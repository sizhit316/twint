<?php

namespace Mame_Twint\lib;

class Helper
{
    /**
     * Maps an array of objects to an array $key => $value pairs where $key and $value are properties of the objects.
     *
     * @param $object_array
     * @param $key
     * @param $value
     * @return mixed
     */
    public static function object_array_map( $object_array, $key, $value )
    {
        return array_reduce( $object_array, function ( $c, $i ) use ( $key, $value ) {
            $c[ (string)( $i->{$key} ) ] = $i->{$value};
            return $c;
        }, [] );
    }

    /**
     * Maps an array of objects to an array $key => $value pairs where $key and $value are properties of the objects.
     *
     * Since PHP converts integer-like strings to integers we use the following structure to preserve the string keys:
     * [
     *      'key' => $key,
     *      'value' => $value,
     * ]
     *
     * @param $object_array
     * @param $key
     * @param $value
     * @return mixed
     */
    public static function object_array_map_string_keys( $object_array, $key, $value )
    {
        return array_reduce( $object_array, function ( $c, $i ) use ( $key, $value ) {
            $c[] = [ 'key' => $i->{$key}, 'value' => $i->{$value} ];
            return $c;
        }, [] );
    }

    /**
     * Creates an array of one property of an array of objects.
     *
     * @param $object_array
     * @param $key
     * @return mixed
     */
    public static function object_array_extract( $object_array, $key )
    {
        return array_reduce( $object_array, function ( $c, $i ) use ( $key ) {
            $c[] = $i->{$key};
            return $c;
        }, [] );
    }

    /**
     * Recursively converts an array to an object.
     *
     * @param $array
     * @param null $class
     * @return stdClass
     */
    public static function array_to_object( $array, $class = null )
    {
        $obj = $class ? new $class() : new stdClass;
        foreach ( $array as $k => $v ) {
            if ( strlen( $k ) ) {
                if ( is_array( $v ) ) {
                    $obj->{$k} = array_to_object( $v );
                } else {
                    $obj->{$k} = $v;
                }
            }
        }
        return $obj;
    }

    /**
     * Deep conversion of an object to an associative array. Removes null values.
     *
     * @param $object
     * @return array
     */
    public static function object_to_array( $object )
    {
        $array = (array)$object;
        foreach ( $array as $k => $v ) {
            if ( is_object( $v ) ) {
                $array[ $k ] = static::object_to_array( $v );
            }
            if ( $v == null )
                unset( $array[ $k ] );
            /*
                            if ( $v === null || $v === '' ) {
                                unset( $array[ $k ] );
                            }
            */
        }
        return $array;
    }

    /**
     * Recursively maps a function $fct to the keys of an array.
     *
     * @param $array
     * @param $fct
     * @return array
     */
    public static function array_map_keys( $array, $fct )
    {
        $result = [];
        foreach ( $array as $key => $value ) {

            $key = $fct( $key );

            if ( is_array( $value ) ) {
                $value = static::array_map_keys( $value, $fct );
            }

            $result[ $key ] = $value;

        }
        return $result;
    }

    /**
     * Converts CamelCase strings to underscore strings (camel_case).
     *
     * @param $str
     * @return string
     */
    public static function camelcase_to_underscore( $str )
    {
        return strtolower( preg_replace( [ '/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/' ], '$1_$2', $str ) );
    }

    /**
     * Converts underscore strings to CamelCase.
     *
     * @param $str
     * @param bool $capitalise_first_char
     * @return null|string|string[]
     */
    public static function underscore_to_camelcase( $str, $capitalise_first_char = false )
    {
        $str = strtolower( $str );
        if ( $capitalise_first_char ) {
            $str[ 0 ] = strtoupper( $str[ 0 ] );
        }

        $func = function ( $c ) {
            return strtoupper( $c[ 1 ] );
        };

        return preg_replace_callback( '/_([a-z])/', $func, $str );
    }

    /**
     * @param $string
     * @return mixed
     */
    public static function replace_special_chars( $string )
    {
        $search  = array( "Ä", "Ö", "Ü", "ä", "ö", "ü", "ß", "´" );
        $replace = array( "Ae", "Oe", "Ue", "ae", "oe", "ue", "ss", "" );
        return str_replace( $search, $replace, $string );
    }

    /**
     * Returns the name of a function.
     *
     * @param $callable
     * @return string
     */
    public static function get_callable_name( $callable )
    {
        if ( is_string( $callable ) ) {
            return trim( $callable );
        } else if ( is_array( $callable ) ) {
            if ( is_object( $callable[ 0 ] ) ) {
                return sprintf( "%s::%s", get_class( $callable[ 0 ] ), trim( $callable[ 1 ] ) );
            } else {
                return sprintf( "%s::%s", trim( $callable[ 0 ] ), trim( $callable[ 1 ] ) );
            }
        } else if ( $callable instanceof \Closure ) {
            return 'closure';
        } else {
            return 'unknown';
        }
    }

    public static function create_v4_uuid()
    {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

    /**
     * Returns true if $value is in v4 UUID format.
     *
     * @param string $value
     * @return bool
     */
    public static function is_v4_uuid( $value )
    {
        if ( !is_string( $value ) ) {
            return false;
        }

        return preg_match( '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $value ) === 1;
    }
}