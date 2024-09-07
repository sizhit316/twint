<?php

namespace Mame_Twint\services;

use Mame_Twint\exceptions\SoapNotLoadedException;
use Mame_Twint\exceptions\TwintCredentialsNotSetException;
use Mame_Twint\interfaces\iDataProvider;
use Mame_Twint\interfaces\iEventHandler;
use Mame_Twint\soap\SoapClient;

class AdminTransactionHandler
{
    /** @var SoapClient */
    private $client;

    /** @var iDataProvider */
    private $data_provider;

    /** @var iEventHandler */
    private $event_handler;

    /**
     * AdminTransactionHandler constructor.
     *
     * @param iDataProvider $data_provider
     * @param iEventHandler $event_handler
     *
     * @throws SoapNotLoadedException
     * @throws TwintCredentialsNotSetException
     */
    public function __construct( $data_provider, $event_handler )
    {
        $this->client        = new SoapClient( $data_provider );
        $this->data_provider = $data_provider;
        $this->event_handler = $event_handler;
    }

    /**
     * Enrolls the cashier register.
     * returns [status, message].
     *
     * @return array
     */
    public function enroll_cashier_register()
    {
        $enroll_response = $this->client->enroll_cash_register();

        if ( !$enroll_response[ 'status' ] ) {
            return [ 'status' => false, 'message' => $enroll_response[ 'message' ] ];
        }

        return [ 'status' => true, 'message' => __( 'Cash register successfully enrolled', 'mametwint' ) ];
    }

    /**
     * Processes a refund and returns true on success.
     * If $amount is missing a full refund is carried out.
     *
     * @param string $amount
     * @return bool|array
     */
    public function create_refund( $amount = null )
    {
        if ( !$amount ) {
            $amount = $this->data_provider->get_formatted_amount();
        }

        $reference = $this->data_provider->get_or_create_merchant_reference( true );
        $currency  = $this->data_provider->get_currency();


        $response = $this->client->reversal( $amount, $currency, $reference, $this->data_provider->get_order_uuid() );

        if ( !$response[ 'status' ] ) {
            return false;
        }

        $response_obj = $response[ 'response' ];

        if ( isset( $response_obj->OrderStatus ) && isset( $response_obj->OrderStatus->Status ) ) {

            $transaction_data = [ 'operation' => 'REVERSAL', 'amount' => $amount, 'reference' => $reference ];

            if ( $response_obj->OrderStatus->Status->_ == 'SUCCESS' ) {

                $this->data_provider->save_transaction( $transaction_data );

                return $transaction_data;

            } elseif ( $response_obj->OrderStatus->Status->_ == 'IN_PROGRESS' ) {

                // Confirmation is needed.
                $confirmation = $this->client->confirm_order( $response_obj->OrderUuid, $amount, $currency );

                if ( isset( $confirmation->Order ) && isset( $confirmation->Order->Status ) && isset( $confirmation->Order->Status->Status ) ) {

                    if ( $confirmation->Order->Status->Status->_ == 'SUCCESS' )

                        $this->data_provider->save_transaction( $transaction_data );

                    return $transaction_data;
                }
            }
        }
        return false;
    }

    public function confirm_transaction()
    {
        $response = $this->client->confirm_order( $this->data_provider->get_order_uuid(), $this->data_provider->get_total(), $this->data_provider->get_currency() );

        if ( !$response[ 'status' ] ) {
            return [ 'status' => false, 'message' => $response[ 'message' ] ];
        }

        $order_confirmation = $response[ 'response' ];

        if ( property_exists( $order_confirmation, 'OrderStatus' ) ) {
            $order_confirmation_status = $order_confirmation->OrderStatus->Status->_;
            $order_confirmation_reason = $order_confirmation->OrderStatus->Reason->_;
        } else {
            $order_confirmation_status = $order_confirmation->Order->Status->Status->_;
            $order_confirmation_reason = $order_confirmation->Order->Status->Reason->_;
        }

        if ( $order_confirmation_status == 'SUCCESS' && $order_confirmation_reason == 'ORDER_OK' ) {

            $fee = $order_confirmation->Order->Fee->Amount;

            $this->data_provider->save_order_data( [ 'status' => $order_confirmation_status, 'status_reason' => $order_confirmation_reason, 'payment_status' => $order_confirmation_status ] );
            $this->data_provider->save_transaction( [ 'operation' => __( '(Settlement)', 'mametwint' ), 'fee' => $fee ] );

            return [ 'status' => true, 'order_status' => $order_confirmation_status, 'status_reason' => $order_confirmation_reason, 'fee' => $fee ];
        }

        Logger::log_error( 'Transaction settlement failed', $order_confirmation, $this->data_provider );

        return [ 'status' => false ];
    }

