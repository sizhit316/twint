<?php

namespace Mame_Twint\admin;

use Mame_Twint\gateway\Twint_Data;
use Mame_Twint\lib\WC_Helper;
use Mame_Twint\services\TransactionHandler;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shop order metabox to display the TWINT order status.
 */
class Metabox
{
    public static function init()
    {
        add_action( 'add_meta_boxes', __CLASS__ . '::add_meta_box' );
    }

    /**
     * Add meta box for order status.
     */
    public static function add_meta_box()
    {
        if ( !MAME_WC_ACTIVE ) {
            return;
        }

        $id    = get_the_ID();
        $order = wc_get_order( $id );

        if ( !$order || (WC_Helper::get( 'payment_method', $order ) !== 'mame_twint' && !WC_Helper::get_order_meta( $order, '_' . MAME_TW_PREFIX . '_payment_initiated', true )) ) {
            return;
        }

        add_meta_box(
            'twint_meta_box',
            __( 'TWINT Order Status', 'mametwint' ),
            __CLASS__ . '::create_order_metabox',
            WC_Helper::get_wc_screen(),
            'normal',
            'default'
        );
    }

    /**
     * The meta box for order status in database
     *
     * @param $post
     */
    public static function create_order_metabox( $post_or_order_object )
    {
        $order            = ($post_or_order_object instanceof \WP_Post) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
        $twint_order_data = Twint_Data::get_order_data( $order );

        // Legacy support
        if ( !$twint_order_data ) {
            $twint_order_data = array(
                'status'        => WC_Helper::get_order_meta( $order, 'twint_status' ),
                'status_reason' => WC_Helper::get_order_meta( $order, 'twint_status_reason' ),
                'order_uuid'    => WC_Helper::get_order_meta( $order, 'twint_order_uuid' ),
                'pairing_uuid'  => WC_Helper::get_order_meta( $order, 'twint_pairing_uuid' ),
            );
        }

        $transactions = Twint_Data::get_transactions( $order );
        ?>
        <table class="mametw-meta-table">
            <tr id="<?= MAME_TW_PREFIX ?>-order-status" class="mametw-table-row">
                <td class="mametw-table-title"><?php _e( 'Order status', 'mametwint' ); ?></td>
                <td class="mametw-table-data"><?= isset( $twint_order_data[ 'status' ] ) ? $twint_order_data[ 'status' ] : '' ?></td>
            </tr>
            <tr id="<?= MAME_TW_PREFIX ?>-status-reason" class="mametw-table-row">
                <td class="mametw-table-title"><?php _e( 'Status reason', 'mametwint' ); ?></td>
                <td class="mametw-table-data"><?= isset( $twint_order_data[ 'status_reason' ] ) ? $twint_order_data[ 'status_reason' ] : '' ?></td>
            </tr>
            <tr class="mametw-table-row">
                <td class="mametw-table-title"><?php _e( 'Order UUID', 'mametwint' ); ?></td>
                <td class="mametw-table-data"><?= isset( $twint_order_data[ 'order_uuid' ] ) ? $twint_order_data[ 'order_uuid' ] : '' ?></td>
            </tr>
            <tr id="<?= MAME_TW_PREFIX ?>-transactions" class="mametw_table-row">
                <td class="mametw-table-title"><?php _e( 'Completed transactions', 'mametwint' ); ?></td>
                <td class="mametw-table-data">
                    <table>
                        <tbody>
                        <?php
                        if ( $transactions && !empty( $transactions ) ) {
                            ?>
                            <tr>
                                <th><?php _e( 'Type', 'mametwint' ) ?></th>
                                <th><?php _e( 'Amount', 'mametwint' ) ?></th>
                                <th><?php _e( 'Merchant Transaction Reference', 'mametwint' ) ?></th>
                                <th><?php _e( 'Fee', 'mametwint' ) ?></th>
                            </tr>
                            <?php
                            foreach ( $transactions as $transaction ) {
                                ?>
                                <tr>
                                    <td><?= isset( $transaction[ 'operation' ] ) ? $transaction[ 'operation' ] : (isset( $transaction[ 'type' ] ) ? $transaction[ 'type' ] : ''); ?></td>
                                    <td><?= $transaction[ 'amount' ] ?? '' ?></td>
                                    <td><?= isset( $transaction[ 'reference' ] ) ? $transaction[ 'reference' ] : (isset( $transaction[ 'merchant_transaction_reference' ] ) ? $transaction[ 'merchant_transaction_reference' ] : ''); ?></td>
                                    <td><?= $transaction[ 'fee' ] ?? '' ?></td>
                                </tr>
                                <?php
                            }
                            ?>

                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <div class="<?= MAME_TW_PREFIX ?>-buttons">

            <button id="<?= MAME_TW_PREFIX ?>-check-order-status-btn"
                    class="button button-primary status"
                    data-order-id="<?= $order->get_id() ?>"><?php _e( 'Check payment status', 'mametwint' ) ?></button><?php

            if ( isset( $twint_order_data[ 'order_uuid' ] ) && $twint_order_data[ 'status' ] == TransactionHandler::TWINT_STATUS_IN_PROGRESS && $twint_order_data[ 'status_reason' ] == TransactionHandler::TWINT_REASON_ORDER_CONFIRMATION_PENDING && !WC_Helper::get_order_meta( $order, MAME_TW_PREFIX . '_transaction_settled' ) ) { ?>
                <button id="<?= MAME_TW_PREFIX ?>-settle-transaction-btn"
                        class="button button-primary status"
                        data-order-id="<?= $order->get_id() ?>"><?php _e( 'Settle transaction', 'mametwint' ) ?></button>

                <button id="<?= MAME_TW_PREFIX ?>-cancel-transaction-btn"
                        class="button mame-admin-button red"
                        data-order-id="<?= $order->get_id() ?>"><?php _e( 'Cancel transaction', 'mametwint' ) ?></button>
            <?php } ?>

        </div>
        <?php

    }

}

