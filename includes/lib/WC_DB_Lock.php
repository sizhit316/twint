<?php

namespace Mame_Twint\lib;

/**
 * Class WC_DB_Lock
 * @package Mame_Twint\lib
 * @since 6.0.0
 */
class WC_DB_Lock implements iDB_Lock
{
    const LOCK_PREFIX = '_' . MAME_TW_PREFIX . '_lock_';

    /** @var \WC_order */
    private $order;

    /** @var int|string */
    private $order_id;

    /** @var string */
    private $name;

    /** @var mixed */
    private $value;

    /**
     * DB_Lock constructor.
     *
     * @param int|string $order_id
     * @param string $name
     * @param mixed $value
     * @since 3.1.0
     *
     */
    public function __construct( $order_id, $name, $value )
    {
        $this->order_id = $order_id;
        $this->name     = $name;
        $this->value    = $value;
    }

    /**
     * Acquires the lock and returns it. Returns null if lock could not be acquired.
     *
     * @return WC_DB_Lock|null
     * @since 3.1.0
     *
     */
    public function acquire()
    {
        $this->order = wc_get_order( $this->order_id );

        // Check if in db and save if not.
        if ( $this->value !== $this->order->get_meta( static::LOCK_PREFIX . $this->name, true ) ) {
            $this->order->update_meta_data( static::LOCK_PREFIX . $this->name, $this->value );
            $this->order->update_meta_data( static::LOCK_PREFIX . $this->name . '-timestamp', time() );
            $this->order->save();
            return new static( $this->order_id, $this->name, $this->value );
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
        $this->order = wc_get_order( $this->order_id );
        WC_Helper::delete_order_meta( $this->order, static::LOCK_PREFIX . $this->name, $this->value, false );
        WC_Helper::delete_order_meta( $this->order, static::LOCK_PREFIX . $this->name . '-timestamp' );
    }

    /**
     * @return mixed
     *
     * @since 4.0.2
     */
    public function is_free()
    {
        $this->order = wc_get_order( $this->order_id );
        return !$this->order->get_meta( static::LOCK_PREFIX . $this->name, true );
    }

    /**
     * @return mixed
     *
     * @since 5.0.0
     */
    public function get_timestamp()
    {
        $this->order = wc_get_order( $this->order_id );
        return $this->order->get_meta( static::LOCK_PREFIX . $this->name . '-timestamp', true );
    }
}