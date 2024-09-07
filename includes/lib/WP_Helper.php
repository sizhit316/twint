<?php

namespace Mame_Twint\lib;

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WP_Helper extends Helper
{
    public static function image_upload_field( $options_name, $name, $width, $height, $default_image )
    {
        $options = get_option( $options_name );

        if ( !empty( $options[ $name ] ) ) {
            $image_attributes = wp_get_attachment_image_src( $options[ $name ], array( $width, $height ) );
            $src              = $image_attributes[ 0 ];
            $value            = $options[ $name ];
        } else {
            $src   = $default_image;
            $value = '';
        }

        $text = __( 'Upload', 'dhuett' );

        return '
        <div class="upload">
            <img data-src="' . $default_image . '" src="' . $src . '" width="' . $width . '" height="' . $height . '" />
            <div>
                <input type="hidden" name="' . $options_name . '[' . $name . ']" id="' . $options_name . '[' . $name . ']" value="' . $value . '" />
                <button type="submit" class="upload_image_button button">' . $text . '</button>
                <button type="submit" class="remove_image_button button">&times;</button>
            </div>
        </div>
    ';
    }

    public static function get_local_formatted_datetime( $timestamp, $format = null )
    {
        $dt = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), 'Y-m-d H:i:s' );
        return date_i18n( $format ?: get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ), strtotime( $dt ) );
    }

    public static function get_formatted_post_datetime( $post_id, $format = null )
    {
        return static::get_local_formatted_datetime( function_exists( 'get_post_timestamp' ) ? get_post_timestamp( $post_id ) : get_post_time( 'U', false, $post_id ), $format );
    }

    public static function get_uncached_option($option){
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );

        $value = false;
        if ( is_object( $row ) ) {
            $value = $row->option_value;
        }

        return $value;
    }

    public static function get_blog_option( $blog_id, $option, $default = false )
    {
        if ( function_exists( 'get_blog_option' ) ) {
            return get_blog_option( $blog_id, $option, $default );
        }

        return get_option( $option, $default );
    }
}