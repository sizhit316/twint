<?php

namespace Mame_Twint\services;

/**
 * Class AbstractMailer
 * @package Mame_Twint\services
 */
abstract class AbstractMailer
{
    /**
     * Sends an email when enrollment of the cashier fails.
     *
     * @param $enrollment_response
     */
    abstract public static function send_enrollment_failed_email( $enrollment_response );

    /**
     * Sends an email when the periodic system check fails.
     *
     * @param $response
     */
    abstract public static function send_system_check_failed_email( $response );

    /**
     * Sends an email when requestCheckin fails.
     *
     * @param $response
     */
    abstract public static function send_checkin_failed_email( $response );

    /**
     * Sends an email when renewal of the certificate fails.
     *
     * @param $response
     * @param $expiry
     */
    abstract public static function send_certificate_renewal_failed_email( $response, $expiry );

    /**
     * Sends an email when the certificate has expired.
     *
     * @return mixed
     */
    abstract public static function send_certificate_expired_email();

    /**
     * Sends a reminder email that the certificate expires soon.
     *
     * @param $days_before_expiring
     */
    abstract public static function send_certificate_expires_soon_email( $days_before_expiring );

    /**
     * Sends an email if the order confirmation fails.
     *
     * @param $order_id
     */
    abstract public static function send_order_confirmation_failed_email( $order_id );

    /**
     * Returns the admin email address.
     *
     * @return string
     */
    abstract protected static function get_email_address();

    /**
     * Sends admin emails. Can be called by all other functions to send emails.
     *
     * @param $subject
     * @param $message
     */
    public static function send_admin_email( $subject, $message )
    {
        $email_address = static::get_email_address();
        if ( !empty( $email_address ) ) {

            $header = static::get_header();

            mail( $email_address, $subject, $message, $header );
        }
    }

    /**
     * Returns the email header.
     *
     * @return string
     */
    protected static function get_header()
    {
        $header = "MIME-Version: 1.0\r\n";
        $header .= "Content-type: text/html; charset=utf-8\r\n";
        $header .= "X-Mailer: PHP " . phpversion();

        return $header;
    }
}