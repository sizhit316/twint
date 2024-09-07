<?php

namespace Mame_Twint\lib;

class Html
{
    public static function div( $content = '', $options = null )
    {
        return static::tag_with_content( 'div', $content, $options );
    }

    public static function a( $content = '', $link = '', $options = null, $target = "self" )
    {
        return static::tag_with_content( 'a', $content, $options, 'href="' . $link . '" target="' . $target . '"' );
    }

    public static function h1( $content = '', $options = null )
    {
        return static::tag_with_content( 'h1', $content, $options );
    }

    public static function h2( $content = '', $options = null )
    {
        return static::tag_with_content( 'h2', $content, $options );
    }

    public static function h3( $content = '', $options = null )
    {
        return static::tag_with_content( 'h3', $content, $options );
    }

    public static function h4( $content = '', $options = null )
    {
        return static::tag_with_content( 'h4', $content, $options );
    }

    public static function h5( $content = '', $options = null )
    {
        return static::tag_with_content( 'h5', $content, $options );
    }

    public static function h6( $content = '', $options = null )
    {
        return static::tag_with_content( 'h6', $content, $options );
    }

    public static function p( $content = '', $options = null )
    {
        return static::tag_with_content( 'p', $content, $options );
    }

    public static function textarea( $name, $value = null, $options = null )
    {
        return static::tag_with_content( 'textarea', $value ?: '', array_merge( [ 'name' => $name ], $options ) );
    }

    public static function img( $src, $options = null )
    {
        $options[ 'src' ] = $src;
        return static::open_tag( 'img', $options );
    }

    public static function button( $content = '', $options = null )
    {
        return static::tag_with_content( 'button', $content, $options );
    }

    public static function option( $key, $value, $options = null )
    {
        return static::tag_with_content( 'option', $value, $options, 'value="' . $key . '"' );
    }

    public static function input( $type, $name, $value = null, $options = null )
    {
        return '<input type="' . $type . '" name="' . $name . '" value="' . ( $value ?: '' ) . '"' . self::get_options_string( $options ) . '>';
    }

    public static function number( $name, $value = null, $options = null )
    {
        return static::input( 'number', $name, $value, $options );
    }

    public static function select( $name, $option_values, $options = null, $selected = null, $add_empty = false )
    {
        $content = '';
        if ( !empty( $option_values ) ) {

            if ( $add_empty ) {
                $content .= static::option( '', '--' );
            }
            foreach ( $option_values as $key => $value ) {
                $o = [];
                if ( $selected == $key ) {
                    $o[ 'selected' ] = 'selected';
                }
                $content .= static::option( $key, $value, $o );
            }
        }
        return static::tag_with_content( 'select', $content, $options, 'name="' . $name . '"' );
    }

    public static function multiselect( $name, $option_values, $options = null, $selected = [] )
    {
        $content = '';
        if ( !empty( $option_values ) ) {
            foreach ( $option_values as $key => $value ) {
                $o = [];
                if ( in_array( $key, $selected ) ) {
                    $o[ 'selected' ] = 'selected';
                }
                $content .= static::option( $key, $value, $o );
            }
        }
        return static::tag_with_content( 'select', $content, $options, 'name="' . $name . '" multiple' );
    }

    public static function select_with_string_keys( $name, $option_values, $options = null, $selected = null )
    {
        $content = '';
        if ( !empty( $option_values ) ) {
            foreach ( $option_values as $value ) {
                $o = [];
                if ( $selected == $value[ 'key' ] )
                    $o[ 'selected' ] = 'selected';
                $content .= static::option( $value[ 'key' ], $value[ 'value' ], $o );
            }
        }
        return static::tag_with_content( 'select', $content, $options, 'name="' . $name . '"' );
    }

    public static function label( $content = '', $for = null, $options = [] )
    {
        if ( $for ) {
            $options[ 'for' ] = $for;
        }

        return static::tag_with_content( 'label', $content, $options );
    }

    public static function checkbox( $label, $name, $value = null, $options = [], $checked = false )
    {
        if ( $checked ) {
            $options[ 'checked' ] = 'checked';
        }

        return static::label( static::input( 'checkbox', $name, $value, $options ) . $label, $name );
    }

    public static function checkbox_group( $checkboxes, $options = null, $checked = null )
    {
        $html = static::open_tag( 'div', $options );

        foreach ( $checkboxes as $checkbox ) {
            $o = [];
            if ( !empty( $checked ) && in_array( $checkbox[ 'value' ], $checked ) )
                $o[ 'checked' ] = 'checked';
            $html .= static::checkbox( $checkbox[ 'label' ], $checkbox[ 'name' ], ( isset( $checkbox[ 'value' ] ) ? $checkbox[ 'value' ] : null ), $o );
        }

        $html .= static::close_tag( 'div' );

        return $html;
    }

    public static function open_tag( $tag, $options = null )
    {
        return '<' . $tag . self::get_options_string( $options ) . '>';
    }

    public static function close_tag( $tag )
    {
        return '</' . $tag . '>';
    }

    public static function tag_with_content( $tag, $content = '', $options = null, $additional_attribute_str = '' )
    {
        $html = '<' . $tag . self::get_options_string( $options );
        $html .= ' ' . $additional_attribute_str . '>' . $content . '</' . $tag . '>';
        return $html;
    }

    public static function self_closing_tag( $tag, $options = null )
    {
        $html = '<' . $tag . self::get_options_string( $options ) . ' />';
        return $html;
    }

    private static function get_options_string( $options = null )
    {
        if ( !$options )
            return '';

        $result = ' ';
        foreach ( $options as $k => $v ) {
            $result .= $k . '="' . $v . '" ';
        }
        return $result;
    }
}