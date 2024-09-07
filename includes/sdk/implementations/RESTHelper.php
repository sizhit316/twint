<?php

namespace Mame_Twint\services;

use Mame_Twint\interfaces\iRESTHelper;

class RESTHelper implements iRESTHelper
{
    /**
     * Sends a GET request.
     *
     * @param string $url
     * @return mixed
     */
    public static function send_get_request( $url )
    {
        return wp_remote_get( $url );
    }

    /**
     * Sends a POST request.
     *
     * @param string $url
     * @param array $data
     * @return bool|mixed
     */
    public static function send_post_request( $url, $data )
    {
        return wp_remote_post( $url, $data );
    }

    /**
     * @param $url
     */
    public static function redirect( $url )
    {
        wp_redirect( $url );
        die();
    }
}