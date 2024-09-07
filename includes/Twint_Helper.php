<?php

namespace Mame_Twint;

/**
 * Class Helper
 * @package Mame_Twint
 */
class Twint_Helper
{
    /**
     * Returns the Cash Register ID.
     * If empty the Cash Register ID is set to 'Register 1' and saved.
     *
     * @return string
     */
    public static function get_cash_register_id()
    {
        $cash_register_id = get_option( 'mametw_settings_registerid' );
        if ( empty( $cash_register_id ) ) {
            $cash_register_id = 'Register 1';
            update_option( 'mametw_settings_registerid', $cash_register_id );
        }

        return $cash_register_id;
    }

    /**
     * Returns the saved certificate password.
     *
     * @return string
     */
    public static function get_certificate_password()
    {
        return stripcslashes( get_option( 'mametw_settings_certpw' ) );
    }

    /**
     * Returns the Merchant UUID or false if it doesn't exist.
     *
     * @return string|bool
     */
    public static function get_merchant_uuid()
    {
        return apply_filters( MAME_TW_PREFIX . '_soap_merchant_uuid', get_option( 'mametw_settings_uuid' ) );
    }

    /**
     * Returns the connection timeout.
     *
     * @return int
     */
    public static function get_connection_timeout()
    {
        $connection_timeout = get_option( 'mametw_settings_connection_timeout' );
        if ( empty( $connection_timeout ) ) {
            $connection_timeout = MAME_TW_DEFAULT_CONNECTION_TIMEOUT;
        }

        return $connection_timeout;
    }

    /**
     * Returns the TWINT uploads directory path and creates it if it doesn't exist yet.
     * Includes trailing slash.
     *
     * @return string
     */
    public static function get_uploads_dir()
    {
        $twint_upload_dir = wp_upload_dir()[ 'basedir' ] . DIRECTORY_SEPARATOR . 'mame_twint' . DIRECTORY_SEPARATOR;

        if ( !file_exists( $twint_upload_dir ) ) {
            wp_mkdir_p( $twint_upload_dir );
            file_put_contents( $twint_upload_dir . '.htaccess', 'deny from all' );
        }

        return $twint_upload_dir;
    }

    /**
     * Returns the directory for lock files.
     * Includes trailing slash.
     *
     * @return string
     */
    public static function get_locks_dir()
    {
        $locks_dir = static::get_uploads_dir() . 'locks' . DIRECTORY_SEPARATOR;

        if ( !file_exists( $locks_dir ) ) {
            wp_mkdir_p( $locks_dir );
            file_put_contents( $locks_dir . '.htaccess', 'deny from all' );
        }

        return $locks_dir;
    }

    public static function save_certificate_file( $file )
    {
        $dir = Twint_Helper::get_uploads_dir();

        return file_put_contents( $dir . MAME_TW_CERTIFICATE_NAME, $file );
    }

    /**
     * Returns the path to the certificate file.
     *
     * @return string
     */
    public static function get_certificate_file_path()
    {
        return Twint_Helper::get_uploads_dir() . MAME_TW_CERTIFICATE_NAME;
    }
}