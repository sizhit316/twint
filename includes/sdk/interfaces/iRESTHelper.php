<?php

namespace Mame_Twint\interfaces;

interface iRESTHelper
{

    /**
     * Sends a GET request.
     *
     * @param $url
     * @return mixed
     */
    public static function send_get_request( $url );

    /**
     * Sends a POST request.
     *
     * @param $url
     * @param $data
     * @return bool|mixed
     */
    public static function send_post_request( $url, $data );

    /**
     * Redirect to $url.
     *
     * @param $url
     */
    public static function redirect( $url );
}