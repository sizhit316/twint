<?php

namespace Mame_Twint\admin;

use Mame_Twint\services\Logger;
use Mame_Twint\vendor\wpbackgroundprocessing\classes\Background_Process;

/**
 * Class Delete_Logs_Background_Process
 * @package Mame_Twint\admin
 */
class Delete_Logs_Background_Process extends Background_Process
{
    protected $action = MAME_TW_PREFIX . '_delete_logs_background_process';

    /**
     * Task
     *
     * Override this method to perform any actions required on each
     * queue item. Return the modified item for further processing
     * in the next pass through. Or, return false to remove the
     * item from the queue.
     *
     * @param mixed $item Queue item to iterate over.
     *
     * @return mixed
     */
    protected function task( $item )
    {
        $query_args = array(
            // 'post_parent'    => 0,
            'post_type'      => 'wp_log',
            'posts_per_page' => 100,
            'post_status'    => 'publish'
        );

        $logs = get_posts( $query_args );

        Logger::log_event( 'Deleting logs', count( $logs ) );

        foreach ( $logs as $l ) {
            $id = is_int( $l ) ? $l : $l->ID;
            wp_delete_post( $id, true );
        }

        return false;
    }

    /**
     * Complete
     *
     * Override if applicable, but ensure that the below actions are
     * performed, or, call parent::complete().
     */
    protected function complete()
    {
        parent::complete();
    }
}