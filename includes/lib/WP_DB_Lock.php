<?php

namespace Mame_Twint\lib;

/**
 * Class WP_DB_Lock
 * @package Mame_Twint\lib
 * @since 2.2.0
 */
class WP_DB_Lock implements iDB_Lock
{
    const LOCK_PREFIX = '_' . MAME_TW_PREFIX . '_lock_';

    private $post_id;
    private $name;
    private $value;

    /**
     * DB_Lock constructor.
     *
     * @param $order_id
     * @param $name
     * @param $value
     * @since 3.1.0
     *
     */
    public function __construct( $order_id, $name, $value )
    {
        $this->post_id = $order_id;
        $this->name    = $name;
        $this->value   = $value;
    }

    /**
     * Acquires the lock and returns it. Returns null if lock could not be acquired.
     *
     * @return WP_DB_Lock|null
     * @since 3.1.0
     *
     */
    public function acquire()
    {
        wp_cache_delete( $this->post_id, 'post_meta' );

        // Check if in db
        if ( false !== update_post_meta( $this->post_id, static::LOCK_PREFIX . $this->name, $this->value ) ) {
            update_post_meta( $this->post_id, static::LOCK_PREFIX . $this->name . '-timestamp', time() );
            return new static( $this->post_id, $this->name, $this->value );
        }

        return null;
    }

    /**
     * @since 3.1.0
     *
     * Releases the lock.
     */
    public function release()
    {
        delete_post_meta( $this->post_id, static::LOCK_PREFIX . $this->name, $this->value );
        delete_post_meta( $this->post_id, static::LOCK_PREFIX . $this->name . '-timestamp' );
    }

    /**
     * @return mixed
     *
     * @since 4.0.2
     */
    public function is_free()
    {
        return !get_post_meta( $this->post_id, static::LOCK_PREFIX . $this->name, true );
    }

    /**
     * @return mixed
     *
     * @since 5.0.0
     */
    public function get_timestamp()
    {
        return get_post_meta( $this->post_id, static::LOCK_PREFIX . $this->name . '-timestamp', true );
    }
}