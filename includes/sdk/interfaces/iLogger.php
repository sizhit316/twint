<?php

namespace Mame_Twint\interfaces;

/**
 * Class Logger
 * @package Mame_Twint
 */
interface iLogger
{
    /**
     * Logs an error.
     *
     * @param $title
     * @param string|array $error
     * @param null $data_provider
     * @param null $caller
     */
    public static function log_error( $title, $error, $data_provider = null, $caller = null );

    /**
     * Logs other events.
     *
     * @param $title
     * @param string|array $event
     * @param null $data_provider
     * @param null $caller
     */
    public static function log_event( $title, $event, $data_provider = null, $caller = null );
}