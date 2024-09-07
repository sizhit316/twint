<?php

namespace Mame_Twint\services;

use Mame_Twint\interfaces\iDataProvider;
use Mame_Twint\interfaces\iLogger;
use Mame_Twint\lib\Log;

/**
 * Class Logger
 * @package Mame_Twint
 */
class Logger implements iLogger
{
    /**
     * Logs an error.
     *
     * @param $title
     * @param string|array $error
     * @param DataProvider|null $data_provider
     * @param null $caller
     */
    public static function log_error( $title, $error, $data_provider = null, $caller = null )
    {
        if ( !$caller ) {
            $backtrace = debug_backtrace();
            $caller    = array_shift( $backtrace );
        }

        Log::error( static::get_formatted_log( $title, $error, $data_provider ), $caller );
    }

    /**
     * Logs other events.
     *
     * @param $title
     * @param string|array $event
     * @param DataProvider|int|string|null $data_provider
     * @param null $caller
     */
    public static function log_event( $title, $event, $data_provider = null, $caller = null )
    {
        if ( !$caller ) {
            $backtrace = debug_backtrace();
            $caller    = array_shift( $backtrace );
        }

        Log::event( static::get_formatted_log( $title, $event, $data_provider ), $caller );
    }

    /**
     * @param $title
     * @param $content
     * @param $data_provider
     * @return string
     */
    private static function get_formatted_log( $title, $content, $data_provider = null )
    {
        if ( !is_string( $content ) ) {
            $content = json_encode( $content );
        }

        $log = sprintf( '%1$s: %2$s', $title, $content );

        if ( $data_provider instanceof iDataProvider ) {
            $log = sprintf( '[order_id=%1$s][pid=%2$s]', $data_provider->get_order_id(), $data_provider->get_process_id() ) . $log;
        } elseif ( is_string( $data_provider ) || is_int( $data_provider ) ) {
            $log = sprintf( '[order_id=%1$s]', $data_provider ) . $log;
        }

        return $log;
    }
}