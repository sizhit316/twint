<?php

namespace Mame_Twint\services;

use Mame_Twint\interfaces\iEventHandler;
use Mame_Twint\lib\iDB_Lock;
use Mame_Twint\lib\Lock;
use Mame_Twint\lib\WC_Helper;
use Mame_Twint\traits\tHasDataProvider;
use Mame_Twint\Twint_Helper;

class EventHandler implements iEventHandler
{
    use tHasDataProvider;

    /**
     * iEventHandler constructor.
     * @param $data_provider
     */
    public function __construct( $data_provider )
    {
        $this->data_provider = $data_provider;
    }

    /**
     * Initialise actions.
     */
    public static function init_actions()
    {
        add_action( MAME_TW_PREFIX . '_confirm_order', __CLASS__ . '::confirm_order', 10, 1 );
        add_action( MAME_TW_PREFIX . '_remove_all_locks', __CLASS__ . '::remove_all_locks', 10, 1 );
    }

    public function on_before_handle_successful_payment()
    {
        $order_id = $this->data_provider->get_order_id();
        $order    = wc_get_order( $order_id );
        if ( empty( WC_Helper::get_order_meta( $order, '_' . MAME_TW_PREFIX . '_payment_successful', true ) ) ) {
            WC_Helper::update_order_meta( $order, '_' . MAME_TW_PREFIX . '_payment_successful', time() );
        }
    }

    /**
     * Handles all order updates after a successful payment.
     * @param iDB_Lock $lock
     */
    public function on_successful_payment( $lock )
    {
        $order_id    = $this->data_provider->get_order_id();
        $order       = wc_get_order( $order_id );
        $is_deferred = $this->data_provider->is_payment_deferred();

        if ( !$order ) {
            $this->log_error( 'on_successful_payment', 'Order not found for ID ' . $order_id );
            return;
        }

        if ( WC_Helper::is_order_complete( $order ) ) {
            $this->log_event( 'on_successful_payment', sprintf( 'Order %1$s already completed.', $order_id ) );
            return;
        }

        $uniqid = uniqid();
        WC_Helper::update_order_meta( $order, '_' . MAME_TW_PREFIX . '_payment_complete', $uniqid );
        if ( WC_Helper::get_order_meta( $order_id, '_' . MAME_TW_PREFIX . '_payment_complete', true ) != $uniqid ) {
            $this->log_event( 'on_successful_payment', sprintf( 'Order update lock IDS: expected %1$s, got %2$s.', $uniqid, WC_Helper::get_order_meta( $order_id, '_' . MAME_TW_PREFIX . '_payment_complete', true ) ) );
            return;
        }

        if ( WC_Helper::get_order_meta( $order_id, '_twint_order_payment_status', true ) ) {
            $this->log_event( 'on_successful_payment', 'Order status update locked.' );
        }

        if ( WC_Helper::get_order_meta( $order_id, '_' . MAME_TW_PREFIX . '_payment_complete', true ) == $uniqid && !WC_Helper::get_order_meta( $order_id, '_twint_order_payment_status', true ) ) {

            $this->log_event( 'Update order status', 'start' );

            WC_Helper::update_order_successful( $order, $is_deferred );

            // Update payment status.
            WC_Helper::update_order_meta( $order, '_twint_order_payment_status', 'SUCCESS', false );

            $order->add_order_note( __( 'TWINT payment successful.', 'mametwint' ) );

            $this->log_event( 'Updated order status', 'complete' );

            WC_Helper::delete_order_meta( $order, '_' . MAME_TW_PREFIX . '_payment_successful' );

            do_action( MAME_TW_PREFIX . '_after_payment_complete', $order_id );
        }
//        $lock->release();

        /*     if ( !wp_next_scheduled( MAME_TW_PREFIX . '_remove_all_locks', [ $order_id ] ) ) {
                 wp_schedule_single_event( time() + 600, MAME_TW_PREFIX . '_remove_all_locks', [ $order_id ] );
             }*/
    }

    /**
     * Handles the cancellation by the customer.
     */
    public function on_cancelled_by_customer()
    {
        $order_id = $this->data_provider->get_order_id();
        $order    = wc_get_order( $order_id );
        wp_redirect( $order->get_cancel_order_url() );
        exit;
    }

    /**
     * Returns true if a WooCommerce checkout notice of type $type is already enqueued which starts with 'TWINT'.
     *
     * @param string $notice
     * @param string $type
     */
    private function add_checkout_notice( $notice, $type )
    {
        if ( wc_has_notice( $notice, $type ) ) {
            return;
        }

        wc_add_notice( $notice, $type );
    }

    public static function remove_all_locks( $order_id )
    {
        Lock::remove_all_locks( 'order-started-' . $order_id, Twint_Helper::get_uploads_dir() );
        Lock::remove_all_locks( 'update-payment-complete-' . $order_id, Twint_Helper::get_uploads_dir() );
    }

