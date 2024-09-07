<?php

namespace Mame_Twint\services;

use Mame_Twint\interfaces\iDataProvider;
use Mame_Twint\interfaces\iEventHandler;
use Mame_Twint\lib\iDB_Lock;
use Mame_Twint\lib\Log;
use Mame_Twint\soap\SoapClient;
use Mame_Twint\traits\tHasDataProvider;

class TransactionHandler
{
    use tHasDataProvider;

    const NUM_SOAP_RETRIES    = 3;
    const START_ORDER_TIMEOUT = 600;

    // TWINT transaction states.
    const TWINT_STATUS_FAILURE                    = 'FAILURE';
    const TWINT_STATUS_SUCCESS                    = 'SUCCESS';
    const TWINT_STATUS_PAIRING_ACTIVE             = 'PAIRING_ACTIVE';
    const TWINT_STATUS_NO_PAIRING                 = 'NO_PAIRING';
    const TWINT_STATUS_ORDER_RECEIVED             = 'ORDER_RECEIVED';
    const TWINT_STATUS_ORDER_PENDING              = 'ORDER_PENDING';
    const TWINT_STATUS_IN_PROGRESS                = 'IN_PROGRESS';
    const TWINT_REASON_CLIENT_ABORT               = 'CLIENT_ABORT';
    const TWINT_REASON_CLIENT_TIMEOUT             = 'CLIENT_TIMEOUT';
    const TWINT_REASON_GENERAL_ERROR              = 'GENERAL_ERROR';
    const TWINT_REASON_MERCHANT_ABORT             = 'MERCHANT_ABORT';
    const TWINT_REASON_ORDER_OK                   = 'ORDER_OK';
    const TWINT_REASON_ORDER_RECEIVED             = 'ORDER_RECEIVED';
    const TWINT_REASON_ORDER_PENDING              = 'ORDER_PENDING';
    const TWINT_REASON_ORDER_CONFIRMATION_PENDING = 'ORDER_CONFIRMATION_PENDING';

    // Internal transaction states.
    const STATUS_TIMEOUT      = 'TIMEOUT';
    const STATUS_EXEC_TIMEOUT = 'EXEC_TIMEOUT';

    /** @var SoapClient */
    private $client;

    /** @var iEventHandler */
    private $event_handler;

    /** @var string */
    private $pairing_uuid;

    /** @var string */
    private $order_uuid;

    /**
     * TransactionHandler constructor.
     *
     * @param iDataProvider $data_provider
     * @param iEventHandler $event_handler
     * @throws \Mame_Twint\exceptions\SoapNotLoadedException
     * @throws \Mame_Twint\exceptions\TwintCredentialsNotSetException
     */
    public function __construct( $data_provider, $event_handler )
    {
        $this->client        = new SoapClient( $data_provider );
        $this->data_provider = $data_provider;
        $this->event_handler = $event_handler;
    }

    public function start_order()
    {
        $order_uuid = $this->data_provider->get_order_uuid();

        if ( $order_uuid ) {

            $this->log_event( 'start_order', 'Found orderUUID: ' . $order_uuid );

            // Check status.
            $response = $this->send_repeating_request( [ $this->client, 'get_order' ], [ $order_uuid ] );

            if ( !$response ) {

                $this->log_error( 'start_order', 'Order UUID exists but failed to get response from TWINT.' );
                return false;
            }

            $twint_order = $response->Order;
            $result      = $this->handle_order_status( $twint_order );

            if ( !$result[ 'result' ] ) {
                return false;
            }

            $status = $result[ 'status' ];
            $reason = $result[ 'reason' ];


            switch ( $status ) {

                case static::TWINT_STATUS_SUCCESS:

                    return $this->data_provider->get_order_received_url();

                case static::TWINT_STATUS_IN_PROGRESS:

                    if ( $reason == static::TWINT_REASON_ORDER_CONFIRMATION_PENDING ) {
                        // Deferred
                        return $this->data_provider->get_order_received_url();

                    } else {
                        // ORDER_RECEIVED | ORDER_PENDING
                        $this->event_handler->spawn_monitor_order_task();

                        $url = $this->data_provider->get_order_data( 'url' );
                        if ( !$url ) {
                            break;
                        }

                        $ts = $this->data_provider->get_order_data( 'url_ts' );
                        if ( $ts && $ts + static::START_ORDER_TIMEOUT < time() ) {
                            break;
                        }

                        return $url;
                    }

                case static::TWINT_STATUS_FAILURE:
                default:
                    return false;
            }
        }

        $transaction_reference = $this->data_provider->get_or_create_merchant_reference();
        $response              = $this->client->start_order( null, $this->data_provider->get_total(), $this->data_provider->get_currency(), $transaction_reference, $this->data_provider->is_payment_deferred() );

        $this->data_provider->save_order_data( [ 'reference' => $transaction_reference ] );

        if ( !$response[ 'status' ] ) {
            return false;
        }

        $response_body = $response[ 'response' ];

        if ( !$response_body->OrderUuid ) {
            $this->log_error( 'start_order:response', 'No OrderUuid received.' );
            return false;
        }

        if ( !property_exists( $response_body, 'TwintURL' ) ) {
            $this->log_error( 'start_order:response', 'No TwintURL received.' );
            return false;
        }

        $this->order_uuid = $response_body->OrderUuid;

        $order_status  = $response_body->OrderStatus->Status->_;
        $status_reason = $response_body->OrderStatus->Reason->_;

        $url = add_query_arg( [
            'cancelOrderCallbackURL' => urlencode( $this->data_provider->get_webhook_cancel_url( [ 'uuid' => $this->order_uuid ] ) ),
            'redirectURL'            => urlencode( $this->data_provider->get_webhook_redirect_url( [ 'uuid' => $this->order_uuid ] ) ),
        ], $response_body->TwintURL );

        $this->data_provider->save_order_data( [
            'status'         => $order_status,
            'status_reason'  => $status_reason,
            'payment_status' => $order_status,
            'order_uuid'     => $this->order_uuid,
            'pairing_uuid'   => $this->pairing_uuid,
            'pairing_status' => $response_body->PairingStatus,
            'url'            => $url,
            'url_ts'         => time(),
        ] );

        $this->event_handler->spawn_monitor_order_task();

        $this->log_event( 'Redirect to TWINT', $url );
        return $url;
    }

