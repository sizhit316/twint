<?php

namespace Mame_Twint\gateway;

use Mame_Twint\lib\Log;
use Mame_Twint\vendor\async_task\WP_Async_Task;

class Twint_Transaction_Async_Task_Immediate extends WP_Async_Task
{
    protected $action = 'twint_monitor_order_immediate';

    public function __construct( $auth_level = self::BOTH )
    {
        if ( empty( $this->action ) ) {
            throw new \Exception( 'Action not defined for class ' . __CLASS__ );
        }
        add_action( $this->action, array( $this, 'launch_now' ), (int)$this->priority, (int)$this->argument_count );
        if ( $auth_level & self::LOGGED_IN ) {
            add_action( "admin_post_wp_async_$this->action", array( $this, 'handle_postback' ) );
        }
        if ( $auth_level & self::LOGGED_OUT ) {
            add_action( "admin_post_nopriv_wp_async_$this->action", array( $this, 'handle_postback' ) );
        }
    }

    protected function prepare_data( $data )
    {
        $order_id   = $data[ 0 ];
        $order_uuid = $data[ 1 ];
        $time       = $data[ 2 ];

        return [ 'order_id' => $order_id, 'order_uuid' => $order_uuid, 'time' => $time ];
    }

    protected function run_action()
    {
        Log::event( sprintf( 'Running async task %1$s', $this->action ) );
        do_action( "wp_async_$this->action", $_POST[ 'order_id' ], $_POST[ 'order_uuid' ], $_POST[ 'time' ] );
    }

}