    public function on_webhook( $result )
    {
        $status = $result[ 'status' ] ?? 'null';
        $reason = $result[ 'reason' ] ?? 'null';

        $this->log_event( 'on_webhook', sprintf( 'Status %1$s, reason %2$s', $status, $reason ) );

        switch ( $status ) {

            case TransactionHandler::TWINT_STATUS_SUCCESS:

                if ( $reason == TransactionHandler::TWINT_REASON_ORDER_OK ) {
                    wp_redirect( $this->data_provider->get_order_received_url() );
                    die();
                }

                $this->log_error( 'on_webhook', 'Partial amount approved' );

                $subject = __( 'TWINT payment was successful but only partial amount was approved', 'mametwint' );
                $message = sprintf( __( 'The TWINT payment for order %1$s was successful but only a partial amount was approved. The WooCommerce order was not successful.', 'mametwint' ), $this->data_provider->get_order_id() );
                Mailer::send_admin_email( $subject, $message );

                $this->add_checkout_notice( __( 'Payment failed because it was only partially approved. Please contact the shop administrator.', 'mametwint' ), 'error' );
                wp_redirect( $this->data_provider->get_checkout_payment_url() );
                die();

            case TransactionHandler::TWINT_STATUS_IN_PROGRESS:

                switch ( $reason ) {

                    case TransactionHandler::TWINT_REASON_ORDER_CONFIRMATION_PENDING:

                        if ( !$this->data_provider->is_payment_deferred() ) {

                            // Spawn single cron action to confirm order
                            $order_id = $this->data_provider->get_order_id();
                            if ( !wp_next_scheduled( MAME_TW_PREFIX . '_confirm_order', [ $order_id ] ) ) {
                                wp_schedule_single_event( time() + 300, MAME_TW_PREFIX . '_confirm_order', [ $order_id ] );
                            }
                        }

                        wp_redirect( $this->data_provider->get_order_received_url() );
                        die();

                    case TransactionHandler::TWINT_REASON_ORDER_RECEIVED:
                    case TransactionHandler::TWINT_REASON_ORDER_PENDING:
                    default:

                        $this->add_checkout_notice( __( 'The payment with TWINT was not confirmed, but may still have been successful. Please contact the website administrator if you don\'t receive a confirmation email for your order.', 'mametwint' ), 'notice' );

                        wp_redirect( $this->data_provider->get_checkout_url() );
                        die();
                }

            case TransactionHandler::TWINT_STATUS_FAILURE:

                $this->data_provider->delete_order_data( [ 'order_uuid' ] );

                $this->log_error( 'on_webhook', sprintf( 'Status %1$s, reason %2$s', $status, $reason ) );

                switch ( $reason ) {

                    case TransactionHandler::TWINT_REASON_CLIENT_ABORT:
                        wp_redirect( $this->data_provider->get_checkout_payment_url() );
                        die();

                    case TransactionHandler::TWINT_REASON_MERCHANT_ABORT:
                    case TransactionHandler::TWINT_REASON_CLIENT_TIMEOUT:
                        $this->add_checkout_notice( __( 'The payment timed out. Please try again or choose a different payment method.', 'mametwint' ), 'error' );
                        wp_redirect( $this->data_provider->get_checkout_url() );
                        die();

                    case TransactionHandler::TWINT_REASON_GENERAL_ERROR:
                    default:
                        $this->add_checkout_notice( __( 'Payment not successful. Please try again or choose a different payment method.', 'mametwint' ), 'error' );
                        wp_redirect( $this->data_provider->get_checkout_url() );
                        die();
                }

            default:
                $this->add_checkout_notice( __( 'Error in transaction. Please contact the shop administrator.', 'mametwint' ), 'error' );

                wp_redirect( $this->data_provider->get_checkout_url() );
                die();
        }
    }

    public function spawn_monitor_order_task( $time = 0 )
    {
        $bg_immediate = get_option( 'mame_tw_asnyc_bg_task_request_immediately' ) ?: MAME_TW_DEFAULT_ASYNC_REQUEST_IMMEDIATE;
        if ( $bg_immediate == 'yes' ) {
            do_action( 'twint_monitor_order_immediate', $this->data_provider->get_order_id(), $this->data_provider->get_order_uuid(), $time );
        }

        $bg_shutdown = get_option( 'mame_tw_asnyc_bg_task_request_shutdown' ) ?: MAME_TW_DEFAULT_ASYNC_REQUEST_SHUTDOWN;
        if ( $bg_shutdown == 'yes' ) {
            do_action( 'twint_monitor_order', $this->data_provider->get_order_id(), $this->data_provider->get_order_uuid(), $time );
        }
    }

    /**
     * @param bool $error
     * @return void
     */
    public function return_to_checkout( $error = false )
    {
        if ( $error ) {
            $this->add_checkout_notice( __( 'Error in transaction. Please try again later or choose a different payment method.', 'mametwint' ), 'error' );
        }

        wp_redirect( $this->data_provider->get_checkout_url() );
        die();
    }

    public function return_to_payment_page( $error = false )
    {
        if ( $error ) {
            $this->add_checkout_notice( __( 'Error in transaction. Please try again later or choose a different payment method.', 'mametwint' ), 'error' );
        }

        wp_redirect( $this->data_provider->get_checkout_payment_url() );
        die();
    }

    public function redirect_to_receipt_page()
    {
        wp_redirect( $this->data_provider->get_order_received_url() );
        die();
    }

    public function fail_order()
    {
        $order_id = $this->data_provider->get_order_id();
        $order    = wc_get_order( $order_id );
        if ( !$order ) {
            $this->log_error( 'fail_order', sprintf( 'Order with ID %1$s not found.' ) );
            return;
        }

        if ( $order->get_status() != 'failed' ) {
            $order->update_status( 'failed', __( 'TWINT payment failed.' ) );
        }
    }
}