    public function handle_order_status( $twint_order )
    {
        $status = $twint_order->Status->Status->_;
        $reason = $twint_order->Status->Reason->_;
        $result = false;

        $this->log_event( 'handle_order_status', sprintf( 'Status %1$s, reason %2$s', $status, $reason ) );

        switch ( $status ) {

            case static::TWINT_STATUS_SUCCESS:

                $this->handle_successful_payment( $twint_order );
                $result = true;
                break;

            case static::TWINT_STATUS_IN_PROGRESS:

                if ( $reason == static::TWINT_REASON_ORDER_CONFIRMATION_PENDING ) {

                    if ( !$this->data_provider->is_payment_deferred() ) {
                        $confirmation = $this->confirm_order();

                        if ( !$confirmation ) {

                            // Fails if order already confirmed.
                            $this->log_error( 'handle_order_status', 'ConfirmOrder failed' );

                            $get_order_response = $this->get_order();
                            $get_order          = $get_order_response->Order;

                            if ( !$get_order ) {
                                return [ 'result' => false, 'status' => $status, 'reason' => $reason ];
                            }

                            $status = $get_order->Status->Status->_;
                            $reason = $get_order->Status->Reason->_;

                            $this->log_event( 'handle_order_status', sprintf( 'Order status %1$s, reason %2$s', $status, $reason ) );

                            if ( $status != static::TWINT_STATUS_SUCCESS ) {
                                return [ 'result' => false, 'status' => $status, 'reason' => $reason ];
                            }

                            $twint_order = $get_order;

                        } else {
                            $status      = $confirmation[ 'status' ];
                            $reason      = $confirmation[ 'reason' ];
                            $twint_order = $confirmation[ 'order' ];
                        }

                    }

                    $this->data_provider->save_order_data( [ 'status' => $status, 'status_reason' => $reason, 'payment_status' => $status ] );

                    $this->handle_successful_payment( $twint_order );
                }

                // ORDER_RECEIVED | ORDER_PENDING
                $result = true;
                break;

            case static::TWINT_STATUS_FAILURE:
            default:

                $this->data_provider->delete_order_data( [ 'order_uuid' ] );
                break;
        }

        return [ 'result' => $result, 'status' => $status, 'reason' => $reason ];
    }

