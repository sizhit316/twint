<?php

namespace Mame_Twint\soap;

use Mame_Twint\exceptions;
use Mame_Twint\interfaces\iDataProvider;
use Mame_Twint\lib\Helper;
use Mame_Twint\TWINT;

class SoapClient
{
    const HEADER_NAMESPACE    = 'http://service.twint.ch/header/types/v8_4';
    const WSDL_FILE           = 'schemas/v8_4/TWINTMerchantService_v8.4.wsdl';
    const PAT_LOCATION        = 'https://service-pat.twint.ch/merchant/service/TWINTMerchantServiceV8_4';
    const INT_LOCATION        = 'https://service-int.twint.ch/merchant/service/TWINTMerchantServiceV8_4';
    const PRODUCTION_LOCATION = 'https://service.twint.ch/merchant/service/TWINTMerchantServiceV8_4';

    /** @var \SoapClient */
    private $client;

    /** @var string */
    private $message_id;

    /** @var string */
    private $password;

    /** @var iDataProvider */
    private $data_provider;

    /**
     * SoapClient constructor.
     *
     * @param null|iDataProvider $data_provider
     * @throws exceptions\SoapNotLoadedException
     * @throws exceptions\TwintCredentialsNotSetException
     */
    public function __construct( $data_provider = null )
    {
        $this->data_provider = $data_provider;
        $this->create_client();
    }

    /**
     * @param $fault
     * @return string
     */
    private function get_error( $fault )
    {
        return sprintf( "ERROR: The TWINT Server returned Error-Code '%s' with the following message: %s ()", $fault->faultcode, $fault->faultstring );
    }

    /**
     * Set SOAP headers.
     */
    private function set_headers()
    {
        $headers = array();

        $this->message_id = Helper::create_v4_uuid();

        $namespace   = static::HEADER_NAMESPACE;
        $header_body = array(
            "MessageId"             => $this->message_id,
            "ClientSoftwareName"    => MAME_TW_SOFTWARE_NAME,
            "ClientSoftwareVersion" => MAME_TW_PLUGIN_VERSION
        );
        $headers[]   = new \SOAPHeader( $namespace, 'RequestHeaderElement', $header_body );

        $this->client->__setSoapHeaders( $headers );
    }

    /**
     * Create the SOAP client.
     *
     * @param null|string $certificate_path
     * @throws exceptions\SoapNotLoadedException
     * @throws exceptions\TwintCredentialsNotSetException
     */
    private function create_client( $certificate_path = null )
    {
        if ( !class_exists( 'SoapClient' ) ) {
            TWINT::$logger::log_error( 'SoapClient not found', 'The SOAP extension is not enabled on your server. SOAP is required for the TWINT plugin.', $this->data_provider );

            throw new exceptions\SoapNotLoadedException();
        }

        $certificate    = $certificate_path ?: MAME_TW_CERTIFICATE_PATH;
        $this->password = MAME_TW_CERTIFICATE_PASS;

        if ( !$certificate || !$this->password || empty( $this->data_provider->get_merchant_uuid() ) ) {
            throw new exceptions\TwintCredentialsNotSetException();
        }

        $wsdl = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . static::WSDL_FILE;

        $opts = array(
            'ssl' => array( 'ciphers' => 'DEFAULT:!DH' )
        );

        $args = apply_filters( MAME_TW_PREFIX . '_soap_data', [
            'location'           => $this->get_location(),
            'local_cert'         => $certificate,
            'passphrase'         => $this->password,
            //'trace' => true,
            //'encoding'           => 'UTF-8',
            'connection_timeout' => MAME_TW_CONNECTION_TIMEOUT,
            //'exceptions' => true,
            'keep_alive'         => false,
            'compression'        => ( SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP ),
            'stream_context'     => stream_context_create( $opts ),
            //'soap_version' => SOAP_1_2,
            //'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_FIXED | 1,
            //'cache_wsdl' => WSDL_CACHE_NONE
        ] );

        if ( !empty( MAME_TW_PROXY_HOST ) ) {
            $args = array_merge( $args, array( 'proxy_host' => MAME_TW_PROXY_HOST ) );
        }
        if ( !empty( MAME_TW_PROXY_PORT ) ) {
            $args = array_merge( $args, array( 'proxy_port' => MAME_TW_PROXY_PORT ) );
        }

        $this->client = new \SoapClient( $wsdl, $args );

        $this->set_headers();
    }

