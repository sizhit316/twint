<?php

namespace Mame_Twint\lib;

class Twint_Json_Response extends Json_Response
{
    public function enroll_response( $status, $error )
    {
        $this->status( $status )->message( $error );
        return $this;
    }

    public function message( $message )
    {
        $this->add_attribute( 'message', $message );
        return $this;
    }

    public function order_uuid( $order_uuid )
    {
        $this->add_attribute( 'order_uuid', $order_uuid );
        return $this;
    }

    public function redirect_url( $redirect_url )
    {
        $this->add_attribute( 'redirect_url', $redirect_url );
        return $this;
    }

    public function reason( $reason )
    {
        $this->add_attribute( 'reason', $reason );
        return $this;
    }

    public function respond()
    {
        ob_clean();
        echo html_entity_decode( json_encode( $this->response ) );
        exit;
    }
}