    /**
     * Proceed with the TWINT order after pairing is established and Order UUID was received.
     *
     * @return array|bool
     */
    public function monitor_order( $time = 0 )
    {
        $timeout          = $this->data_provider->get_timeout();
        $soap_interval    = $this->data_provider->get_soap_interval();
        $start_time       = microtime( true );
        $this->order_uuid = $this->data_provider->get_order_uuid();
        $max_exec_time    = $this->data_provider->get_max_execution_time();

        do {
            // Get the order response.
            $response = $this->send_repeating_request( [ $this->client, 'monitor_order' ], [ $this->order_uuid ] );

            $result = $this->handle_order_status( $response->Order );

            if ( !$result[ 'result' ] ) {
                return [ 'status' => $result[ 'status' ], 'reason' => $result[ 'reason' ], 'response' => $response ];
            }

            $status = $result[ 'status' ];
            $reason = $result[ 'reason' ];

            if ( ($status == static::TWINT_STATUS_SUCCESS && $reason == static::TWINT_REASON_ORDER_OK)
                || $status == static::TWINT_STATUS_FAILURE ) {
                return [ 'status' => $status, 'reason' => $reason, 'response' => $response ];
            }

            // Abort when timeout reached.
            $end_time    = microtime( true );
            $time_passed = $end_time - $start_time;
            if ( $time_passed > $max_exec_time ) {
                $this->log_event( 'Execution timeout', $time_passed );
                return [ 'status' => static::STATUS_EXEC_TIMEOUT ];
            }

            if ( $time_passed + $time > $timeout ) {
                $this->log_event( 'Global timeout', $time_passed + $time );
                $this->cancel_order();
                return [ 'status' => static::STATUS_TIMEOUT, 'reason' => $reason, 'response' => $response ];
            }

            session_write_close();
            sleep( $soap_interval );
            $time++;

        } while ( $status != static::TWINT_STATUS_IN_PROGRESS || $reason != static::TWINT_REASON_ORDER_CONFIRMATION_PENDING );

        return [ 'status' => $status, 'reason' => $reason, 'response' => $response ];
    }

    /**
     * Send a request to TWINT to confirm the payment and update the WooCommerce order status.
     *
     * @return array|bool
     */
    public function confirm_order()
    {
        $number_retries = 10;
        $count          = 0;
        $soap_interval  = $this->data_provider->get_soap_interval();

        do {
            $order_confirmation = $this->send_repeating_request( [ $this->client, 'confirm_order' ], [ $this->data_provider->get_order_uuid(), $this->data_provider->get_total(), $this->data_provider->get_currency() ] );

            if ( !$order_confirmation ) {
                // Order might already be confirmed.
                return false;
            }

            $status = $order_confirmation->Order->Status->Status->_;
            $reason = $order_confirmation->Order->Status->Reason->_;

            session_write_close();
            sleep( $soap_interval );
            $count++;

        } while ( $status != static::TWINT_STATUS_SUCCESS && $count < $number_retries );

        // Update the order status and the status reason.
        $this->data_provider->save_order_data( [ 'status' => $status, 'status_reason' => $reason, 'payment_status' => $status ] );

        return [ 'status' => $status, 'reason' => $reason, 'order' => $order_confirmation->Order ];
    }

    /**
     * @return bool
     */
    public function cancel_order()
    {
        $order_uuid = $this->data_provider->get_order_uuid();
        $response   = $this->send_repeating_request( [ $this->client, 'cancel_order' ], [ $order_uuid ] );

        if ( !$response ) {
            $this->log_error( 'cancel_order', sprintf( 'Order %1$s could not be cancelled.', $order_uuid ) );
            return false;
        }

        $status = $response->Order->Status->Status->_;
        $reason = $response->Order->Status->Reason->_;

        if ( $status == static::TWINT_STATUS_FAILURE && $reason == static::TWINT_REASON_MERCHANT_ABORT ) {

            $this->log_event( 'Cancel order', 'Order cancelled by merchant.' );
            return true;
        }

        $this->log_error( 'cancel_order', sprintf( 'Order %1$s could not be cancelled.', $order_uuid ) );
        return false;
    }

    /**
     * Updates the order after a successful transaction.
     *
     * @param object $twint_order_object the OrderType object of the TWINT order.
     */
    private function handle_successful_payment( $twint_order_object )
    {
        if ( $this->data_provider->is_order_complete() ) {
            return;
        }

        $lock_name  = MAME_TW_DB_LOCK_CLASS;
        $order_id   = $this->data_provider->get_order_id();
        $order_uuid = $this->data_provider->get_order_uuid();

        $this->event_handler->on_before_handle_successful_payment();

        /** @var iDB_Lock $lock */
        $lock = new $lock_name( $order_id, 'update-payment-complete', $order_id );

        if ( !$lock->acquire() ) {

            $ts = $lock->get_timestamp();

            if ( empty( $ts ) || time() - $ts <= MAME_TW_MAX_LOCK_TIME ) {
                $this->log_event( 'handle_successful_payment', 'Lock could not be acquired.' );
                return;
            }
        }

        $this->log_event( 'handle_successful_payment', 'Lock acquired or skipped.' );

        $transaction_reference = $this->data_provider->get_or_create_merchant_reference();

        if ( empty( $this->data_provider->get_transactions() ) ) {

            $this->data_provider->save_transaction( [
                'operation' => $this->data_provider->is_payment_deferred() ? __( 'Deferred', 'mametwint' ) : __( 'Immediate', 'mametwint' ),
                'reference' => $transaction_reference,
                'amount'    => $this->data_provider->get_formatted_amount(),
                'fee'       => isset( $twint_order_object->Fee ) ? $twint_order_object->Fee->Amount : ''
            ] );
        }

        $data = [
            'order_uuid' => $order_uuid,
            'status'     => $twint_order_object->Status->Status->_,
            'reason'     => $twint_order_object->Status->Reason->_,
        ];

        // Use latest Order UUID
        $this->data_provider->save_order_data( $data );

        $this->log_event( 'Saved order UUID', $order_uuid );

        if ( $this->data_provider->is_order_complete() ) {
            return;
        }

        $this->event_handler->on_successful_payment( $lock );
    }

