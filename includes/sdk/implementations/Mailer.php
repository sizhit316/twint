<?php

namespace Mame_Twint\services;

class Mailer extends AbstractMailer
{
    /**
     * Sends an email when enrollment of the cashier fails.
     *
     * @param $enrollment_response
     */
    public static function send_enrollment_failed_email( $enrollment_response )
    {
        $subject = __( 'TWINT cashier register enrolment failed', 'mametwint' );
        $message = sprintf( __( 'Cashier register could not be enrolled for website %1$s.', 'mametwint' ), get_bloginfo( 'title' ) );
        $message .= static::get_common_messages();
        $message .= __( 'Error message : ', 'mametwint' ) . $enrollment_response[ 'message' ];

        static::send_admin_email( $subject, $message );
    }

    /**
     * Sends an email when the periodic system check fails.
     *
     * @param $response
     */
    public static function send_system_check_failed_email( $response )
    {
        $subject = __( 'TWINT system check failed', 'mametwint' );
        $message = sprintf( __( 'System check for website %1$s failed.', 'mametwint' ), get_bloginfo( 'title' ) );
        $message .= static::get_common_messages();
        $message .= __( 'Response : ', 'mametwint' );
        $message .= '<br>';
        $message .= json_encode( $response );

        static::send_admin_email( $subject, $message );
    }

    /**
     * Sends an email when requestCheckin fails.
     *
     * @param $response
     */
    public static function send_checkin_failed_email( $response )
    {
        $subject = __( 'TWINT failed on the checkout', 'mametwint' );
        $message = __( 'Failed to connect to TWINT on the checkout.', 'mametwint' );
        $message .= static::get_common_messages();
        $message .= __( 'Response : ', 'mametwint' );
        $message .= '<br>';
        $message .= json_encode( $response );

        static::send_admin_email( $subject, $message );
    }

    /**
     * Sends an email when renewal of the certificate fails.
     *
     * @param $response
     * @param $expiry
     */
    public static function send_certificate_renewal_failed_email( $response, $expiry )
    {
        $subject = __( 'TWINT certificate renewal failed', 'mametwint' );
        $message = sprintf( __( 'The automatic renewal of the TWINT certificate failed for the website %1$s.', 'mametwint' ), get_bloginfo( 'title' ) );
        $message .= __( 'Please try manually renewing the certificate from either the TWINT settings in the WordPress backend or from the TWINT portal. Please make sure that all other websites/shops using the same TWINT account are updated accordingly.', 'mametwint' );
        $message .= '<br><br>';
        $message .= sprintf( __( 'The old certificates expires %1$s', 'mametwint' ), $expiry );
        $message .= '<br><br>';
        $message .= __( 'Response : ', 'mametwint' );
        $message .= '<br>';
        $message .= json_encode( $response );

        static::send_admin_email( $subject, $message );
    }

    /**
     * Sends an email when the certificate has expired.
     *
     * @return mixed
     */
    public static function send_certificate_expired_email()
    {
        $subject = __( 'TWINT certificate expired', 'mametwint' );
        $message = sprintf( __( 'The TWINT certificate for the website %1$s expired. Please renew the certificate from the TWINT settings in the WordPress backend or from the TWINT portal.', 'mametwint' ), get_bloginfo( 'title' ) );

        static::send_admin_email( $subject, $message );
    }

    /**
     * Sends a reminder email that the certificate expires soon.
     *
     * @param $days_before_expiring
     */
    public static function send_certificate_expires_soon_email( $days_before_expiring )
    {
        $subject = __( 'TWINT certificate will expire soon.', 'mametwint' );
        $message = sprintf( __( 'The TWINT certificate for the website %1$s will expire in %2$s days. Please renew the certificate from the TWINT settings in the WordPress backend or from the TWINT portal.', 'mametwint' ), get_bloginfo( 'title' ), $days_before_expiring );

        static::send_admin_email( $subject, $message );
    }

    /**
     * Sends an email if the order confirmation fails.
     *
     * @return mixed
     */
    public static function send_order_confirmation_failed_email( $order_id )
    {
        $subject = __( 'TWINT payment was successful but not confirmed', 'mametwint' );
        $message = sprintf( __( 'The TWINT payment for order %1$s was successful but not confirmed. Please confirm the payment in the order edit screen of the WP backend.', 'mametwint' ), $order_id );
        static::send_admin_email( $subject, $message );
    }

    /**
     * Returns the admin email address.
     *
     * @return string
     */
    protected static function get_email_address()
    {
        return get_option( 'mametw_settings_email_on_enroll_failure' );
    }

    /**
     * @return string
     */
    private static function get_common_messages()
    {
        $message = '<br><br>';
        $message .= sprintf( __( 'Please check if the payment gateway works on the WooCommerce checkout page. You can perform a system check in the WooCommerce TWINT settings (enroll cash register). If the system continues to fail for a longer period, please contact our %1$s or the TWINT support (%2$s).', 'mametwint' ), '<a href="https://www.mamedev.ch/support/?lang=en">Support</a>', 'support@twint.ch' );
        $message .= '<br><br>';
        $message .= __( 'Please also check the logs in the WP backend on the page <strong>WooCommerce > Settings > TWINT > Logs</strong>', 'mametwint' );
        $message .= '<br><br>';

        return $message;
    }
}