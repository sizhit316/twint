<?php

namespace Mame_Twint\services;

use Mame_Twint\gateway\Twint_Data;
use Mame_Twint\Twint_Helper;
use Mame_Twint\interfaces\iDataProvider;
use Mame_Twint\lib\WC_Helper;

class DataProvider implements iDataProvider
{
    /** @var string */
    public $order_id;

    /** @var \WC_Order */
    public $order;

    /** @var bool */
    private $is_deferred;

    /** @var string|int */
    private $process_id;

    /** @var string */
    private $merchant_uuid;

    public function __construct()
    {
        if ( get_option( 'mametw_settings_payment_type' ) == 'deferred' ) {
            $this->is_deferred = true;
        }

        $this->process_id = isset( $_SERVER[ 'REQUEST_TIME_FLOAT' ] ) ? $_SERVER[ 'REQUEST_TIME_FLOAT' ] : uniqid();
    }

    public static function create()
    {
        return new self();
    }

    /**
     * @param string $order_id
     * @return DataProvider
     */
    public function set_order( $order_id )
    {
        $this->order_id = $order_id;
        $this->order    = wc_get_order( $order_id );

        return $this;
    }

    /**
     * Returns the currency ISO Code.
     *
     * @return string
     */
    public function get_currency()
    {
        if ( $this->order && $currency = $this->order->get_currency() ) {
            return $currency;
        }

        return apply_filters( MAME_TW_PREFIX . '_currency', get_woocommerce_currency() );
    }

    /**
     * Returns saved the TWINT order data.
     *
     * order data =
     * [
     *  status,
     *  pairing_uuid,
     *  order_uuid,
     *  twint_status,
     *  twint_status_reason
     * ]
     *
     * @param null $property
     * @param bool $cache
     * @return array|string|bool
     */
    public function get_order_data( $property = null, $cache = true )
    {
        return Twint_Data::get_order_data( $this->order_id, $property );
    }

    /**
     * Returns the order id of the system.
     *
     * @return string|int
     */
    public function get_order_id()
    {
        return $this->order_id;
    }

    /**
     * Returns the Order UUID
     *
     * @param bool $cache
     * @return string|false
     */
    public function get_order_uuid( $cache = true )
    {
        return $this->get_order_data( 'order_uuid' );
    }

    /**
     * Returns the merchant reference for the order.
     *
     * @param bool $reversal
     * @return string
     */
    public function get_or_create_merchant_reference( $reversal = false )
    {
        $reference = Twint_Data::get_merchant_reference( $this->order_id );

        if ( !empty( $reference ) ) {

            $reference = substr( $reference, 0, 50 );

            if ( $reversal ) {
                $reference = substr( $reference, 0, 35 );
                $reference .= '_' . uniqid();
            }
            return $reference;
        }

        $order = wc_get_order( $this->order_id );

        $reference = apply_filters( MAME_TW_PREFIX . '_merchant_reference_order_id', $this->order_id, $order );

        // Additional customer data.
        $add_billing_firstname = get_option( 'mame_tw_reference_include_billing_firstname' );

        if ( $add_billing_firstname && $add_billing_firstname == 'yes' ) {
            $bfn = WC_Helper::get( 'billing_first_name', $order );
            if ( !empty( $bfn ) ) {
                $reference .= '_' . $bfn;
            }
        }

        $add_billing_lastname = get_option( 'mame_tw_reference_include_billing_lastname' );
        if ( $add_billing_lastname && $add_billing_lastname == 'yes' ) {
            $bln = WC_Helper::get( 'billing_last_name', $order );
            if ( !empty( $bln ) ) {
                $reference .= '_' . $bln;
            }
        }

        $add_billing_company = get_option( 'mame_tw_reference_include_billing_company' );
        if ( $add_billing_company && $add_billing_company == 'yes' ) {
            $bc = WC_Helper::get( 'billing_company', $order );
            if ( !empty( $bc ) ) {
                $reference .= '_' . $bc;
            }
        }

        $add_shipping_firstname = get_option( 'mame_tw_reference_include_shipping_firstname' );
        if ( $add_shipping_firstname && $add_shipping_firstname == 'yes' ) {
            $sfn = WC_Helper::get( 'shipping_first_name', $order );
            if ( !empty( $sfn ) ) {
                $reference .= '_' . $sfn;
            }
        }

        $add_shipping_lastname = get_option( 'mame_tw_reference_include_shipping_lastname' );
        if ( $add_shipping_lastname && $add_shipping_lastname == 'yes' ) {
            $sln = WC_Helper::get( 'shipping_last_name', $order );
            if ( !empty( $sln ) ) {
                $reference .= '_' . $sln;
            }
        }

        $add_shipping_company = get_option( 'mame_tw_reference_include_shipping_company' );
        if ( $add_shipping_company && $add_shipping_company == 'yes' ) {
            $sc = WC_Helper::get( 'shipping_company', $order );
            if ( !empty( $sc ) ) {
                $reference .= '_' . $sc;
            }
        }

        $reference = substr( $reference, 0, 50 );

        // Length of uniqid is 13.
        $reference = substr( $reference, 0, 35 );
        $reference .= '_' . uniqid();

        return $reference;
    }

