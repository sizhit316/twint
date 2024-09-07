<?php

namespace Mame_Twint\lib;

/**
 * Interface iDB_Lock
 * @package Mame_Twint\lib
 * @since 2.2.0
 */
interface iDB_Lock
{
    /**
     * DB_Lock constructor.
     *
     * @param $order_id
     * @param $name
     * @param $value
     * @since 3.0.5
     *
     */
    public function __construct( $order_id, $name, $value );

    /**
     * Acquires the lock and returns it. Returns null if lock could not be acquired.
     *
     * @return WP_DB_Lock|null
     * @since 3.0.5
     *
     */
    public function acquire();

    /**
     * Releases the lock.
     *
     * @since 3.0.5
     */
    public function release();

    /**
     * @return mixed
     *
     * @since 4.0.2
     */
    public function is_free();

    /**
     * @return mixed
     *
     * @since 5.0.0
     */
    public function get_timestamp();
}