<?php

namespace Mame_Twint;

class Globals
{
    public static function get_allowed_subdirs()
    {
        return [
            'locks',
            'logs',
            'temp'
        ];
    }

    public static function get_public_subdirs()
    {
        return [];
    }

    public static function get_options()
    {
        return get_option( MAME_TW_PREFIX . '_options_group' );
    }

    public static function update_options( $data )
    {
        $options                  = static::get_options();
        $options                  = array_merge( $options, $data );
        $options[ 'no_sanitize' ] = true;
        // Careful: password cslashes
        update_option( MAME_TW_PREFIX . '_options_group', $options );
    }

    public static function get_gateway_settings()
    {
        $payment_gateway = \WC()->payment_gateways->payment_gateways()[ 'mame_twint' ];
        return $payment_gateway->settings;
    }

    public static function get_license_options()
    {
        return get_option( MAME_TW_PREFIX . '_license_options' );
    }

    public static function update_license_options( $key, $status )
    {
        update_option( MAME_TW_PREFIX . '_license_options', [ 'license_key' => $key, 'license_status' => $status ] );
    }

    public static function get_plugin_uploads_dir_path()
    {
        return static::get_or_create_dir( MAME_TW_UPLOAD_DIR );
    }

    /**
     * Returns the plugin  uploads directory with optional subdirectory. Includes trailing slash.
     *
     * @param null $subdir
     * @return string
     */
    public static function get_upload_path( $subdir = null )
    {
        $dir = MAME_TW_UPLOAD_DIR;

        if ( $subdir ) {

            if ( in_array( $subdir, static::get_allowed_subdirs() ) ) {

                $dir .= $subdir . DIRECTORY_SEPARATOR;

            }
        }

        return $dir;
    }

    public static function create_dirs()
    {
        $upload_path = MAME_TW_UPLOAD_DIR;

        if ( !file_exists( $upload_path ) ) {
            wp_mkdir_p( $upload_path );
            file_put_contents( $upload_path . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all' );
        }

        $folders = static::get_allowed_subdirs();
        foreach ( $folders as $folder ) {
            $path = $upload_path . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;
            if ( !file_exists( $path ) ) {
                wp_mkdir_p( $path );
            }
        }
    }


    /**
     * Returns the path to the log file. Creates a new log file if log file is too large.
     *
     * @return string
     */
    public static function get_log_file_path()
    {
        $name = date( 'd-m-Y' );
        $path = static::get_upload_path( 'logs' ) . $name . '.log';

        if ( !file_exists( $path ) ) {
            file_put_contents( $path, '' );
        }

        return $path;
    }

    private static function get_or_create_dir( $path )
    {
        if ( !file_exists( $path ) ) {
            wp_mkdir_p( $path );
            file_put_contents( $path . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all' );
        }

        return $path;
    }
}