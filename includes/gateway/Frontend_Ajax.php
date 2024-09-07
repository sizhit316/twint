<?php

namespace Mame_Twint\gateway;

use Mame_Twint\services\Logger;
use Mame_Twint\TWINT;

class Frontend_Ajax
{
    const ACTION_BG_TASK = 'background_task';

    public static function init()
    {
        add_action( 'wp_ajax_mame_tw_frontend', [ new static(), 'ajax_handler' ] );
        add_action( 'wp_ajax_nopriv_mame_tw_frontend', [ new static(), 'ajax_handler' ] );
    }

    /**
     * The AJAX handler for orders.
     *
     * Checks the action from the order AJAX request and call the corresponding function.
     */
    public function ajax_handler()
    {
        $order_id = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
        $action   = $_POST[ 'mame_tw_action' ];

        if ( $action == static::ACTION_BG_TASK ) {

            Logger::log_event( 'ajax_handler:background_task', 'start', $order_id );

            $twint = TWINT::create_for_transaction( $order_id, MAME_TW_MERCHANT_UUID );
            if ( !$twint ) {
                Logger::log_error( 'ajax_handler:background_task', 'Could not create TWINT object.', $order_id );
                return;
            }

            $iteration = 0;
            do {
                $order_uuid = $twint->dataProvider->get_order_uuid( false );

                session_write_close();
                sleep( 2 );
                $iteration++;

            } while ( !$order_uuid && $iteration < 60 );

            if ( empty( $order_uuid ) ) {
                Logger::log_error( 'ajax_handler:background_task', 'No order UUID.', $order_id );
                return;
            }

            Logger::log_event( 'ajax_handler:background_task', 'Order UUID: ' . $order_uuid, $order_id );
            $twint->transactionHandler->background_task();
        }
    }
}