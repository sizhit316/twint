<?php

namespace Mame_Twint\lib;

use Mame_Twint\services\Logger;

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Helper
{
    /**
     * Checks if WooCommerce higher or equal $version is installed.
     *
     * @param $version
     * @return mixed
     */
    public static function is_woocommerce_version_up( $version )
    {
        return version_compare( WOOCOMMERCE_VERSION, $version, '>=' );
    }

    /**
     * @param $property
     * @param $order
     * @return mixed
     */
    public static function get( $property, $order )
    {
        if ( is_numeric( $order ) || is_string( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( 'id' == strtolower( $property ) ) {
            $func = 'get_id';
        } else {
            $func = 'get_' . $property;
        }
        return $order->$func();
    }

    /**
     * @param $order
     * @return mixed
     */
    public static function get_order_status( $order )
    {
        if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>=' ) ) {
            return $order->get_status();
        } else {
            return $order->status;
        }
    }

    /**
     * @param $order
     * @return bool
     */
    public static function is_order_complete( $order )
    {
        $wc_order_status = static::get_order_status( $order );
        if ( $wc_order_status == 'processing' || $wc_order_status == 'completed' ) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the WC order only contains virtual products.
     *
     * @param $order
     * @return bool
     */
    public static function is_virtual_order( $order )
    {
        $items = $order->get_items();

        if ( 0 >= count( $items ) ) {
            return false;
        }

        foreach ( $items as $item ) {

            if ( '0' != $item[ 'variation_id' ] ) {
                $product = new \WC_Product_Variation( $item[ 'variation_id' ] );
            } else {
                $product = new \WC_Product( $item[ 'product_id' ] );
            }

            if ( !$product->is_virtual() ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Updates the order status after a successful payment.
     *
     * @param \WC_Order $order
     * @param bool $is_deferred
     */
    public static function update_order_successful( $order, $is_deferred = false )
    {
        if ( $is_deferred ) {
            $deferred_status = get_option( 'mametw_settings_deferred_order_status' );
            $order_status    = !empty( $deferred_status ) ? $deferred_status : MAME_TW_DEFAULT_STATUS_DEFERRED;
        } elseif ( static::is_virtual_order( $order ) ) {
            $virtual_status = get_option( 'mametw_settings_order_status_virtual' );
            $order_status   = !empty( $virtual_status ) ? $virtual_status : MAME_TW_DEFAULT_STATUS_VIRTUAL;
        } else {
            $non_virtual_status = get_option( 'mametw_settings_order_status_non_virtual' );
            $order_status       = !empty( $non_virtual_status ) ? $non_virtual_status : MAME_TW_DEFAULT_STATUS_ORDER;
        }

        Logger::log_event( 'update_order_successful', sprintf( 'Updated order to status %1$s', $order_status ), $order->get_id() );

        switch ( $order_status ) {

            case 'processing':
                $order->payment_complete();
                break;

            case 'completed':
                $order->payment_complete();
                $order->update_status( 'completed' );
                break;

            case 'on-hold':
            default:
                $order->update_status( 'on-hold' );
                break;
        }

        global $woocommerce;
        $cart = $woocommerce->cart;
        if ( $cart ) {
            $cart->empty_cart();
        }
    }

    /**
     * @param \WC_Order|string|int $order
     * @param string $key
     * @param mixed $single
     * @return mixed
     * @since 6.0.0
     */
    public static function get_order_meta( $order, $key, $single = true )
    {
        if ( !is_object( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( !$order ) {
            return $order;
        }

        return $order->get_meta( $key, $single );
    }

    /**
     * @param \WC_Order|string|int $order
     * @param string $key
     * @param mixed $value
     * @return mixed
     * @since 6.0.0
     */
    public static function update_order_meta( $order, $key, $value, $save = true )
    {
        if ( !is_object( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( !$order ) {
            return $order;
        }

        $order->update_meta_data( $key, $value );

        if ( $save ) {
            return $order->save();
        }

        return true;
    }

    public static function delete_order_meta( $order, $key, $value = '', $save = true )
    {
        if ( !is_object( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( !$order ) {
            return $order;
        }

        if ( !empty( $value ) && $value != $order->get_meta( $key, true ) ) {
            return false;
        }

        Log::event( 'Deleting meta ' . $key );
        $order->delete_meta_data( $key, $value );

        if ( $save ) {
            return $order->save();
        }

        return true;
    }

    /**
     * Returns the correct screen id for WC pages. HPOS-ready and backward-compatible.
     *
     * @param $screen
     * @return mixed|string
     */
    public static function get_wc_screen( $screen = 'shop-order' )
    {
        if ( !class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
            return $screen;
        }

        return wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( $screen )
            : str_replace( '-', '_', $screen );
    }
}