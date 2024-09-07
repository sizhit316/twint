<?php

namespace Mame_Twint\admin;

use Mame_Twint\Globals;
use Mame_Twint\lib\Html;
use Mame_Twint\lib\updates\Licensing_Handler;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds the general TWINT settings tab to WooCommerce settings
 */
class Settings_Tab extends \WC_Settings_Page
{
    private static $instance;

    /**
     * Hook into WC functions.
     */
    public function __construct()
    {
        $this->id    = 'twint';
        $this->label = __( 'TWINT', 'mametwint' );

        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
        add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output_settings' ) );
        add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
        add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save_settings' ) );
        //add_action( 'woocommerce_settings_saved', array( $this, 'init' ) );

        add_action( 'woocommerce_admin_field_mametw_certificate', array( $this, 'display_certificate_field' ) );
        add_action( 'woocommerce_admin_field_mametw_certificate_upload', array( $this, 'display_certificate_upload_field' ) );
        add_action( 'woocommerce_admin_field_mametw_certificate_password', array( $this, 'display_certificate_password_field' ) );
        add_action( 'woocommerce_admin_field_mametw_setup_assistant', array( $this, 'display_setup_assistant_field' ) );
        add_action( 'woocommerce_admin_field_mametw_systeminfo', array( $this, 'display_systeminfo_field' ) );
        add_action( 'woocommerce_admin_field_mametw_logs', array( $this, 'display_logs_field' ) );
        add_action( 'woocommerce_admin_field_twintenroll', __CLASS__ . '::display_register_enroll_field' );
        add_action( 'woocommerce_admin_field_renew_certificate', __CLASS__ . '::display_renew_certificate_field' );

        add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'sanitize_certificate_option' ), 10, 3 );
    }

    function sanitize_certificate_option( $value, $option, $raw_value )
    {
        if ( $option[ 'type' ] == 'mametw_certificate' ) {
            return sanitize_textarea_field( $raw_value );
        } elseif ( $option[ 'type' ] == 'mametw_certificate_password' ) {

            // Careful: & ; \ get stripped sometimes when creating a cert.
            return addcslashes( $value, "\\'\"!~@#%^*_+-={}[]:,./`$&()|;<>?" );
        } elseif ( $option[ 'id' ] == 'mametw_settings_registerid' ) {
            return substr( $value, 0, 50 );
        } elseif ( $option[ 'id' ] == 'mametw_order_check_interval' ) {
            if ( $value > 0 ) {

                $previous_value = get_option( 'mametw_order_check_interval' );
                if ( intval( $previous_value ) != intval( $value ) ) {
                    $timestamp = wp_next_scheduled( MAME_TW_PREFIX . '_check_order_statuses' );
                    wp_unschedule_event( $timestamp, MAME_TW_PREFIX . '_check_order_statuses' );
                }

            }
        }
        return $value;
    }

    public static function get_instance()
    {
        if ( empty( static::$instance ) ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Output the settings
     */
    public function output_settings()
    {
        global $current_section;

        $settings = $this->get_settings( $current_section );

        $setup_complete = $this->is_setup_complete();

        ?>
        <button id="<?= MAME_TW_PREFIX ?>-start-setup-button"
                class="button <?= $setup_complete ? '' : 'hidden' ?>"><?= __( 'Start setup assistant', 'mametwint' ) ?></button>
        <div id="<?= MAME_TW_PREFIX ?>-setup-complete" data-value="<?= $setup_complete ?>"></div>
        <div id="<?= MAME_TW_PREFIX ?>-settings-fields" class="<?= $setup_complete ? '' : 'hidden' ?>">
            <?php woocommerce_admin_fields( $settings ); ?>
        </div>
        <div id="<?= MAME_TW_PREFIX ?>-setup-assistant-field"
             class="<?= $setup_complete ? 'hidden' : '' ?>"><?php $this->display_setup_assistant_field(); ?></div>
        <?php
        $this->display_modal();
    }

    /**
     * Save settings
     */
    public function save_settings()
    {
        global $current_section;

        $settings = $this->get_settings( $current_section );

        if ( isset( $_POST[ 'mametw_settings_uuid' ] ) ) {
            $_POST[ 'mametw_settings_uuid' ] = trim( $_POST[ 'mametw_settings_uuid' ] );
        }

        woocommerce_update_options( $settings );

        if ( isset( $_POST[ 'mametw_settings_certpw' ] ) ) {
            update_option( 'mametw_settings_certpw', $_POST[ 'mametw_settings_certpw' ] );
        }
    }

    public function get_sections()
    {
        $sections = array();

        $sections[ '' ] = __( 'General', 'mametwint' );
        //$sections[ 'mametw_tools' ] = __( 'Tools', 'mametwint' );
        $sections[ 'mametw_system_status' ] = __( 'System status', 'mametwint' );
        $sections[ 'logs' ]                 = __( 'Logs', 'mametwint' );

        return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
    }

    /**
     * Get settings array.
     * @param string $current_section
     * @return
     */
    public function get_settings( $current_section = '' )
    {

        if ( '' === $current_section ) {
            $doc_url = 'https://docs.mamedev.ch/';

            $openssl_warning = __( 'Upload the certificate you downloaded from your TWINT account in p12 format to automatically convert it. Please first enter the certificate password and save it before uploading the certificate. If automatic conversion fails you will have to manually convert the certificate to PEM format and either rename it twint.txt and use the upload button or directly upload the renamed file twint.pem to the directory wp-content/uploads/mame_twint. Read the <a href="https://documentation.mame-webdesign.ch/?p=273&lang=en#1-2%c2%a0certificate-creation" target="_blank">documentation</a> for more information.', 'mametwint' );

            if ( !MAME_TW_OPENSSL_ACTIVE ) {

                $openssl_warning .= __( '<br><span class="mame-warning">The PHP extension openssl seems not to be active on your server which means that automatic conversion will not work.</span>', 'mametwint' );
            }

            $settings = [
                'section_title' => array(
                    'name' => __( 'TWINT General Settings', 'mametwint' ),
                    'type' => 'title',
                    'desc' => __( 'Please read the <a href="', 'mametwint' ) . $doc_url . __( '" target="_blank">documentation</a> for the configuration of the settings.', 'mametwint' ),
                    'id'   => 'mametw_settings_title'
                ),
            ];

            $settings = array_merge( $settings, [
                'plugin_license'                       => array(
                    'name' => __( 'License key', 'mametwint' ),
                    'type' => 'mame_tw_licensing',
                    'desc' => __( 'Enter your license code to receive updates and support for the TWINT plugin.', 'mametwint' ),
                    'id'   => 'mametw_settings_license'
                ),
                'uuid'                                 => array(
                    'title' => __( 'Store UUID', 'mametwint' ),
                    'type'  => 'text',
                    'desc'  => __( 'The UUID of your store can be found in the TWINT account by selecting your shop on the page <strong>STORES > [edit store button]</strong>. If the list of stores is empty you will first have to create a store.', 'mametwint' ),
                    'id'    => 'mametw_settings_uuid',
                    'class' => 'mametw-prod-settings',
                ),
                'cert_pass'                            => array(
                    'title' => __( 'Certificate passphrase', 'mametwint' ),
                    'type'  => 'mametw_certificate_password',
                    'desc'  => __( 'The password for the certificate which was set in the TWINT account when the certificate was created. This is not the password for the TWINT account. Please only use the following special characters in your password: ~!@#%^*_+-={}[]:,./', 'mametwint' ),
                    'id'    => 'mametw_settings_certpw',
                    'class' => 'mametw-prod-settings',
                ),
                'register_id'                          => array(
                    'title' => __( 'Cash Register ID', 'mametwint' ),
                    'type'  => 'text',
                    'desc'  => __( 'Choose any ID to identify transactions from your sales point in the TWINT backend. You can choose any name but names have to be different for shops which use the same TWINT account.', 'mametwint' ),
                    'id'    => 'mametw_settings_registerid',
                    'class' => 'mametw-prod-settings',
                ),
                'certificate_upload'                   => array(
                    'title'            => __( 'Certificate', 'mametwint' ),
                    'type'             => 'mametw_certificate_upload',
                    'desc'             => $openssl_warning,
                    'id'               => 'mametw_settings_certificate_upload',
                    'class'            => 'mametw-prod-settings',
                    'data-environment' => 'prod',
                ),
                'enroll_register'                      => array(
                    'title' => __( 'Enroll cash register', 'mametwint' ),
                    'type'  => 'twintenroll',
                    'desc'  => __( 'Before you can use TWINT you will have to enroll your cash register once. Do this after you have filled in the settings above and converted the certificate.', 'mametwint' ),
                    'name'  => 'mametw_settings_enroll',
                    'class' => 'mametw-prod-settings prod ' /*. $show_enroll_field ? '' : 'hidden'*/,
                ),
                'payment_type'                         => [
                    'title'   => __( 'Payment type', 'mametwint' ),
                    'type'    => 'select',
                    'desc'    => __( 'Select \'immediate\' to confirm the transaction immediately when the customer completes the payment (default). If \'deferred\' is selected, the amount is only reserved and you will need to manually confirm the transaction from the order edit screen. Deferred payments have to be confirmed whith 30 days.', 'mametwint' ),
                    'options' => array(
                        'immediate' => __( 'Immediate', 'mametwint' ),
                        'deferred'  => __( 'Deferred', 'mametwint' ),
                    ),
                    'default' => 'immediate',
                    'id'      => 'mametw_settings_payment_type'
                ],
                'chf_only'                             => array(
                    'title' => __( 'Only enable for CHF', 'mametwint' ),
                    'type'  => 'checkbox',
                    'desc'  => __( 'TWINT payments are only possible in Swiss Francs (CHF). If this option is selected the TWINT payment gateway will only be shown on the checkout page for payments in CHF.', 'mametwint' ),
                    'id'    => 'mametw_settings_chf_only',
                ),
                'reference_include_billing_firstname'  => [
                    'title'         => __( 'Add customer data to reference number sent to TWINT. This will make it easier to search for certain transactions in the TWINT backend.', 'mametwint' ),
                    'desc'          => __( 'Billing first name', 'mametwint' ),
                    'id'            => 'mame_tw_reference_include_billing_firstname',
                    'default'       => 'no',
                    'type'          => 'checkbox',
                    'checkboxgroup' => 'start'
                ],
                'reference_include_billing_lastname'   => [
                    'desc'          => __( 'Billing last name', 'mametwint' ),
                    'id'            => 'mame_tw_reference_include_billing_lastname',
                    'default'       => 'no',
                    'type'          => 'checkbox',
                    'checkboxgroup' => ''
                ],
                'reference_include_billing_company'    => [
                    'desc'          => __( 'Billing company', 'mametwint' ),
                    'id'            => 'mame_tw_reference_include_billing_company',
                    'default'       => 'no',
                    'type'          => 'checkbox',
                    'checkboxgroup' => ''
                ],
                'reference_include_shipping_firstname' => [
                    'desc'          => __( 'Shipping first name', 'mametwint' ),
                    'id'            => 'mame_tw_reference_include_shipping_firstname',
                    'default'       => 'no',
                    'type'          => 'checkbox',
                    'checkboxgroup' => ''
                ],
                'reference_include_shipping_lastname'  => [
                    'desc'          => __( 'Shipping last name', 'mametwint' ),
                    'id'            => 'mame_tw_reference_include_shipping_lastname',
                    'default'       => 'no',
                    'type'          => 'checkbox',
                    'checkboxgroup' => ''
                ],
                'reference_include_shipping_company'   => [
                    'desc'          => __( 'Shipping company', 'mametwint' ),
                    'id'            => 'mame_tw_reference_include_shipping_company',
                    'default'       => 'no',
                    'type'          => 'checkbox',
                    'checkboxgroup' => 'end'
                ],
                'email_on_enroll_failure'              => array(
                    'title' => __( 'System check email address', 'mametwint' ),
                    'type'  => 'email',
                    'desc'  => __( 'Enter the email address to which system emails should be sent (e.g. when the TWINT system check or the certificate renewal fails.', 'mametwint' ),
                    'id'    => 'mametw_settings_email_on_enroll_failure',
                ),
                'section_end'                          => [
                    'type' => 'sectionend',
                    'id'   => 'mametw_settings_title'
                ],
                'advanced_section_title'               => array(
                    'name' => __( 'Advanced settings', 'mametwint' ),
                    'type' => 'title',
                    'desc' => __( 'These settings are optional.', 'mametwint' ),
                    'id'   => 'mametw_settings_advanced_title'
                ),
                'automatically_renew_certificate'      => array(
                    'title'   => __( 'Automatically renew certificate', 'mametwint' ),
                    'type'    => 'checkbox',
                    'default' => 'no',
                    'desc'    => __( 'The TWINT certificate can be renewed automatically before it expires. Please note that this will not work if you have multiple shops connected to the same TWINT account. In this case you will need to manually renew the certificate in the TWINT portal and then upload it to each shop.', 'mametwint' ),
                    'id'      => 'mametw_automatically_renew_certificate',
                ),
                'renew_certificate'                    => array(
                    'title' => __( 'Renew certificate', 'mametwint' ),
                    'type'  => 'renew_certificate',
                    'desc'  => __( 'Here you can manually renew the certificate. Please note that if you are using multiple shops with the same TWINT account you will need to manually copy the created PEM certificate file from this shop to the other shops. The file can be found in the folder "wp-content/uploads/mame_twint". Alternatively you can renew the certificate from the TWINT backend and then upload it to all shops via the upload button above.', 'mametwint' ),
                    'name'  => 'mametw_settings_renew_certificate',
                    'class' => ' ',
                ),
                'timeout'                              => array(
                    'title' => __( 'Timeout', 'mametwint' ),
                    'type'  => 'number',
                    'desc'  => __( 'Enter the timeout for the payment process in seconds or leave empty for default/maximum value 180.', 'mametwint' ),
                    'id'    => 'mametw_settings_timeout',
                ),
                'soap_request_interval'                => array(
                    'title'   => __( 'SOAP request interval', 'mametwint' ),
                    'type'    => 'number',
                    'default' => MAME_TW_DEFAULT_SOAP_INTERVAL,
                    'min'     => 1,
                    'desc'    => __( 'The interval between SOAP calls in seconds. Leave empty for default value 2.', 'mametwint' ),
                    'id'      => 'mametw_settings_soap_request_interval',
                ),
                'soap_connection_timeout'              => array(
                    'title'   => __( 'SOAP connection timeout', 'mametwint' ),
                    'type'    => 'number',
                    'default' => MAME_TW_DEFAULT_CONNECTION_TIMEOUT,
                    'min'     => 1,
                    'desc'    => __( 'The connection timeout for SOAP calls. Should be lower than the maximum execution time of the server.', 'mametwint' ),
                    'id'      => 'mametw_settings_connection_timeout',
                ),
                'check_pending_status_after'           => array(
                    'title'   => __( 'Order status cron job interval', 'mametwint' ),
                    'type'    => 'number',
                    'default' => 15,
                    'min'     => 0,
                    'desc'    => __( 'Enter how often the cron job should run (in minutes) to check and update orders paid by TWINT which were successfully paid but where the order status was not updated correctly. Enter 0 to deactivate the cron action.', 'mametwint' ),
                    'id'      => 'mametw_order_check_interval',
                ),
                'system_check_interval'                => array(
                    'title'   => __( 'System check interval', 'mametwint' ),
                    'type'    => 'number',
                    'default' => MAME_TW_DEFAULT_SYSTEM_CHECK_INTERVAL,
                    'min'     => 1,
                    'desc'    => __( 'The plugin periodically performs a system check to check if the TWINT service is available and sends an email to the email address in the <strong>System check email address</strong> field  if the system check fails. Enter the number of hours to define the interval of the system check.', 'mametwint' ),
                    'id'      => 'mametw_system_check_interval',
                ),
                'order_status_non_virtual'             => array(
                    'title'   => __( 'Order status after successful payments for orders with non-virtual products', 'mametwint' ),
                    'type'    => 'select',
                    'desc'    => __( 'Select the order status to be applied on successful payment for orders containing non-virtual products.', 'mametwint' ),
                    'options' => array(
                        'on-hold'    => __( 'on-hold', 'mametwint' ),
                        'processing' => __( 'processing', 'mametwint' ),
                        'completed'  => __( 'completed', 'mametwint' ),
                    ),
                    'default' => MAME_TW_DEFAULT_STATUS_ORDER,
                    'id'      => 'mametw_settings_order_status_non_virtual'
                ),
                'order_status_virtual'                 => array(
                    'title'   => __( 'Order status after successful payments for virtual products', 'mametwint' ),
                    'type'    => 'select',
                    'desc'    => __( 'Select the order status to be applied on successful payment for orders containing only virtual products.', 'mametwint' ),
                    'options' => array(
                        'on-hold'    => __( 'on-hold', 'mametwint' ),
                        'processing' => __( 'processing', 'mametwint' ),
                        'completed'  => __( 'completed', 'mametwint' ),
                    ),
                    'default' => MAME_TW_DEFAULT_STATUS_VIRTUAL,
                    'id'      => 'mametw_settings_order_status_virtual'
                ),
                'deferred_order_status'                => [
                    'title'   => __( 'Order status for deferred payments.', 'mametwint' ),
                    'type'    => 'select',
                    'desc'    => __( 'Select the WooCommerce order status for deferred payments which are not settled yet. Only applies if \'deferred\' is selected under \'Payment type\'.', 'mametwint' ),
                    'options' => array(
                        'on-hold'    => __( 'On-hold', 'mametwint' ),
                        'processing' => __( 'Processing', 'mametwint' ),
                        'completed'  => __( 'Completed', 'mametwint' ),
                    ),
                    'default' => 'on-hold',
                    'id'      => 'mametw_settings_deferred_order_status'
                ],
                'log'                                  => [
                    'title'   => __( 'Write logs', 'mametwint' ),
                    'type'    => 'select',
                    'default' => 'all',
                    'options' => [
                        'off'   => __( 'Off', 'mametwint' ),
                        'error' => __( 'Errors', 'mametwint' ),
                        'all'   => __( 'All events', 'mametwint' ),
                    ],
                    'desc'    => __( 'Select the log level.', 'mametwint' ),
                    'id'      => 'mametw_log',
                ],
                'delete_logs_after'                    => [
                    'title'   => __( 'Delete logs after', 'mametwint' ),
                    'type'    => 'number',
                    'default' => 30,
                    'min'     => 0,
                    'desc'    => __( 'Enter the number of days after which logs will automatically be deleted. If left empty logs will be deleted after 30 days by default.', 'mametwint' ),
                    'id'      => 'mametw_delete_logs_after',
                ],
                'asnyc_bg_task_request_immediately'    => [
                    'title'         => __( 'Asynchronous background task request', 'mametwint' ),
                    'desc'          => __( 'Immediately', 'mametwint' ),
                    'id'            => 'mame_tw_asnyc_bg_task_request_immediately',
                    'default'       => 'yes',
                    'type'          => 'checkbox',
                    'checkboxgroup' => 'start'
                ],
                'asnyc_bg_task_request_shutdown'       => [
                    'desc'          => __( 'In shutdown action', 'mametwint' ),
                    'id'            => 'mame_tw_asnyc_bg_task_request_shutdown',
                    'default'       => 'no',
                    'type'          => 'checkbox',
                    'checkboxgroup' => ''
                ],
                'asnyc_bg_task_request_ajax'           => [
                    'desc'          => __( 'In AJAX request', 'mametwint' ),
                    'id'            => 'mame_tw_asnyc_bg_task_request_ajax',
                    'default'       => 'yes',
                    'type'          => 'checkbox',
                    'checkboxgroup' => ''
                ],
                'asnyc_bg_task_request_cron'           => [
                    'desc'          => __( 'In Cron action 5 minutes after starting transaction', 'mametwint' ),
                    'id'            => 'mame_tw_asnyc_bg_task_request_cron',
                    'default'       => 'yes',
                    'type'          => 'checkbox',
                    'checkboxgroup' => 'end',
                ],
                'proxyhost'                            => array(
                    'title' => __( 'Proxy host', 'mametwint' ),
                    'type'  => 'text',
                    'desc'  => __( 'Enter the address of the proxy host if you are using a proxy. (optional)', 'mametwint' ),
                    'id'    => 'mametw_settings_proxyhost'
                ),
                'proxyport'                            => array(
                    'title' => __( 'Proxy port', 'mametwint' ),
                    'type'  => 'text',
                    'desc'  => __( 'Enter the port of your proxy if you are using a proxy. (optional)', 'mametwint' ),
                    'id'    => 'mametw_settings_proxyport'
                ),
                'ipv4_only'                            => array(
                    'title'   => __( 'Force IPv4 for cURL requests', 'mametwint' ),
                    'type'    => 'checkbox',
                    'default' => 'no',
                    'desc'    => __( 'Forces all cURL requests to use IPv4.', 'mametwint' ),
                    'id'      => 'mametw_ipv4_only',
                ),
                'advanced_section_end'                 => array(
                    'type' => 'sectionend',
                    'id'   => 'mametw_advanced_section_end'
                )
            ] );

            return apply_filters( 'wc_settings_tab_twint_settings', $settings );

        } elseif ( 'mametw_tools' == $current_section ) {

            $settings = array(
                'section_title'   => array(
                    'name' => __( 'Tools', 'mametwint' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'mametw_tools_title'
                ),
                'test_connection' => array(
                    'title' => __( 'Test', 'mametwint' ),
                    'type'  => 'mametw_test_connection',
                    'desc'  => '',
                    'id'    => 'mametw_settings_tools'
                ),
                'section_end'     => array(
                    'type' => 'sectionend',
                    'id'   => 'mametw_tools_end'
                ),
            );

            return apply_filters( 'wc_settings_tab_twint_settings', $settings );

        } elseif ( 'mametw_system_status' == $current_section ) {
            $settings = array(
                'section_title' => array(
                    'name' => __( 'System status', 'mametwint' ),
                    'type' => 'title',
                    'desc' => '',
                    'id'   => 'mametw_system_status_title'
                ),
                'systeminfo'    => array(
                    'title' => __( 'System info', 'mametwint' ),
                    'type'  => 'mametw_systeminfo',
                    'desc'  => '',
                    'id'    => 'mametw_settings_systeminfo'
                ),
                'section_end'   => array(
                    'type' => 'sectionend',
                    'id'   => 'mametw_system_status_end'
                ),
            );

            return apply_filters( 'wc_settings_tab_twint_settings', $settings );
        } elseif ( 'logs' == $current_section ) {

            $settings = array(
                'logs'        => array(
                    'name' => __( 'Logs', 'mametwint' ),
                    'type' => 'title',
                    'desc' => __( '', 'mametwint' ),
                    'id'   => 'mametw_logs_title'
                ),
                'systeminfo'  => array(
                    'title' => __( 'Logs', 'mametwint' ),
                    'type'  => 'mametw_logs',
                    'desc'  => __( '', 'mametwint' ),
                    'id'    => 'mametw_settings_logs'
                ),
                'section_end' => array(
                    'type' => 'sectionend',
                    'id'   => 'mametw_logs_end'
                ),
            );

            return $settings;

        }
    }

    public function display_certificate_field( $field )
    {
        $value = get_option( $field[ 'id' ] );
        ?>

        <tr valign="top"
            class="<?= MAME_TW_PREFIX . '-certificate-wrapper' ?> <?= $field[ 'class' ] ?> <?= $field[ 'environment' ] ?>">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $field[ 'title' ] ); ?>"><?php echo esc_html( $field[ 'title' ] ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo sanitize_title( $field[ 'type' ] ) ?>">
                    <textarea name="<?= $field[ 'id' ] ?>"
                              class="<?= $field[ 'class' ] ?>"><?= $value ?: '' ?></textarea>
                <span class="description"><?= $field[ 'desc' ] ?></span>
            </td>
        </tr>
        <?php
    }

    public function display_certificate_upload_field( $field )
    {
        wp_enqueue_media();
        $options = get_option( MAME_TW_PREFIX . '_options_group' );
        $name    = $field[ 'data-environment' ] == 'prod' ? 'certificate' : 'certificate_test';
        $value   = isset( $options[ $name ] ) ? $options[ $name ] : '';
        ?>

        <tr valign="top"
            class="<?= MAME_TW_PREFIX . '-certificate-upload-wrapper' ?> <?= $field[ 'class' ] ?> <?= $field[ 'data-environment' ] ?>">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $field[ 'id' ] ); ?>"><?php echo esc_html( $field[ 'title' ] ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo sanitize_title( $field[ 'type' ] ) ?>">
                <div class="upload">
                    <div>
                        <input type="hidden" name="<?= MAME_TW_PREFIX . '_options_group' . '[' . $name . ']' ?> "
                               id="<?= MAME_TW_PREFIX . '_options_group' . '[' . $name . ']' ?>"
                               value="<?= $value ?>"/>
                        <div id="<?= MAME_TW_PREFIX ?>-upload_file_button-wrapper">
                            <button type="submit"
                                    class="<?= MAME_TW_PREFIX ?>-upload_file_button button"
                                    data-environment="<?= esc_html( $field[ 'data-environment' ] ) ?>"><?= __( 'Upload file', 'mametwint' ) ?>
                            </button>
                            <p class="description"><?= $field[ 'desc' ] ?></p>
                        </div>
                        <div id="<?= MAME_TW_PREFIX ?>-certificate-loader"
                             class="mame-loader <?= $field[ 'data-environment' ] ?>"><img
                                    src="<?= plugins_url() . '/' . MAME_TW_PLUGIN_DIRNAME . '/assets/images/loader_round.gif' ?>">
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    public function display_certificate_password_field( $field )
    {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?= $field[ 'id' ] ?>"><?= $field[ 'title' ] ?></label>
            </th>
            <td class="forminp forminp-password">
                <input name="<?= $field[ 'id' ] ?>" id="<?= $field[ 'id' ] ?>" type="password" style=""
                       value="<?= esc_attr( stripcslashes( get_option( $field[ 'id' ] ) ) ) ?>"
                       class="<?= $field[ 'class' ] ?>"
                       placeholder="">
                <p class="description"><?= $field[ 'desc' ] ?></p></td>
        </tr>
        <?php
    }

    /**
     * Display the logs field.
     *
     * @param $field
     */
    public function display_logs_field( $field )
    {
        $log_file = Globals::get_log_file_path();
        if ( file_exists( $log_file ) ) {
            $content = file_get_contents( $log_file );
            ?>
            <div id="<?= MAME_TW_PREFIX ?>-logs">
                <pre><?= ($content) ?></pre>
            </div>
            <?php
        }

        $files = glob( Globals::get_upload_path( 'logs' ) . "*.log" );

        if ( !empty( $files ) && count( $files ) > 1 ) {

            $filenames = array_map( function ( $f ) {
                return basename( $f, '.log' );
            }, $files );

            usort($filenames, function($a, $b){

                $a_arr = explode('-', $a);
                $b_arr = explode('-', $b);
                if (count($a_arr) < 3){
                    return 1;
                }
                if (count($b_arr) < 3){
                    return -1;
                }

                if ($a_arr[2] > $b_arr[2]){
                    return -1;
                } elseif($a_arr[2] < $b_arr[2]){
                    return 1;
                }
                else {
                    if ($a_arr[1] > $b_arr[1]){
                        return -1;
                    } elseif($a_arr[1] < $b_arr[1]){
                        return 1;
                    }else {
                        if ($a_arr[0] > $b_arr[0]){
                            return -1;
                        } elseif($a_arr[0] < $b_arr[0]){
                            return 1;
                        }
                    }
                }
            });

            ?>
            <div id="<?= MAME_TW_PREFIX ?>-old-logs"><?php

                echo Html::h4( __( 'Download log files', 'mametwint' ) );

                foreach ( $filenames as $name ) {
//
                    $url = add_query_arg( [ MAME_TW_PREFIX . '-download' => 1, MAME_TW_PREFIX . '-type' => 'logs', MAME_TW_PREFIX . '-file' => $name . '.log', MAME_TW_PREFIX . '-name' => 'logs' ], get_admin_url() );
                    echo Html::a( $name, $url ) . '<br>';
//                    }
                }
                ?>
            </div>
            <?php
        }
    }

    public function display_systeminfo_field( $field )
    {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $field[ 'id' ] ); ?>"><?php echo esc_html( $field[ 'title' ] ); ?></label>
            </th>
            <td class="mametw-systeminfo forminp forminp-<?php echo sanitize_title( $field[ 'type' ] ) ?>">

                <div style="margin-bottom:1em;"><span
                            class="description"><?php echo esc_html( $field[ 'desc' ] ); ?></span>
                </div>

                <div class="mametw-systeminfo-table">

                    <?php $systeminfo = $this->get_system_info(); ?>

                    <!-- Site info -->
                    <h4><?php _e( 'Site information', 'mametwint' ); ?></h4>

                    <?php $this->systeminfo_table_cell( $systeminfo[ 'site' ] ); ?>

                    <!-- License info -->
                    <h4><?php _e( 'License information', 'mametwint' ); ?></h4>

                    <?php $this->systeminfo_table_cell( $systeminfo[ 'license' ] ); ?>

                    <!-- WordPress info -->
                    <h4><?php _e( 'WordPress', 'mametwint' ); ?></h4>

                    <?php $this->systeminfo_table_cell( $systeminfo[ 'wordpress' ] ); ?>

                    <!-- Server info -->
                    <h4><?php _e( 'Server information', 'mametwint' ); ?></h4>

                    <?php $this->systeminfo_table_cell( $systeminfo[ 'server' ] ); ?>

                    <!-- PHP info -->
                    <h4><?php _e( 'PHP information', 'mametwint' ); ?></h4>

                    <?php $this->systeminfo_table_cell( $systeminfo[ 'php' ] ); ?>

                    <!-- TWINT info -->
                    <h4><?php _e( 'TWINT information', 'mametwint' ); ?></h4>

                    <?php $this->systeminfo_table_cell( $systeminfo[ 'twint' ] ); ?>

                </div>
            </td>
        </tr>
        <?php
    }

    public function display_setup_assistant_field()
    {
        $step = get_option( MAME_TW_PREFIX . '_setup_step' );
        if ( !$step ) {
            $step = 1;
        }

        $licensing = Licensing_Handler::get_instance();

        $gateway_name = '\\Mame_Twint\\gateway\\WC_Gateway_Twint';
        $gateway      = new $gateway_name();

        $args = [
            'page'            => $step,
            'license'         => $licensing->get_license(),
            'gateway_enabled' => $gateway->enabled == 'yes',
        ];

        mame_twint_get_template( 'setup-assistant.php', $args );

    }

    /**
     * Add "Enroll cash register" field.
     *
     * @param $field
     */
    public static function display_register_enroll_field( $field )
    {
        ?>
        <tr valign="top" class="<?= $field[ 'class' ] ?>">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $field[ 'id' ] ); ?>"><?php echo esc_html( $field[ 'title' ] ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo sanitize_title( $field[ 'type' ] ) ?>">
                <?php static::get_register_enroll_field(); ?>
            </td>
        </tr>
        <?php
    }

    /**
     * The cash register enroll field content.
     */
    public static function get_register_enroll_field()
    {
        wp_nonce_field( 'mametw_enroll_nonce', 'mametw_enroll_nonce' );
        ?>
        <div class="mametw-regenroll-field-wrapper">
            <div>
                <input class="button" type="submit" name="mametw_enroll_register"
                       value="<?php echo __( 'Enroll', 'mametwint' ) ?>"/>
                <p class="description"> <?php _e( 'The (virtual) cash register has to be enrolled once before you can use TWINT on the checkout page. If you change the register ID you will have to enroll the cash register again. If enrolment fails either store UUID or certificate password is not correct or the certificate has not been correctly converted.', 'mametwint' ); ?></
            </div>
        </div>
        <?php
    }

    /**
     * @param $field
     */
    public static function display_renew_certificate_field( $field )
    {
        ?>
        <tr valign="top" class="<?= $field[ 'class' ] ?>">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $field[ 'id' ] ); ?>"><?php echo esc_html( $field[ 'title' ] ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo sanitize_title( $field[ 'type' ] ) ?>">
                <div class="mametw-renew-certificate-field-wrapper">
                    <div><?php
                        $expiry = Admin_Ajax::get_certificate_expiry_date();

                        if ( !empty( $expiry ) && $expiry[ 'status' ] ) {
                            ?>
                            <p>
                                <strong><?= sprintf( __( 'Certificate expires %1$s', 'mametwint' ), $expiry[ 'expiry' ] ) ?></strong>
                            </p><br>
                            <?php
                        } else {
                            ?>
                            <input class="button" type="submit" name="mametw_check_certificate_expiration"
                                   value="<?php echo __( 'Check expiry date', 'mametwint' ) ?>"/>
                            <?php
                        }
                        ?>

                        <input class="button" type="submit" name="mametw_renew_certificate"
                               value="<?= $field[ 'title' ] ?>"/>
                        <p class="description"> <?= $field[ 'desc' ]; ?></
                    </div>
                </div>
            </td>
        </tr>
        <?php
    }

    public function systeminfo_table_cell( $systeminfo )
    {
        foreach ( $systeminfo as $info ) { ?>

            <div class="info">
                <div class="title">
                    <p><?php echo $info[ 'title' ]; ?></p>
                </div>
                <div class="description">
                    <p><?php echo $info[ 'info' ]; ?></p>
                </div>
            </div>

        <?php }
    }

    public function update_systeminfo_option()
    {

    }

    public function get_system_info()
    {

        $systeminfo = array();

        // Site info.
        $systeminfo[ 'site' ] = array(
            'site_url'  => array(
                'title' => __( 'Site URL', 'mametwint' ),
                'info'  => get_site_url(),
            ),
            'home_url'  => array(
                'title' => __( 'Home URL', 'mametwint' ),
                'info'  => get_home_url(),
            ),
            'multisite' => array(
                'title' => __( 'Multisite', 'mametwint' ),
                'info'  => is_multisite() ? __( 'Yes', 'mametwint' ) : __( 'No', 'mametwint' ),
            ),
        );

        $license_info = Licensing_Handler::get_option( MAME_TW_PREFIX . '_license_options', get_current_blog_id() );

        // License info.
        $systeminfo[ 'license' ] = array(
            'status'           => array(
                'title' => __( 'License status', 'mametwint' ),
                'info'  => $license_info[ 'status' ],
            ),
            'license_key'      => array(
                'title' => __( 'License key', 'mametwint' ),
                'info'  => $license_info[ 'license_key' ],
            ),
            'expiration'       => array(
                'title' => __( 'Expiration', 'mametwint' ),
                'info'  => isset( $license_info[ 'expires' ] ) ? date( 'd.m.Y, H:i:s', strtotime( $license_info[ 'expires' ] ) ) : '',
            ),
            'limit'            => array(
                'title' => __( 'License limit', 'mametwint' ),
                'info'  => isset( $license_info[ 'license_limit' ] ) ?: '',
            ),
            'site_count'       => array(
                'title' => __( 'Site count', 'mametwint' ),
                'info'  => isset( $license_info[ 'site_count' ] ) ?: '',
            ),
            'activations_left' => array(
                'title' => __( 'Activations left', 'mametwint' ),
                'info'  => isset( $license_info[ 'activations_left' ] ) ?: '',
            ),
            'customer_email'   => array(
                'title' => __( 'Customer email', 'mametwint' ),
                'info'  => isset( $license_info[ 'customer_email' ] ) ?: '',
            ),

        );

        global $wpdb;

        $systeminfo[ 'server' ] = array(
            'server'        => array(
                'title' => __( 'Server', 'mametwint' ),
                'info'  => $_SERVER[ 'SERVER_SOFTWARE' ]
            ),
            'php_version'   => array(
                'title' => __( 'PHP Version', 'mametwint' ),
                'info'  => phpversion(),
            ),
            'mysql_version' => array(
                'title' => __( 'MySQL Version', 'mametwint' ),
                'info'  => $wpdb->db_version(),
            ),
        );

        $systeminfo[ 'wordpress' ] = array(
            'server'      => array(
                'title' => __( 'Version', 'mametwint' ),
                'info'  => get_bloginfo( 'version' ),
            ),
            'php_version' => array(
                'title' => __( 'Memory limit', 'mametwint' ),
                'info'  => WP_MEMORY_LIMIT,
            ),
        );

        $systeminfo[ 'php' ] = array(
            'memory_limit'       => array(
                'title' => __( 'memory_limit', 'mametwint' ),
                'info'  => ini_get( 'memory_limit' ),
            ),
            'max_execution_time' => array(
                'title' => __( 'max_execution_time', 'mametwint' ),
                'info'  => ini_get( 'max_execution_time' ),
            ),
            'upload_size'        => array(
                'title' => __( 'upload_max_filesize', 'mametwint' ),
                'info'  => ini_get( 'upload_max_filesize' ),
            ),
            'post_max_size'      => array(
                'title' => __( 'post_max_size', 'mametwint' ),
                'info'  => ini_get( 'post_max_size' ),
            ),
            'max_input_vars'     => array(
                'title' => __( 'max_input_vars', 'mametwint' ),
                'info'  => ini_get( 'max_input_vars' ),
            ),
            'soap_enabled'       => array(
                'title' => __( 'soap enabled', 'mametwint' ),
                'info'  => extension_loaded( 'soap' ) ? __( 'Yes', 'mametwint' ) : __( 'No', 'mametwint' ),
            ),
            'openssl_enabled'    => array(
                'title' => __( 'openssl enabled', 'mametwint' ),
                'info'  => MAME_TW_OPENSSL_ACTIVE ? __( 'Yes', 'mametwint' ) : __( 'No', 'mametwint' ),
            ),
        );

        $expiry = Admin_Ajax::get_certificate_expiry_date();

        $systeminfo[ 'twint' ] = [
            'certificate_expiry' => [
                'title' => __( 'Certificate expiry date', 'mametwint' ),
                'info'  => !empty( $expiry ) && $expiry[ 'status' ] ? $expiry[ 'expiry' ] : __( 'No information', 'mametwint' ),
            ]
        ];

        return $systeminfo;
    }

    private function display_modal()
    {
        ?>
        <div id="mame_tw-modal" class="hidden">
            <div id="mame_tw-modal-content">
                <p class="title"></p>
                <p class="text"></p>
                <button class="button" id="mame_tw-modal-cancel"><?php _e( 'Cancel', 'dhuett' ); ?></button>
                <button class="button button-primary"
                        id="mame_tw-modal-confirm"><?php _e( 'Confirm', 'dhuett' ); ?></button>
            </div>
        </div>
        <?php
    }

    private function is_setup_complete()
    {
        return get_option( MAME_TW_PREFIX . '_setup_done' );
    }

}