    /**
     * Saves the TWINT order data.
     *
     * @param $data
     * @return void
     */
    public function save_order_data( $data )
    {
        Twint_Data::save_order_data( $this->order_id, $data );
    }

    /**
     * Deletes all data in array $data.
     *
     * @param array $data
     */
    public function delete_order_data( $data )
    {
        Twint_Data::delete_order_data( $this->order_id, $data );
    }

    /**
     * Saves a transaction belonging to the current order.
     * [operation, reference, amount, fee]
     *
     * @param $data
     * @return mixed
     */
    public function save_transaction( $data )
    {
        Twint_Data::save_transaction( $this->order_id, $data );
    }

    /**
     * Returns all transactions for the order.
     *
     * @return array
     */
    public function get_transactions()
    {
        Twint_Data::get_transactions( $this->order_id );
    }

    /**
     * Returns the amount in CHF formatted to 2 decimals.
     *
     * @return string
     * @throws \Exception
     */
    public function get_formatted_amount()
    {
        return str_replace( ',', '.', $this->order->get_formatted_order_total() );
    }

    /**
     * Returns true if StartOrder was called for this order.
     *
     * @return bool
     */
    public function is_order_started()
    {
//        wp_cache_delete( $this->order_id, 'post_meta' );
        $order = wc_get_order( $this->order_id );

        if ( !$order ) {
            return false;
        }

        if ( true === $order->get_meta( '_' . MAME_TW_PREFIX . '_order_started', true ) ) {
            return true;
        }
        WC_Helper::update_order_meta( $order, '_' . MAME_TW_PREFIX . '_order_started', true );
        return false;
    }

    /**
     * Returns true if the current order is complete.
     *
     * @param bool $cache
     * @return bool
     */
    public function is_order_complete( $cache = false )
    {
        if ( !$cache ) {

            $order_id = $this->order->get_id();
            wp_cache_delete( $order_id, 'post_meta' );

            //update_postmeta_cache( [ $this->order->get_id() ] );
            return WC_Helper::is_order_complete( wc_get_order( $order_id ) );
        } else {
            return WC_Helper::is_order_complete( $this->order );
        }
    }

    /**
     * Returns true if the payment should be manually captured (PAYMENT_DEFERRED).
     *
     * @return bool
     */
    public function is_payment_deferred()
    {
        return $this->is_deferred;
    }

    /**
     * Returns the URL of the order confirmation page (successful order).
     *
     * @return string
     */
    public function get_order_received_url()
    {
        return $this->order->get_checkout_order_received_url();
    }

    /**
     * Returns the URL of the checkout page.
     *
     * @return string
     */
    public function get_checkout_url()
    {
        return wc_get_checkout_url();
    }

    /**
     * Returns the payment page of the checkout where the payment method can be selected.
     *
     * @return string
     */
    public function get_checkout_payment_url()
    {
        return $this->order->get_checkout_payment_url();
    }

    /**
     * Returns the URL to redirect to when an order is cancelled.
     *
     * @return string
     */
    public function get_cancel_order_url()
    {
        return $this->order->get_cancel_order_url();
    }


