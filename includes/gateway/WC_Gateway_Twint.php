<?php

namespace Mame_Twint\gateway;

use Mame_Twint\lib\Log;
use Mame_Twint\lib\WC_Helper;
use Mame_Twint\TWINT;
use Mame_Twint\services\DataProvider;
use Mame_Twint\services\Logger;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The TWINT payment gateway
 */
class WC_Gateway_Twint extends \WC_Payment_Gateway
{
    /**
     * WC_Gateway_Twint constructor.
     */
    public function __construct()
    {
        // Standard gateway fields.
        $this->id                 = 'mame_twint';
        $this->method_title       = 'TWINT';
        $this->method_description = __( 'Payment gateway for payments with TWINT.', 'mametwint' );
        $this->has_fields         = false;
        $this->supports           = array(
            'products',
            'refunds',
        );

        // Default texts (settings).
        $this->default_enabled     = __( 'Enable TWINT payment gateway.', 'mametwint' );
        $this->default_title       = __( 'TWINT', 'mametwint' );
        $this->default_description = __( 'Pay with your smartphone', 'mametwint' );

        // Initialize settings.
        $this->init_form_fields();
        $this->init_settings();

        // Gateway settings.
        $this->title        = $this->settings[ 'title' ];
        $this->description  = $this->settings[ 'description' ];
        $this->instructions = isset( $this->settings[ 'instructions' ] ) ? $this->settings[ 'instructions' ] : '';
        $this->icon         = MAME_TW_PLUGIN_URL . 'assets/images/twint_logo_q.svg';

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options'
            ) );
        } else {
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
        }

        add_action( 'woocommerce_receipt_' . $this->id, array( &$this, 'receipt_page' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

        add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
    }

    public function scripts()
    {
        if ( is_checkout() ) {
            wp_enqueue_style( 'twint-frontend', plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'assets/css/twint-frontend.css', [], MAME_TW_PLUGIN_VERSION );

            $bg_ajax = get_option( 'mame_tw_asnyc_bg_task_request_ajax' ) ?: MAME_TW_DEFAULT_ASYNC_REQUEST_AJAX;
            if ( $bg_ajax === 'yes' ) {
                wp_register_script( MAME_TW_PREFIX . '-frontend', MAME_TW_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], MAME_TW_PLUGIN_VERSION );
                wp_localize_script( MAME_TW_PREFIX . '-frontend', 'mameTwFrontend', array(
                    'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                    'texts'          => [
                        'overlayAlt'     => __( 'Redirecting to TWINT', 'mametwint' ),
                        'overlayMessage' => __( 'You will be redirected to the payment page of TWINT.', 'mametwint' ),
                    ],
                    'overlayIconUrl' => MAME_TW_PLUGIN_URL . 'assets/images/twint_logo_q.svg',
                    'checkoutUrl'    => wc_get_checkout_url(),
                ) );

                wp_enqueue_script( MAME_TW_PREFIX . '-frontend' );
            }
        }
    }

    /**
     * Initialize settings fields
     */
    function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'      => array(
                'title'   => __( 'Enable/disable', 'mametwint' ),
                'type'    => 'checkbox',
                'label'   => $this->default_enabled,
                'default' => 'no'
            ),
            'title'        => array(
                'title'       => __( 'Title:', 'mametwint' ),
                'type'        => 'text',
                'description' => __( 'The title of the payment method shown in the fronted.', 'mametwint' ),
                'default'     => $this->default_title
            ),
            'description'  => array(
                'title'       => __( 'Description:', 'mametwint' ),
                'type'        => 'textarea',
                'description' => __( 'The description of the payment method which will be visible at the checkout page.', 'mametwint' ),
                'default'     => $this->default_description
            ),
            'instructions' => array(
                'title'       => __( 'Instructions', 'woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Payment gateway settings.
     */
    public function admin_options()
    {
        echo '<h3>' . __( 'TWINT Payment Gateway', 'mametwint' ) . '</h3>';
        echo '<p>' . __( 'Payment gateway for online payments by TWINT', 'mametwint' ) . '</p>';

        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * TWINT payment fields.
     */
    function payment_fields()
    {
        if ( $this->description ) {
            echo wpautop( wptexturize( $this->description ) );
        }
    }

    public function receipt_page( $order_id )
    {
        $bg_ajax = get_option( 'mame_tw_asnyc_bg_task_request_ajax' ) ?: MAME_TW_DEFAULT_ASYNC_REQUEST_AJAX;

        if ( $bg_ajax !== 'yes' ) {
            parent::receipt_page( $order_id );
            return;

        }

        if ( $url = $this->start_twint_payment_process( $order_id ) ) {

            $this->schedule_background_task( $order_id );

            $error_url = add_query_arg( [
                'order_id' => $order_id,
            ], \WC()->api_request_url( MAME_TW_PREFIX . '_webhook_redirect' ) );

            $args = [
                'redirect_url' => $url,
                'error_url'    => $error_url,
                'order_id'     => $order_id
            ];

            mame_twint_get_template( 'payment-page.php', $args );
        } else {
            $order = wc_get_order( $order_id );
            wp_redirect( $order->get_checkout_payment_url() );
            die();
        }
    }

    /**
     * Order received page output.
     */
    public function thankyou_page()
    {
        if ( $this->instructions ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
        }
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param \WC_Order $order Order object.
     * @param bool $sent_to_admin Sent to admin.
     * @param bool $plain_text Email format: plain text or HTML.
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false )
    {
        if ( $this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method() ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
        }
    }

    /**
     * Start the TWINT payment process.
     *
     * @param int $order_id
     * @global $woocommerce
     *
     */
    public function start_twint_payment_process( $order_id )
    {
        Logger::log_event( 'Start payment', 'Payment process started', DataProvider::create()->set_order( $order_id ) );

        $order = wc_get_order( $order_id );

        // Set flag: Initiated with TWINT.
        WC_Helper::update_order_meta( $order, '_' . MAME_TW_PREFIX . '_payment_initiated', time() );

        $twint = TWINT::create_for_transaction( $order_id, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return false;
        }

        return $twint->transactionHandler->start_order();
    }

    /**
     * Handles the redirect from TWINT via webhook.
     *
     * @return void
     */
    public static function handle_webhook_redirect()
    {
        if ( !isset( $_GET[ 'order_id' ] ) ) {
            Logger::log_error( 'Webhook error', 'No order ID provided' );
            return;
        }

        $order_id = $_GET[ 'order_id' ];

        Logger::log_event( 'handle_webhook_redirect', 'start', $order_id );

        $twint = TWINT::create_for_transaction( $order_id, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {

            $order = wc_get_order( $order_id );
            wc_add_notice( __( 'TWINT is not available at the moment. Please try another payment method or contact the website administrator.', 'mametwint' ), 'error' );
            wp_redirect( $order->get_checkout_payment_url() );
            die();
        }

        $twint->transactionHandler->handle_webhook_response( $_GET[ 'uuid' ] ?? null );
    }

    /**
     * Handles the cancel request from TWINT via webhook.
     *
     * @return void
     */
    public static function handle_webhook_cancel()
    {
        if ( !isset( $_GET[ 'order_id' ] ) ) {
            Logger::log_error( 'handle_webhook_cancel', 'No order ID provided' );
            return;
        }

        $order_id = $_GET[ 'order_id' ];

        Logger::log_event( 'handle_webhook_cancel', 'start', $order_id );

        $twint = TWINT::create_for_transaction( $order_id, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {

            $order = wc_get_order( $order_id );
            wc_add_notice( __( 'TWINT is not available at the moment. Please try another payment method or contact the website administrator.', 'mametwint' ), 'error' );
            wp_redirect( $order->get_checkout_payment_url() );
            die();
        }

        $twint->transactionHandler->handle_webhook_cancel( $_GET[ 'uuid' ] ?? null );
    }

    /**
     * Runs the background task to check the TWINT order.
     *
     * @param $order_id
     * @param $order_uuid
     * @return void
     */
    public static function monitor_order_background_task( $order_id, $order_uuid, $time )
    {
        Logger::log_event( 'monitor_order_background_task', 'start', $order_id );

        $twint = TWINT::create_for_transaction( $order_id, MAME_TW_MERCHANT_UUID );
        if ( !$twint ) {
            Logger::log_error( 'monitor_order_background_task', 'Could not create TWINT object.', $order_id );
            return;
        }

        $saved_order_uuid = $twint->dataProvider->get_order_uuid( false );

        $iteration = 0;
        while ( !$saved_order_uuid && $iteration < 5 ) {
            $saved_order_uuid = $twint->dataProvider->get_order_uuid( false );

            Logger::log_error( 'monitor_order_background_task', 'No order UUID', $order_id );
            session_write_close();
            sleep( 2 );
            $iteration++;
        }

        if ( empty( $order_uuid ) || empty( $saved_order_uuid ) || $saved_order_uuid != $order_uuid ) {
            Logger::log_error( 'monitor_order_background_task', sprintf( 'Wrong order UUID: expected %1$s, given %2$s.', $saved_order_uuid, $order_uuid ), $order_id );
            return;
        }

        $twint->transactionHandler->background_task( $time );
    }

    /**
     * Process the payment and return the result.
     *
     * @param $order_id
     *
     * @return array
     */
    function process_payment( $order_id )
    {
        $bg_ajax = get_option( 'mame_tw_asnyc_bg_task_request_ajax' ) ?: MAME_TW_DEFAULT_ASYNC_REQUEST_AJAX;
        if ( $bg_ajax === 'yes' ) {
            $order = wc_get_order( $order_id );

            return [
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true ),
            ];
        }

        if ( $url = $this->start_twint_payment_process( $order_id ) ) {

            $this->schedule_background_task( $order_id );

            return [
                'result'   => 'success',
                'redirect' => $url,
            ];
        }
    }

    /**
     * Handles refunds. Refunds with $amount higher than the paid amount are possible.
     *
     * @param $order_id
     * @param null $amount
     * @param string $reason
     * @return bool
     */
    public function process_refund( $order_id, $amount = null, $reason = '' )
    {
        $twint = TWINT::create_for_admin( $order_id, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return false;
        }

        $amount = wc_format_decimal( $amount, 2 );

        $transaction_data = $twint->transactionHandler->create_refund( $amount );

        if ( !$transaction_data ) {
            return false;
        }

        return true;
    }

    private function schedule_background_task( $order_id )
    {
        $bg_cron = get_option( 'mame_tw_asnyc_bg_task_request_cron' ) ?: MAME_TW_DEFAULT_ASYNC_REQUEST_CRON;
        if ( $bg_cron == 'yes' ) {
            if ( !wp_next_scheduled( MAME_TW_PREFIX . '_bg_cron_task_single', [ $order_id ] ) ) {
                wp_schedule_single_event( time() + 300, MAME_TW_PREFIX . '_bg_cron_task_single', [ $order_id ] );
            }
        }
    }

    public static function run_scheduled_background_task( $order_id )
    {
        Logger::log_event( 'run_scheduled_background_task', 'start', $order_id );

        $twint = TWINT::create_for_transaction( $order_id, MAME_TW_MERCHANT_UUID );
        if ( !$twint ) {
            Logger::log_error( 'run_scheduled_background_task', 'Could not create TWINT object.', $order_id );
            return;
        }

        $order_uuid = $twint->dataProvider->get_order_uuid();

        if ( empty( $order_uuid ) ) {
            Logger::log_error( 'run_scheduled_background_task', 'No order UUID.' );
            return;
        }

        $twint->transactionHandler->background_task();
    }
}