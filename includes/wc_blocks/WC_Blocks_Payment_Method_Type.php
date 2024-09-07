<?php

namespace Mame_Twint\wc_blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

class WC_Blocks_Payment_Method_Type extends AbstractPaymentMethodType
{
    /**
     * Payment method name defined by payment methods extending this class.
     *
     * @var string
     */
    protected $name = 'mame_twint';

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        global $woocommerce;

        $payment_gateways = $woocommerce->payment_gateways->payment_gateways();

        if ( isset( $payment_gateways[ $this->name ] ) ) {

            $gateway = $payment_gateways[ $this->name ];
            if ( !empty( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
                return true;
            }

        }

        return false;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
//        $dependencies = require_once( MAME_TW_PLUGIN_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'wc-blocks' . DIRECTORY_SEPARATOR . 'wc_blocks.asset.php' );

        global $woocommerce;
        $payment_gateways = $woocommerce->payment_gateways->payment_gateways();

        $gateway = null;
        if ( isset( $payment_gateways[ $this->name ] ) && $payment_gateways[ $this->name ]->is_available() ) {
            $gateway = $payment_gateways[ $this->name ];
        }

        wp_register_script( MAME_TW_PREFIX . '-wc-blocks', MAME_TW_PLUGIN_URL . 'assets/js/wc-blocks/wc_blocks.js', [ 'wc-blocks-registry', 'wp-element', 'wp-polyfill' ], MAME_TW_PLUGIN_VERSION );

        wp_localize_script( MAME_TW_PREFIX . '-wc-blocks', 'mameTwFrontend', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'prefix'       => MAME_TW_PREFIX,
            'userAgent'    => isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) ? strtolower( $_SERVER[ 'HTTP_USER_AGENT' ] ) : '',
            'httpXReqWith' => isset( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) ? 'true' : 'false',
            //'pairing_uuid' => $this->checkin_response->CheckInNotification->PairingUuid,
            'checkout_url' => wc_get_checkout_url(),
            'icon'         => MAME_TW_PLUGIN_URL . 'assets/images/twint_logo_q.svg',
            'title'        => $gateway ? $gateway->title : __( 'TWINT', 'mametwint' ),
            'description'  => $gateway ? $gateway->description : '',
        ) );

        return [ MAME_TW_PREFIX . '-wc-blocks' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
//        return [];
        return [
            'isAdmin' => is_admin(),
            'icon'    => MAME_TW_PLUGIN_URL . 'assets/images/twint_logo_q.svg',
        ];
    }

}