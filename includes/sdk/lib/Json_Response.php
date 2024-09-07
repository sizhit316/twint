<?php

namespace Mame_Twint\lib;

/**
 * Class Json_Response
 * @package Mame_Twint\lib
 */
class Json_Response
{
    protected $response;

    public function __construct()
    {
        $this->response = [];
    }

    public function status( $status )
    {
        $this->response[ 'status' ] = $status;
        return $this;
    }

    public function add_attribute( $key, $value )
    {
        $this->response[ $key ] = $value;
        return $this;
    }

    public function replace( $element, $replacement )
    {
        $this->response [ 'replace' ] = [ 'element' => $element, 'with' => $replacement ];
        return $this;
    }

    public function html( $element, $content )
    {
        $this->response [ 'html' ][] = [ 'element' => $element, 'content' => $content ];
        return $this;
    }

    public function text( $element, $text )
    {
        $this->response[ 'text' ][] = [ 'element' => $element, 'text' => $text ];
        return $this;
    }

    public function append( $to, $element )
    {
        $this->response [ 'append' ] = [ 'to' => $to, 'element' => $element ];
        return $this;
    }

    public function attr( $elem, $attr, $val )
    {
        $this->response[ 'attr' ][] = [ 'elem' => $elem, 'attr' => $attr, 'val' => $val ];
        return $this;
    }

    public function data( $elem, $attr, $val )
    {
        $this->response[ 'data' ][] = [ 'elem' => $elem, 'attr' => $attr, 'val' => $val ];
        return $this;
    }

    public function hide( $elem )
    {
        $this->response[ 'hide' ][] = $elem;
        return $this;
    }

    public function show( $elem, $scroll_to = null )
    {
        $this->response[ 'show' ][]       = $elem;
        $this->response[ 'showScrollTo' ] = $scroll_to;
        return $this;
    }

    public function add_class( $elem, $class )
    {
        $this->response[ 'addClass' ][] = [ 'elem' => $elem, 'class' => $class ];
        return $this;
    }

    public function remove_class( $elem, $class )
    {
        $this->response[ 'removeClass' ][] = [ 'elem' => $elem, 'class' => $class ];
        return $this;
    }

    public function redirect( $location )
    {
        $this->response[ 'redirect' ][] = $location;
        return $this;
    }

    public function errors( $container, $errors )
    {
        $this->response[ 'errors' ][] = [ 'container' => $container, 'errors' => $errors ];
        return $this;
    }

    public function after( $element, $insert )
    {
        $this->response[ 'after' ][] = [ 'element' => $element, 'insert' => $insert ];
        return $this;
    }

    public function scroll_to( $elem, $offset = 0 )
    {
        $this->response[ 'scrollTo' ][ 'target' ] = $elem;
        $this->response[ 'scrollTo' ][ 'offset' ] = $offset;
        return $this;
    }

    public function respond()
    {
        ob_clean();
        echo json_encode( $this->response );
        die();
    }

}