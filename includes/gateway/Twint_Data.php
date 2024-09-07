<?php

namespace Mame_Twint\gateway;

use Mame_Twint\lib\Log;
use Mame_Twint\lib\WC_Helper;

/**
 * Class Twint_Data
 * @package Mame_Twint\gateway
 */
class Twint_Data
{
    /**
     * Returns the TWINT order data either completely as an array or only one property if $index is defined.
     *
     * @param \WC_Order|int|string $order
     * @param null $index
     * @param bool $cache
     * @return mixed
     */
    public static function get_order_data( $order, $index = null )
    {
        $order_data = WC_Helper::get_order_meta( $order, '_mame_twint_order' );

        if ( $index === null ) {
            return $order_data;
        }

        if ( $order_data && isset( $order_data[ $index ] ) ) {
            return $order_data[ $index ];
        }

        // Legacy support.
        return WC_Helper::get_order_meta( $order, 'twint_' . $index );
    }

    /**
     * Saves the TWINT order data. Only saves data which are not empty.
     *
     * @param $order_id
     * @param array $args
     */
    public static function save_order_data( $order_id, $args )
    {
        $order      = wc_get_order( $order_id );
        $order_data = WC_Helper::get_order_meta( $order, '_mame_twint_order' );

        if ( !$order_data ) {
            $order_data = array();
        }

        foreach ( $args as $k => $v ) {
            if ( $v ) {
                $order_data[ $k ] = $v;
            }
        }

        WC_Helper::update_order_meta( $order, '_mame_twint_order', $order_data );
    }

    /**
     * Deletes all TWINT order data in array $args.
     *
     * @param $order_id
     * @param array $args
     */
    public static function delete_order_data( $order_id, $args )
    {
        $order      = wc_get_order( $order_id );
        $order_data = WC_Helper::get_order_meta( $order, '_mame_twint_order' );

        if ( !$order_data ) {
            return;
        }

        foreach ( $args as $k ) {
            $order_data[ $k ] = null;
            unset( $order_data[ $k ] );
        }

        Log::event( 'Deleted order data: ' . json_encode( $args ) );

        WC_Helper::update_order_meta( $order, '_mame_twint_order', $order_data );
    }

    /**
     * Saves a TWINT transaction in the array of transactions
     *
     * @param $order_id
     * @param $data
     */
    public static function save_transaction( $order_id, $data )
    {
        $order        = wc_get_order( $order_id );
        $transactions = WC_Helper::get_order_meta( $order, '_mame_twint_transactions' );

        if ( !$transactions ) {
            $transactions = array();
        }

        $data           = array_intersect_key( $data, [ 'operation' => '', 'reference' => '', 'amount' => '', 'fee' => '' ] );
        $transactions[] = $data;

        WC_Helper::update_order_meta( $order, '_mame_twint_transactions', $transactions );
    }

    /**
     * Returns the TWINT transactions.
     *
     * @param $order_id
     * @return mixed
     */
    public static function get_transactions( $order_id )
    {
        return WC_Helper::get_order_meta( $order_id, '_mame_twint_transactions' );
    }

    /**
     * Returns the merchant reference or creates a new one if no merchant reference is available.
     * Always creates a new reference for reversals.
     *
     * @param $order_id
     * @param bool $reversal
     * @return bool|string
     */
    public static function get_merchant_reference( $order_id )
    {
        return static::get_order_data( $order_id, 'reference' );
    }

    public static function clean_post_meta_cache( $post_id )
    {
        wp_cache_delete( $post_id, 'post_meta' );
    }
}