    public function get_order( $order_uuid = null )
    {
        if ( !$order_uuid ) {
            $order_uuid = $this->data_provider->get_order_uuid();
        }

        return $this->send_repeating_request( [ $this->client, 'get_order' ], [ $order_uuid ] );
    }

    /**
     * Repeatedly sends a SOAP request if it fails, maximum NUM_SOAP_RETRIES number of times.
     *
     * @param callable $func
     * @param array $args
     * @return mixed
     */
    private function send_repeating_request( $func, $args = [] )
    {
        $count = 0;
        do {

            $response = $func( ...$args );

            if ( !$response[ 'status' ] ) {
                session_write_close();
                sleep( MAME_TW_SOAP_INTERVAL );
            }

            $count++;

        } while ( !$response[ 'status' ] && $count < static::NUM_SOAP_RETRIES );

        if ( !$response[ 'status' ] ) {

            $this->log_error( 'Repeated request failed', $response );

            return false;
        }

        // Successful
        return $response[ 'response' ];
    }

    public function handle_webhook_response( $order_uuid = null )
    {
        if ( !$order_uuid ) {
            $order_uuid = $this->data_provider->get_order_uuid();
        } elseif ( $order_uuid != $this->data_provider->get_order_uuid() ) {
            $this->log_error( 'handle_webhook_response', sprintf( 'OrderUUIDs do not match. Expected %1$s, given %2$s.', $this->data_provider->get_order_uuid(), $order_uuid ) );
            $this->event_handler->return_to_checkout( true );
            return false;
        }

        $response = $this->send_repeating_request( [ $this->client, 'get_order' ], [ $order_uuid ] );

        if ( !$response ) {
            $this->log_error( 'handle_webhook_response', 'GetOrder: No response from TWINT.' );
            $this->event_handler->return_to_checkout( true );
        }

        $twint_order = $response->Order;

        $status = $twint_order->Status->Status->_;
        $reason = $twint_order->Status->Reason->_;

        $this->data_provider->save_order_data( [
            'status'        => $status,
            'status_reason' => $reason,
        ] );

        $result = $this->handle_order_status( $twint_order );

        if ( $result[ 'status' ] === static::TWINT_STATUS_IN_PROGRESS && in_array( $result[ 'reason' ], [ static::TWINT_REASON_ORDER_RECEIVED, static::TWINT_REASON_ORDER_PENDING ] ) ) {

            $this->log_event( 'handle_webhook_response', 'Incorrect order state. Polling.' );
            $result = $this->monitor_order();
        }

        $this->event_handler->on_webhook( $result );
    }

    public function handle_webhook_cancel( $order_uuid = null )
    {
        $this->log_event( 'handle_webhook_cancel', 'Payment canceled by customer.' );
        if ( $this->cancel_order() ) {
            $this->event_handler->on_cancelled_by_customer();
        }
        $this->log_event( 'handle_webhook_cancel', 'Could not cancel order.' );

        $this->event_handler->return_to_payment_page();
    }

    public function background_task( $time = 0 )
    {
        $mo_response = $this->monitor_order( $time );

        if ( !$mo_response || $mo_response[ 'status' ] == static::STATUS_TIMEOUT || $this->data_provider->has_memory_exceeded() ) {
            $this->log_event( 'monitor_and_confirm_order', 'Timeout' );
            return;
        }

        if ( $mo_response[ 'status' ] == static::STATUS_EXEC_TIMEOUT ) {
            $this->log_event( 'monitor_and_confirm_order', 'Server timeout' );

            $time += $this->data_provider->get_max_execution_time();
            $this->event_handler->spawn_monitor_order_task( $time );
            return;
        }

        if ( $mo_response[ 'status' ] == static::TWINT_STATUS_FAILURE ) {

            $this->data_provider->delete_order_data( [ 'order_uuid' ] );
            $this->log_error( 'monitor_and_confirm_order', json_encode( $mo_response ) );
            return;
        }

        $this->handle_successful_payment( $mo_response[ 'response' ]->Order );
    }
}