    public function cancel_transaction()
    {
        $response = $this->client->cancel_order( $this->data_provider->get_order_uuid() );

        if ( !$response[ 'status' ] ) {
            return [ 'status' => false, 'message' => $response[ 'message' ] ];
        }

        $confirmation = $response[ 'response' ];

        $status = $confirmation->Order->Status->Status->_;
        $reason = $confirmation->Order->Status->Reason->_;
        $fee    = property_exists( $confirmation->Order, 'Fee' ) ? $confirmation->Order->Fee->Amount : '';

        $this->data_provider->save_order_data( [ 'status' => $status, 'status_reason' => $reason, 'payment_status' => $status ] );
        $this->data_provider->save_transaction( [ 'operation' => __( '(Cancellation)', 'mametwint' ), 'fee' => $fee ] );

        return [ 'status' => true, 'order_status' => $status, 'status_reason' => $reason, 'fee' => $fee ];
    }

    /**
     * Returns the TWINT status and reason of an order.
     *
     * @param $order_uuid
     * @return array
     */
    public function check_order_status( $order_uuid )
    {
        $response = $this->client->monitor_order( $order_uuid );
        if ( !$response[ 'status' ] ) {
            return $response;
        }

        $order_object = $response[ 'response' ];

        $order_status  = $order_object->Order->Status->Status->_;
        $status_reason = $order_object->Order->Status->Reason->_;

        return [ 'status' => true, 'order_status' => $order_status, 'status_reason' => $status_reason ];
    }

    /**
     * Renews the certificate if it can already be renewed.
     *
     * success : [ status = true, message, expiry ]
     * failure : [ status = false, message, response ]
     *
     * @return array
     * @throws SoapNotLoadedException
     * @throws TwintCredentialsNotSetException
     */
    public function renew_certificate()
    {
        $dir            = $this->data_provider->get_twint_files_dir();
        $temp_file_path = $dir . 'twint_temp.pem';

        $this->client = new SoapClient();

        $response = $this->client->renew_certificate();

        if ( $response[ 'status' ] ) {

            $certificate = $response[ 'response' ]->MerchantCertificate;
            $newExpiry   = $response[ 'response' ]->ExpirationDate;

            if ( !empty( $certificate ) ) {

                // Save temporary certificate file.
                $pem_content = CertificateHandler::convert_p12_to_pem( $certificate, MAME_TW_CERTIFICATE_PASS );

                file_put_contents( $temp_file_path, $pem_content );

                sleep( 5 );

                // Enroll
                $response = $this->client->enroll_cash_register();
                if ( !$response[ 'status' ] ) {

                    // Delete temp file.
                    @unlink( $temp_file_path );

                    Logger::log_error( 'Certificate renewal', 'Enroll cashier register failed with new certificate.' );
                    return [ 'status' => false, 'message' => 'Enroll cashier register failed with new certificate.', 'response' => $response ];
                }

                // Replace old with new certificate.
                file_put_contents( $dir . MAME_TW_CERTIFICATE_NAME, $pem_content );
                @unlink( $temp_file_path );

                return [ 'status' => true, 'message' => 'Certificate successfully renewed and cashier register enrolled.', 'expiry' => $newExpiry ];
            }

            Logger::log_error( 'Certificate renewal failed', $response[ 'message' ] );
            return [ 'status' => false, 'message' => 'Certificate renewal failed.', 'response' => $response ];
        }
    }

    /**
     * Returns the expiry date of the certificate. If $force_request is set a new SOAP request is sent and the date is updated in the database.
     *
     * @return array
     */
    public function get_certificate_expiry_date()
    {
        $certificate_expiry = $this->client->get_certificate_validity();

        if ( !$certificate_expiry[ 'status' ] ) {
            return $certificate_expiry;
        }

        $expiry          = $certificate_expiry[ 'response' ]->CertificateExpiryDate;
        $renewal_allowed = $certificate_expiry[ 'response' ]->RenewalAllowed;

        return [ 'status' => true, 'expiry' => $expiry, 'renewal_allowed' => $renewal_allowed ];
    }


    /**
     * Returns the number of days after which the certificate expires.
     * A negative number indicates the number of days the certificate has expired since.
     * Returns false if the request fails.
     *
     * @return bool|false|int
     */
    public function certificate_expires_in()
    {
        $response = $this->get_certificate_expiry_date();

        if ( !$response[ 'status' ] ) {
            return false;
        }

        $timestamp = strtotime( $response[ 'expiry' ] );
        $now       = time();

        $difference = $timestamp - $now;

        return $difference;
    }

    /**
     * Returns true if the renewal of the certificate is allowed otherwise returns false.
     * Returns null if the request fails.
     *
     * @return bool|null
     */
    public function is_certificate_renewal_allowed()
    {
        $response = $this->get_certificate_expiry_date();

        if ( !$response[ 'status' ] ) {
            return null;
        }

        return $response[ 'renewal_allowed' ];

    }
}