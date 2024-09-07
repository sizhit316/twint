<?php

namespace Mame_Twint\interfaces;

/**
 * Interface iDataProvider
 * @package Mame_Twint\interfaces
 */
interface iDataProvider
{
    /**
     * Returns a unique process ID for the request.
     *
     * @return mixed
     */
    public function get_process_id();

    /**
     * Returns the currency ISO Code.
     *
     * @return string
     */
    public function get_currency();

    /**
     * Returns saved the TWINT order data.
     *
     * order data =
     * [
     *  status,
     *  pairing_uuid,
     *  order_uuid,
     *  twint_status,
     *  twint_status_reason
     * ]
     *
     * @param null $property
     * @param bool $cache
     * @return array|string
     */
    public function get_order_data( $property = null, $cache = true );

    /**
     * Returns the order id of the system.
     *
     * @return string|int
     */
    public function get_order_id();

    /**
     * Returns the Order UUID
     *
     * @param bool $cache
     * @return string
     */
    public function get_order_uuid( $cache = true );

    /**
     * Returns the merchant reference for the order.
     *
     * @param bool $reversal
     * @return string
     */
    public function get_or_create_merchant_reference( $reversal = false );

    /**
     * Saves the TWINT order data.
     *
     * @param $data
     * @return array
     */
    public function save_order_data( $data );

    /**
     * Deletes all data in array $data.
     *
     * @param array $data
     */
    public function delete_order_data( $data );

    /**
     * Saves a transaction belonging to the current order.
     * [operation, reference, amount, fee]
     *
     * @param $data
     * @return mixed
     */
    public function save_transaction( $data );

    /**
     * Returns all transactions for the order.
     *
     * @return array
     */
    public function get_transactions();

    /**
     * Returns the total amount of the order.
     *
     * @return float
     */
    public function get_total();

    /**
     * Returns the formatted amount to be output on screen.
     *
     * @return string
     */
    public function get_formatted_amount();

    /**
     * Returns true if StartOrder was called for this order.
     *
     * @return bool
     */
    public function is_order_started();

    /**
     * Returns true if the current order is complete.
     *
     * @param bool $cache
     * @return bool
     */
    public function is_order_complete( $cache = false );

    /**
     * Returns true if the payment should be manually captured (PAYMENT_DEFERRED).
     *
     * @return bool
     */
    public function is_payment_deferred();

    /**
     * Returns the timeout in seconds.
     *
     * @return int
     */
    public function get_timeout();

    /**
     * Returns the timeout of the server to run scripts.
     *
     * @return mixed
     */
    public function get_max_execution_time();

    /**
     * Returns true if the allowed memory has exceeded.
     *
     * @return bool
     */
    public function has_memory_exceeded();

    /**
     * Returns the interval in seconds for SOAP requests.
     *
     * @return int
     */
    public function get_soap_interval();

    /**
     * Returns the URL of the order confirmation page (successful order).
     *
     * @return string
     */
    public function get_order_received_url();

    /**
     * Returns the URL of the checkout page.
     *
     * @return string
     */
    public function get_checkout_url();

    /**
     * Returns the payment page of the checkout where the payment method can be selected.
     *
     * @return string
     */
    public function get_checkout_payment_url();

    /**
     * Returns the URL to redirect to when an order is cancelled.
     *
     * @return string
     */
    public function get_cancel_order_url();

    /**
     * Returns the URL to redirect to when the timeout is reached.
     *
     * @return string
     */
    public function get_timeout_url();

    /**
     * Returns the URL to redirect to when the payment failed.
     *
     * @return string
     */
    public function get_failure_url();

    /**
     * Returns the directory of the current swhere TWINT files are stored.
     *
     * @return string
     */
    public function get_twint_files_dir();

    /**
     * Returns the URL for handling the webhook redirect response.
     *
     * @return mixed
     */
    public function get_webhook_redirect_url( $args = [] );

    /**
     * Returns the URL for handling the webhook cancel response.
     *
     * @param $args
     * @return mixed
     */
    public function get_webhook_cancel_url( $args = [] );

    /**
     * Returns the URL for handling the order update notification webhook.
     *
     * @return mixed
     */
    public function get_webhook_order_update_notification_url();

    /**
     * Sets the system's order.
     *
     * @param $order_id
     * @return iDataProvider
     */
    public function set_order( $order_id );

    /**
     * Sets the merchant.
     *
     * @param string|int $merchant_id
     * @return iDataProvider
     */
    public function set_merchant( $merchant_id );

    /**
     * Returns the merchant UUID.
     *
     * @return string
     */
    public function get_merchant_uuid();

}