    /**
     * Sends a SOAP request with function $func and function arguments $args.
     *
     * Returns an array:
     *
     * [
     *  status, // true on success, false on error
     *  message, // error message if  status==false
     *  response // webservice response if status==true
     * ]
     *
     * @param $func
     * @param $args
     * @return array
     */
    private function send_soap_request( $args, $func )
    {
        if ( !is_callable( $func ) ) {
            return [ 'status' => false, 'message' => __( 'SOAP connection to TWINT not set up yet', 'mametwint' ) ];
        }

        TWINT::$logger::log_event( Helper::get_callable_name( $func ) . ' - Request - ' . $this->message_id, $args, $this->data_provider );

        try {

            $response = $func( $args );

        } catch ( \SoapFault $e ) {

            TWINT::$logger::log_error( Helper::get_callable_name( $func ) . ' - Response - ' . $this->message_id, [ 'code' => $e->faultcode, 'message' => $e->faultstring ], $this->data_provider );

            if ( MAME_TW_DEBUG ) {
                print_r( $this->client );
            }

            return [ 'status' => false, 'message' => $this->get_error( $e ) ];
        }

        TWINT::$logger::log_event( Helper::get_callable_name( $func ) . ' - Response - ' . $this->message_id, $response, $this->data_provider );

        return [ 'status' => true, 'response' => $response ];
    }

    /**
     * Returns the SOAP request location.
     *
     * @return string
     */
    private function get_location()
    {

        switch ( MAME_TW_ENVIRONMENT ) {

            case 'int':
                return static::INT_LOCATION;
                break;
            case 'pat':
                return static::PAT_LOCATION;
                break;
            default:
                return static::PRODUCTION_LOCATION;
                break;
        }
    }

    private function get_default_params()
    {
        return [
            'MerchantInformation' => [
                'MerchantUuid'   => $this->data_provider->get_merchant_uuid(),
                'CashRegisterId' => MAME_TW_CASHREGISTER_ID
            ]
        ];
    }

    /**
     * Check the Webservice system status.
     *
     * @return array
     */
    public function check_system_status()
    {
        return $this->send_soap_request( $this->get_default_params(), [ $this->client, 'checkSystemStatus' ] );
    }

    /**
     * Enrolls the cash register to TWINT (EPOS).
     *
     * @return array
     */
    public function enroll_cash_register()
    {
        $params                       = $this->get_default_params();
        $params[ 'CashRegisterType' ] = 'EPOS';

        return $this->send_soap_request( $params, [ $this->client, 'enrollCashRegister' ] );
    }

    /**
     * TWINT check-in request.
     *
     * @param bool $request_alias
     * @param null|string $customer_relation_uuid
     * @return array
     */
    public function request_checkin( $request_alias = false, $customer_relation_uuid = null )
    {
        $params                      = $this->get_default_params();
        $params[ 'QRCodeRendering' ] = 'true';

        if ( $customer_relation_uuid ) {
            $params[ 'CustomerRelationUuid' ] = $customer_relation_uuid;

        } else {
            $params[ 'UnidentifiedCustomer' ] = true;
        }

        if ( $request_alias ) {
            $params[ 'RequestCustomerRelationAlias' ] = 'RECURRING_PAYMENT';
        }

        return $this->send_soap_request( $params, [ $this->client, 'requestCheckIn' ] );
    }

    /**
     * Returns the TWINT check-in status.
     *
     * @param string $pairing_uuid
     *
     * @param null|string $customer_relation_uuid
     * @return array
     */
    public function monitor_checkin( $pairing_uuid, $customer_relation_uuid = null )
    {
        $params = $this->get_default_params();

        if ( $customer_relation_uuid ) {
            $params[ 'CustomerRelationUuid' ] = $customer_relation_uuid;
        } else {
            $params[ 'PairingUuid' ] = $pairing_uuid;
        }

        $params[ 'WaitForResponse' ] = true;

        return $this->send_soap_request( $params, [ $this->client, 'monitorCheckIn' ] );
    }

    /**
     * Cancel the check-in to TWINT.
     *
     * @param string $pairing_uuid
     *
     * @return array
     */
    public function cancel_checkin( $pairing_uuid )
    {
        $params = array_merge(
            $this->get_default_params(),
            [
                'Reason'      => 'PAYMENT_ABORT',
                'PairingUuid' => $pairing_uuid
            ]
        );

        return $this->send_soap_request( $params, [ $this->client, 'cancelCheckIn' ] );
    }

    /**
     * Start the TWINT order.
     *
     * @param string $pairing_uuid
     * @param string $amount
     * @param string $currency
     * @param string $merchant_transaction_reference
     * @param bool $deferred
     * @param null|string $customer_relation_uuid
     * @return mixed
     */
    public function start_order( $pairing_uuid, $amount, $currency, $merchant_transaction_reference, $deferred = false, $customer_relation_uuid = null )
    {
        $params = array_merge(
            $this->get_default_params(),
            [
                'Order' => array(
//                    'type'                         => $deferred ? 'PAYMENT_DEFERRED' : 'PAYMENT_IMMEDIATE',
                    'type'                         => 'PAYMENT_DEFERRED',
                    //                    'confirmationNeeded'           => $deferred,
                    'confirmationNeeded'           => true,
                    'PostingType'                  => 'GOODS',
                    'RequestedAmount'              => array(
                        'Amount'   => $amount,
                        'Currency' => $currency
                    ),
                    'MerchantTransactionReference' => $merchant_transaction_reference,
                ),
            ]
        );

        if ( MAME_TW_TWINT_PM_PAGE ) {

            $params[ 'PaymentLayerRendering' ]      = 'PAYMENT_PAGE';
            $params[ 'UnidentifiedCustomer' ]       = true;
        } else {

            // TODO Not needed anymore?
            if ( $customer_relation_uuid ) {
                $params[ 'CustomerRelationUuid' ] = $customer_relation_uuid;
            } else {
                $params[ 'PairingUuid' ] = $pairing_uuid;
            }
        }

        return $this->send_soap_request( $params, [ $this->client, 'startOrder' ] );
    }