    /**
     * Returns the URL to redirect to when the timeout is reached.
     *
     * @return string
     */
    public function get_timeout_url()
    {
        return $this->order->get_checkout_payment_url();
    }

    /**
     * Returns the URL to redirect to when the payment failed.
     *
     * @return string
     */
    public function get_failure_url()
    {
        return $this->order->get_checkout_payment_url();
    }

    /**
     * Returns the total amount of the order.
     *
     * @return float
     */
    public function get_total()
    {
        return wc_format_decimal( $this->order->get_total(), 2 );
    }

    /**
     * Returns the directory of the current shop where TWINT files are stored.
     *
     * @return string
     */
    public function get_twint_files_dir()
    {
        return Twint_Helper::get_uploads_dir();
    }

    /**
     * Returns the timeout in seconds.
     *
     * @return int
     */
    public function get_timeout()
    {
        $timeout = intval( get_option( 'mametw_settings_timeout' ) );

        if ( !$timeout || ($timeout <= 0) || $timeout > MAME_TW_TIMEOUT ) {
            // Default timeout
            return MAME_TW_TIMEOUT;
        }

        return $timeout;
    }

    /**
     * Returns the timeout of the server to run scripts.
     *
     * @return mixed
     */
    public function get_max_execution_time()
    {
        $max_exec_time = MAME_TW_MAX_EXEC_TIME;

        if ( function_exists( 'ini_get' ) ) {
            $max_exec_time = ini_get( 'max_execution_time' );
        }

        return max( 0, $max_exec_time - 10 );
    }

    /**
     * Returns true if the allowed memory has exceeded.
     *
     * @return bool
     */
    public function has_memory_exceeded()
    {
        $memory_limit = '128M';

        if ( function_exists( 'ini_get' ) ) {
            $memory_limit = ini_get( 'memory_limit' );
        }

        if ( !$memory_limit || -1 === intval( $memory_limit ) ) {
            $memory_limit = '32000M';
        }

        $memory_limit   = intval( $memory_limit ) * 1024 * 1024 * 0.9; // 90 percent
        $current_memory = memory_get_usage( true );

        return $current_memory >= $memory_limit;
    }

    /**
     * Returns the interval in seconds for SOAP requests.
     *
     * @return int
     */
    public function get_soap_interval()
    {
        $interval = intval( get_option( 'mametw_settings_soap_request_interval' ) );

        if ( !$interval || ($interval <= 0) ) {
            // Default timeout
            return MAME_TW_SOAP_INTERVAL;
        }

        return $interval;
    }

    public function get_process_id()
    {
        return $this->process_id;
    }

    /**
     * Returns the URL for handling the webhook redirect response.
     *
     * @return mixed
     */
    public function get_webhook_redirect_url( $args = [] )
    {
        $args = array_merge( [
            'order_id'     => $this->order->get_id(),
            'order_number' => $this->order->get_order_number(),
            'uuid'         => $this->get_order_uuid(),
        ], $args );

        return add_query_arg( $args, \WC()->api_request_url( MAME_TW_PREFIX . '_webhook_redirect' ) );
    }

    /**
     * Returns the URL for handling the webhook cancel response.
     *
     * @param $args
     * @return mixed
     */
    public function get_webhook_cancel_url( $args = [] )
    {
        $args = array_merge( [
            'order_id'     => $this->order->get_id(),
            'order_number' => $this->order->get_order_number(),
            'uuid'         => $this->get_order_uuid(),
        ], $args );

        return add_query_arg( $args, \WC()->api_request_url( MAME_TW_PREFIX . '_webhook_cancel' ) );
    }

    public function get_webhook_order_update_notification_url()
    {
        return add_query_arg(
            [
                'order_id'     => $this->order->get_id(),
                'order_number' => $this->order->get_order_number(),
                'r'            => Twint_Data::get_merchant_reference( $this->order->get_id() ),
            ], \WC()->api_request_url( MAME_TW_PREFIX . '_webhook_oun' ) );
    }

    /**
     * @param $merchant_id
     * @return $this|iDataProvider
     */
    public function set_merchant( $merchant_id )
    {
        $this->merchant_uuid = $merchant_id;

        return $this;
    }

    public function get_merchant_uuid()
    {
        return $this->merchant_uuid;
    }
}