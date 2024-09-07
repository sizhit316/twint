<?php

namespace Mame_Twint\traits;

use Mame_Twint\interfaces\iDataProvider;
use Mame_Twint\TWINT;

trait tHasDataProvider
{
    /** @var iDataProvider */
    private $data_provider;

    private function log_event( $title, $event )
    {
        $backtrace = debug_backtrace();
        $caller    = array_shift( $backtrace );

        TWINT::$logger::log_event( $title, $event, $this->data_provider, $caller );
    }

    private function log_error( $title, $error )
    {
        $backtrace = debug_backtrace();
        $caller    = array_shift( $backtrace );

        TWINT::$logger::log_error( $title, $error, $this->data_provider, $caller );
    }
}