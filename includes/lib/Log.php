<?php

namespace Mame_Twint\lib;

use Mame_Twint\Globals;

/**
 * Class Log
 * @package Mame_Twint\lib
 */
class Log
{
    const LEVEL_ERROR = 'error';
    const LEVEL_ALL   = 'all';

    /**
     * @var string
     */
    private $log_level;

    /**
     * @var string
     */
    public $filename;

    /**
     * Log constructor.
     */
    private function __construct()
    {
        $this->log_level = MAME_TW_DEBUG_LOG;
        $this->filename  = Globals::get_log_file_path();
    }

    /**
     * @var Log
     */
    private static $instance;

    /**
     * Get single instance of this class.
     *
     * @return Log
     */
    public static function get_instance()
    {
        if ( empty( static::$instance ) ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function log( $is_event, $log )
    {
        if ( $is_event ) {
            $this->write_all( $log );
        } else {
            $this->write_error( $log );
        }
    }

    public function write_error( $log, $caller = null )
    {
        if ( !$caller ) {
            $backtrace = debug_backtrace();
            $caller    = array_shift( $backtrace );
        }

        $log = '[ERROR]' . $log;

        $this->write( $log, $this->log_level === static::LEVEL_ERROR || $this->log_level === static::LEVEL_ALL, $caller );
    }

    public function write_all( $log, $caller = null )
    {
        if ( !$caller ) {
            $backtrace = debug_backtrace();
            $caller    = array_shift( $backtrace );
        }
        $this->write( $log, $this->log_level === static::LEVEL_ALL, $caller );
    }

    /**
     * Writes a log to the plugins debug.log file if $condition is true. Optionally $file and $line can be passed to write file and line information.
     *
     * @param $log
     * @param bool $condition
     * @param $caller
     */
    public function write( $log, $condition = true, $caller = null )
    {
        if ( !$condition )
            return;

        if ( !$caller ) {
            $backtrace = debug_backtrace();
            $caller    = array_shift( $backtrace );
        }

        if ( is_array( $log ) || is_object( $log ) ) {
            error_log( $this->get_formatted_log( print_r( $log, true ), $caller ), 3, $this->filename );
        } else {
            error_log( $this->get_formatted_log( $log, $caller ), 3, $this->filename );
        }
    }

    public static function error( $log, $caller = null )
    {
        if ( !$caller ) {
            $backtrace = debug_backtrace();
            $caller    = array_shift( $backtrace );
        }
        static::get_instance()->write_error( $log, $caller );
    }

    public static function event( $log, $caller = null )
    {
        if ( !$caller ) {
            $backtrace = debug_backtrace();
            $caller    = array_shift( $backtrace );
        }
        static::get_instance()->write_all( $log, $caller );
    }

    /**
     * Formats the log information and adds time and file information.
     *
     * @param $log
     * @param $caller
     * @return string
     */
    private function get_formatted_log( $log, $caller )
    {
        $file = str_replace( plugin_dir_path( dirname( __FILE__ ) ), '', $caller[ 'file' ] );
        return '[' . date( "F j, Y, g:i:s a e O" ) . '][' . $file . ':' . $caller[ 'line' ] . ']' . $log . PHP_EOL;
    }
}