<?php

namespace Mame_Twint\gateway;

use Mame_Twint\lib\Log;
use Mame_Twint\vendor\async_task\WP_Async_Task;

class Twint_Transaction_Async_Task extends WP_Async_Task
{
    protected $action = 'twint_monitor_order';

    protected function prepare_data( $data )
    {
        $order_id   = $data[ 0 ];
        $order_uuid = $data[ 1 ];
        $time       = $data[ 2 ];

        return [ 'order_id' => $order_id, 'order_uuid' => $order_uuid, 'time' => $data[ 2 ] ];
    }

    protected function run_action()
    {
        Log::event( sprintf( 'Running async task %1$s', $this->action ) );
        do_action( "wp_async_$this->action", $_POST[ 'order_id' ], $_POST[ 'order_uuid' ], $_POST[ 'time' ] );
    }
}