    /**
     * Processes a refund.
     *
     * @param string $amount
     * @param string $currency
     * @param string $merchant_transaction_reference
     * @param string $order_uuid
     * @return mixed
     */
    public function reversal( $amount, $currency, $merchant_transaction_reference, $order_uuid )
    {
        $params = array_merge(
            $this->get_default_params(),
            [
                'Order'                => array(
                    'type'                         => 'REVERSAL',
                    'confirmationNeeded'           => false,
                    'PostingType'                  => 'GOODS',
                    'RequestedAmount'              => array(
                        'Amount'   => $amount,
                        'Currency' => $currency
                    ),
                    'MerchantTransactionReference' => $merchant_transaction_reference,
                    'Link'                         => array(
                        'OrderUuid' => $order_uuid,
                    )
                ),
                'UnidentifiedCustomer' => true,
            ]
        );

        return $this->send_soap_request( $params, [ $this->client, 'startOrder' ] );
    }

    /**
     * Check the TWINT order status.
     *
     * @param string $order_uuid
     *
     * @return array
     */
    public function monitor_order( $order_uuid )
    {
        $params = array_merge(
            $this->get_default_params(),
            [ 'OrderUuid' => $order_uuid ]
        );

        $params[ 'WaitForResponse' ] = true;

        return $this->send_soap_request( $params, [ $this->client, 'monitorOrder' ] );
    }

    /**
     * Confirm the order after payment is completed in TWINT.
     *
     * @param string $order_uuid
     * @param double $amount
     * @param string $currency
     *
     * @return array
     */
    public function confirm_order( $order_uuid, $amount, $currency )
    {
        $params = array_merge(
            $this->get_default_params(),
            [
                'OrderUuid'       => $order_uuid,
                'RequestedAmount' => [
                    'Amount'   => $amount,
                    'Currency' => $currency,
                ]
            ]
        );

        return $this->send_soap_request( $params, [ $this->client, 'confirmOrder' ] );
    }

    /**
     * Cancel the TWINT order.
     *
     * @param string $order_uuid
     *
     * @return array
     */
    public function cancel_order( $order_uuid )
    {
        $params = array_merge(
            $this->get_default_params(),
            [ 'OrderUuid' => $order_uuid ]
        );

        return $this->send_soap_request( $params, [ $this->client, 'cancelOrder' ] );
    }

    /**
     * Find a particular order.
     *
     * If $args is null, the transactions of the current day are returned.
     * If $args is a string the search will be by transaction reference.
     * To search by a date range an array with two elements [startDate, endDate].
     * DateTime has to be formated with format('Y-m-d\Th:i:s') or format( DateTime::ATOM )
     *
     * @param null|array|string $args
     * @return array
     */
    public function find_order( $args = null )
    {
        $params = array(
            'MerchantUuid'   => $this->data_provider->get_merchant_uuid(),
            'CashRegisterId' => MAME_TW_CASHREGISTER_ID,
        );

        if ( is_string( $args ) ) {
            $params[ 'MerchantTransactionReference' ] = $args;
            // 'OrderUuid'
        } elseif ( is_array( $params ) && count( $params ) == 2 ) {
            $params[ 'SearchByDate' ] = [
                'SearchStartDate' => $args[ 0 ],
                'SearchEndDate'   => $args[ 1 ]
            ];
        }

        return $this->send_soap_request( $params, [ $this->client, 'findOrder' ] );
    }

    public function get_order( $order_uuid )
    {
        $params = [
            'MerchantUuid' => $this->data_provider->get_merchant_uuid(),
            'OrderUuid'    => $order_uuid,
        ];

        return $this->send_soap_request( $params, [ $this->client, 'GetOrder' ] );
    }

    /**
     * Checks the expiry date of the certificate and if it can already be renewed.
     *
     * @return array
     */
    public function get_certificate_validity()
    {
        $params = array(
            'MerchantUuid' => $this->data_provider->get_merchant_uuid(),
        );

        return $this->send_soap_request( $params, [ $this->client, 'getCertificateValidity' ] );
    }

    /**
     * Renew the certificate
     *
     * @return array
     */
    public function renew_certificate()
    {
        $params = array(
            "MerchantUuid"        => $this->data_provider->get_merchant_uuid(),
            'CertificatePassword' => $this->password
        );

        return $this->send_soap_request( $params, [ $this->client, 'renewCertificate' ] );
    }

}
