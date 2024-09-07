<?php

namespace Mame_Twint\interfaces;

use Mame_Twint\lib\iDB_Lock;

/**
 * Interface iEventHandler
 * @package Mame_Twint\interfaces
 */
interface iEventHandler
{
    /**
     * iEventHandler constructor.
     * @param $data_provider
     */
    public function __construct( $data_provider );

    /**
     * Called before a successful TWINT order is handled.
     */
    public function on_before_handle_successful_payment();

    /**
     * Handles all order updates after a successful payment.
     * @param iDB_Lock $lock
     */
    public function on_successful_payment($lock);

    /**
     * Handles the cancellation by the customer.
     */
    public function on_cancelled_by_customer();

    /**
     * @param array $result
     */
    public function on_webhook( $result );

    /**
     * Forks a new background task to monitor the TWINT order.
     *
     * @return mixed
     */
    public function spawn_monitor_order_task( $time = 0);

    /**
     * Redirect to the checkout page.
     *
     * @param bool $error
     */
    public function return_to_checkout( $error = false );

    /**
     * Redirect to the checkout payment page (payment method select screen). This can be the same as return_to_checkout.
     *
     * @param $error
     */
    public function return_to_payment_page( $error = false );

    /**
     * Redirects to the order confirmation page.
     */
    public function redirect_to_receipt_page();

    /**
     * Sets the order to a failed state.
     */
    public function fail_order();
}