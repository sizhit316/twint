<?php

namespace Mame_Twint;

use Mame_Twint\admin\Delete_Logs_Background_Process;
use Mame_Twint\admin\File_Manager;
use Mame_Twint\admin\Settings_Tab;
use Mame_Twint\admin\DB_Updater;
use Mame_Twint\admin\Admin_Ajax;
use Mame_Twint\admin\Metabox;
use Mame_Twint\gateway\Frontend_Ajax;
use Mame_Twint\gateway\Twint_Transaction_Async_Task;
use Mame_Twint\gateway\Twint_Transaction_Async_Task_Immediate;
use Mame_Twint\lib\Html;
use Mame_Twint\lib\Log;
use Mame_Twint\lib\updates\Licensing_Handler;
use Mame_Twint\lib\updates\Plugin_Updater;
use Mame_Twint\lib\WC_Helper;
use Mame_Twint\lib\WP_Helper;
use Mame_Twint\services\EventHandler;
use Mame_Twint\wc_blocks\WC_Blocks_Payment_Method_Type;

/**
 * Class Plugin_Init
 * @package Mame_Twint
 */
class Plugin_Init
{
    /**
     * Plugin_Init constructor.
     */
    public function __construct()
    {
        include_once 'constants.php';
        include_once plugin_dir_path( __FILE__ ) . 'lib/template-functions.php';

        if ( MAME_TW_OPENSSL_ACTIVE ) {
            add_filter( 'upload_mimes', [ $this, 'upload_mimes' ] );
        }

        register_activation_hook( MAME_TW_PLUGIN_FILE, [ $this, 'on_activation' ] );
        register_deactivation_hook( MAME_TW_PLUGIN_FILE, [ $this, 'on_deactivation' ] );

        add_filter( 'wp_logging_should_we_prune', [ $this, 'activate_pruning' ], 10 );

        // Admin styles.
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_styles' ] );

        add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );

        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_twint_gateway' ] );
        add_filter( 'woocommerce_available_payment_gateways', [ $this, 'conditionalLy_show_payment_gateway' ], 10, 1 );

        add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );

        // Thickbox
        add_action( 'admin_action_' . MAME_TW_PREFIX . '_backtrace_modal', 'Mame_Twint\admin\Log_List_Table::render_thickbox_backtrace_modal' );

        add_action( 'woocommerce_blocks_loaded', [ $this, 'woocommerce_blocks_support' ] );

        add_action( 'init', [ $this, 'download_file' ] );
        add_action( 'init', [ $this, 'print_file' ] );

        add_action( 'woocommerce_api_' . MAME_TW_PREFIX . '_webhook_oun', 'Mame_Twint\gateway\WC_Gateway_Twint::handle_webhook_order_update_notification' );
        add_action( 'woocommerce_api_' . MAME_TW_PREFIX . '_webhook_redirect', 'Mame_Twint\gateway\WC_Gateway_Twint::handle_webhook_redirect' );
        add_action( 'woocommerce_api_' . MAME_TW_PREFIX . '_webhook_cancel', 'Mame_Twint\gateway\WC_Gateway_Twint::handle_webhook_cancel' );
        add_action( 'wp_async_twint_monitor_order', 'Mame_Twint\gateway\WC_Gateway_Twint::monitor_order_background_task', 10, 3 );
        add_action( 'wp_async_twint_monitor_order_immediate', 'Mame_Twint\gateway\WC_Gateway_Twint::monitor_order_background_task', 10, 3 );

        add_action( MAME_TW_PREFIX . '_check_order_statuses', 'Mame_Twint\admin\Admin_Ajax::check_successful_order_statuses' ); // Runs the cron job.
        add_action( MAME_TW_PREFIX . '_check_num_logs', [ $this, 'check_num_logs' ] );
        add_action( MAME_TW_PREFIX . '_bg_cron_task_single', 'Mame_Twint\gateway\WC_Gateway_Twint::run_scheduled_background_task', 10, 1 );

        add_action( 'http_api_curl', [ $this, 'curl_ipv4' ], 10, 1 );
    }

    private function include_files()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        spl_autoload_register( function ( $class_name ) {

            if ( false === strpos( $class_name, 'Mame_Twint' ) ) {
                return;
            }

            $class_name = str_replace( "\\", DIRECTORY_SEPARATOR, str_replace( 'Mame_Twint', '', $class_name ) );
            $class_name = DIRECTORY_SEPARATOR . 'includes' . $class_name;

            $file_path = dirname( dirname( __FILE__ ) ) . $class_name . '.php';
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }

        } );

        require_once(plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . 'sdk' . DIRECTORY_SEPARATOR . 'includes.php');
    }

    /**
     * @param $mimes
     * @return mixed
     */
    public function upload_mimes( $mimes )
    {
        if ( current_user_can( 'administrator' ) ) {
            $mimes[ 'p12' ] = 'application/pkcs-12';
            $mimes[ 'pfx' ] = 'application/x-pkcs12';
            $mimes[ 'pem' ] = 'application/x-pem-file';
            $mimes[ 'txt' ] = 'text/plain';
        }
        return $mimes;
    }

    public function on_activation()
    {
        $license_options = get_option( MAME_TW_PREFIX . '_license_options' );
        if ( !$license_options ) {

            $license_options[ 'license_key' ]    = '';
            $license_options[ 'license_status' ] = false;

            update_option( MAME_TW_PREFIX . '_license_options', $license_options );

        }

        flush_rewrite_rules();
    }

    public function on_deactivation()
    {
        // Don't ignore the license notice.
        global $current_user;
        $user_id = $current_user->ID;
        delete_user_meta( $user_id, 'mametw_notice_ignore' );

        $licensing = new Licensing_Handler();
        $licensing->network_valid_license();
    }

    private function initialize()
    {
        $log_level = get_option( 'mametw_log' ) ?? 'all';
        if ( $log_level == 'yes' ) {
            $log_level = 'all'; // Fallback for older versions.
        }
        defined( 'MAME_TW_DEBUG_LOG' ) || define( 'MAME_TW_DEBUG_LOG', $log_level );

        // Schedule pruning.
        $scheduled = wp_next_scheduled( 'wp_logging_prune_routine' );
        if ( $scheduled == false ) {
            wp_schedule_event( time(), 'hourly', 'wp_logging_prune_routine' );
        }

//        Order_Ajax_Handler::init();
        EventHandler::init_actions();
    }

    public function activate_pruning( $should_we_prune )
    {
        return true;
    }

    public function admin_styles()
    {
        $current_screen = get_current_screen();

        if ( is_admin() && (
                $current_screen->id == WC_Helper::get_wc_screen()
                || $current_screen->id == WC_Helper::get_wc_screen( 'shop_subscription' )
                || $current_screen->base == 'woocommerce_page_wc-settings'
            ) ) {
            wp_enqueue_style( 'mametw_admin_style', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin-styles.css', [], MAME_TW_PLUGIN_VERSION );

            wp_register_script( 'twint', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/twint.js', [ 'jquery' ], MAME_TW_PLUGIN_VERSION );
            wp_localize_script( 'twint', 'twintVars', array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'ajaxNonce' => wp_create_nonce( 'mame_tw_license_nonce' ),
                'prefix'    => MAME_TW_PREFIX,
                'texts'     => [
                    'renewCertificateTitle'      => __( 'Renew the certificate?', 'mametwint' ),
                    'renewCertificateText'       => __( 'If you have multiple shops connected to the same TWINT account please remember to copy the twint.pem file from the folder <strong>wp-content/uploads/mame_twint</strong> to all other installations after the certificate renewal.', 'mametwint' ),
                    'licenseActivationFailed'    => __( 'The license activation failed. Please check the logs.', 'mametwint' ),
                    'licenseDeactivationFailed'  => __( 'The license deactivation failed. Please check the logs.', 'mametwint' ),
                    'licenseCheckFailed'         => __( 'The license check failed. Please check the logs.', 'mametwint' ),
                    'confirmCloseSetupAssistant' => __( 'Are you sure you want to cancel the setup? You can always restart the setup by clicking \'Start setup assistant \' in the settings.', 'mametwint' ),
                    'confirmSettlement'          => __( 'Are you sure you want to confirm the transaction?', 'mametwint' ),
                    'confirmCancellation'        => __( 'Are you sure you want to cancel the transaction?', 'mametwint' ),
                ]
            ) );
            wp_enqueue_script( 'twint' );
        }
    }

    public function on_plugins_loaded()
    {
        $this->include_files();
        $this->initialize();
        if ( !class_exists( '\\SoapClient' ) ) {
            // Show notice
            add_action( 'admin_notices', array( $this, 'soap_not_enabled_notice' ) );
            add_action( 'network_admin_notices', array( $this, 'soap_not_enabled_notice' ) );
            return;
        }

        Frontend_Ajax::init();

        new Delete_Logs_Background_Process();
        DB_Updater::init();

        Admin_Ajax::init();

        // Async tasks.
        new Twint_Transaction_Async_Task();
        new Twint_Transaction_Async_Task_Immediate();

        if ( !defined( 'MAME_WC_ACTIVE' ) )
            define( 'MAME_WC_ACTIVE', class_exists( 'woocommerce' ) );

        if ( !defined( 'MAME_SUBSCRIPTIONS_ACTIVE' ) )
            define( 'MAME_SUBSCRIPTIONS_ACTIVE', is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) );

        if ( !MAME_WC_ACTIVE ) {
            return;
        }

        // Load localization file.
        load_plugin_textdomain( 'mametwint', false, plugin_basename( dirname( dirname( __FILE__ ) ) . '/localization/' ) );

        add_filter( 'plugin_action_links_' . plugin_basename( MAME_TW_PLUGIN_FILE ), [ $this, 'action_links' ] );

        // Include licensing/updates files.
        $licensing = new Licensing_Handler();

        $license_options = $licensing->network_valid_license( false ) ? Licensing_Handler::get_option( MAME_TW_PREFIX . '_license_options' ) : null;
        if ( isset( $license_options[ 'license_key' ] ) ) {
            $edd_updater = new Plugin_Updater( MAME_TW_UPDATE_URL, MAME_TW_PLUGIN_FILE, array(
                'version'   => MAME_TW_PLUGIN_VERSION,
                'license'   => $license_options[ 'license_key' ],
                'item_name' => MAME_TW_PLUGIN_NAME,
                'author'    => 'mame webdesign hüttig',
                'url'       => WP_Helper::get_blog_option( 1, 'home' ),
                'beta'      => false,
            ) );
        }

        // Include settings and metabox.
        if ( is_admin() && !is_network_admin() ) {

            add_filter( 'woocommerce_get_settings_pages', [ $this, 'add_settings' ] );

            $metabox = new Metabox();
            $metabox->init();
        }

        // Schedules
        if ( !wp_next_scheduled( MAME_TW_PREFIX . '_check_register_status_daily' ) ) {
            wp_schedule_event( time(), MAME_TW_PREFIX . '_systemcheck', MAME_TW_PREFIX . '_check_register_status_daily' );
        }

        if ( !wp_next_scheduled( MAME_TW_PREFIX . '_automatically_renew_certificate' ) ) {
            wp_schedule_event( time(), 'weekly', MAME_TW_PREFIX . '_automatically_renew_certificate' );
        }

        if ( !wp_next_scheduled( MAME_TW_PREFIX . '_check_certificate_status' ) ) {
            wp_schedule_event( time(), 'weekly', MAME_TW_PREFIX . '_check_certificate_status' );
        }

        if ( MAME_TW_ORDER_CHECK_INTERVAL > 0 ) {
            if ( !wp_next_scheduled( MAME_TW_PREFIX . '_check_order_statuses' ) ) {
                wp_schedule_event( time(), MAME_TW_PREFIX . '_order_check', MAME_TW_PREFIX . '_check_order_statuses' );
            }
        }

        // Schedule log files check cron.
        if ( !wp_next_scheduled( MAME_TW_PREFIX . '_check_num_logs' ) ) {
            wp_schedule_event( time(), 'daily', MAME_TW_PREFIX . '_check_num_logs' );
        }

        // IPv6 fix.
        function gw_curl_setopt_ipresolve( $handle )
        {
            curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
            return $handle;
        }

    }

    /**
     * Adds a custom system check schedule.
     *
     * @param $schedules
     * @return mixed
     */
    public function add_cron_schedules( $schedules )
    {
        $system_check_interval                        = MAME_TW_SYSTEM_CHECK_INTERVAL * 60 * 60;
        $schedules[ MAME_TW_PREFIX . '_systemcheck' ] = array(
            'interval' => $system_check_interval,
            'display'  => sprintf( __( 'Every %1$s hours', 'mametwint' ), $system_check_interval ),
        );

        if ( MAME_TW_ORDER_CHECK_INTERVAL > 0 ) {
            $order_check_interval                         = MAME_TW_ORDER_CHECK_INTERVAL * 60;
            $schedules[ MAME_TW_PREFIX . '_order_check' ] = array(
                'interval' => $order_check_interval,
                'display'  => sprintf( __( 'Order check: every %1$s minutes', 'mametwint' ), $order_check_interval ),
            );
        }

        $schedules[ 'weekly' ] = array(
            'interval' => 604800,
            'display'  => __( 'Once weekly' )
        );

        return $schedules;
    }

    /**
     * Add settings action links to plugins page
     *
     * @param array $links
     *
     * @return array
     */
    public function action_links( $links )
    {
        $plugin_links = [
            Html::a( __( 'Settings', 'mametwint' ), admin_url( 'admin.php?page=wc-settings&tab=twint' ) ),
            Html::a( __( 'Documentation', 'mametwint' ), 'https://docs.mamedev.ch/category/twint-for-woocommerce', [], '_blank' ),
        ];

        return array_merge( $plugin_links, $links );
    }

    /**
     * Adds the TWINT payment gateway to WooCommerce.
     *
     * @param $methods
     * @return mixed
     */
    public function add_twint_gateway( $methods )
    {
        // Load the payment gateway.
        include_once(dirname( __FILE__ ) . '/gateway/WC_Gateway_Twint.php');

        $methods[ 'mame_twint' ] = 'Mame_Twint\gateway\WC_Gateway_Twint';

        return $methods;
    }

    /**
     * Show TWINT on the checkout only for CHF based on the settings.
     *
     * @since 5.5.1
     *
     * @param $available_gateways
     * @return mixed
     */
    public function conditionalLy_show_payment_gateway( $available_gateways )
    {
        $check_for_currency = get_option( 'mametw_settings_chf_only' );

        if ( !is_admin() && $check_for_currency == 'yes' && strtolower( get_woocommerce_currency() ) != 'chf' ) {
            unset( $available_gateways[ 'mame_twint' ] );
        }

        return $available_gateways;
    }

    /**
     * @param $settings
     * @return array
     */
    public function add_settings( $settings )
    {
        $settings[] = Settings_Tab::get_instance();

        return $settings;
    }

    /**
     * Notice is displayed if SOAP is not enabled on the server.
     */
    public function soap_not_enabled_notice()
    {
        echo '<div class="error"><p>';
        _e( 'The SOAP extension is not enabled on your server. The payment method TWINT will not work if SOAP is not enabled.', 'mametwint' );
        echo '</p></div>';

    }

    /**
     * Adds support for WooCommerce blocks.
     *
     * @since 3.2.0
     */
    function woocommerce_blocks_support()
    {
        if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {

//                    $payment_method_registry->register( new WC_Blocks_Payment_Method_Type() );

                    $container = \Automattic\WooCommerce\Blocks\Package::container();
                    // registers as shared instance.
                    $container->register(
                        WC_Blocks_Payment_Method_Type::class,
                        function () {

                            return new WC_Blocks_Payment_Method_Type();

                        }
                    );
                    $payment_method_registry->register(
                        $container->get( WC_Blocks_Payment_Method_Type::class )
                    );
                },
                5
            );
        }
    }

    /**
     * Checks the request query args and sends the protected file.
     */
    function download_file()
    {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( isset( $_GET[ MAME_TW_PREFIX . "-download" ] ) && isset( $_GET[ MAME_TW_PREFIX . "-type" ] ) && isset( $_GET[ MAME_TW_PREFIX . "-file" ] ) && isset( $_GET[ MAME_TW_PREFIX . "-name" ] ) ) {
            File_Manager::send_file( $_GET[ MAME_TW_PREFIX . "-file" ], $_GET[ MAME_TW_PREFIX . "-type" ], $_GET[ MAME_TW_PREFIX . "-name" ] );
        }
    }

    /**
     * Opens the print screen of a file.
     */
    function print_file()
    {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( isset( $_GET[ MAME_TW_PREFIX . "-print" ] ) && isset( $_GET[ MAME_TW_PREFIX . "-type" ] ) && isset( $_GET[ MAME_TW_PREFIX . "-file" ] ) ) {

            $link = add_query_arg( [ MAME_TW_PREFIX . '-type' => $_GET[ MAME_TW_PREFIX . '-type' ], MAME_TW_PREFIX . '-file' => $_GET[ MAME_TW_PREFIX . "-file" ], MAME_TW_PREFIX . '-download' => '1' ], get_admin_url() );

            $filename = $_GET[ MAME_TW_PREFIX . "-file" ];
            $ext      = pathinfo( $filename, PATHINFO_EXTENSION );

            if ( $ext === 'pdf' ) {
                File_Manager::display_pdf( $filename, $_GET[ MAME_TW_PREFIX . '-type' ] );
                die();
            }

            ob_start();
            ?>
            <!doctype html>
            <html>
            <head>
            </head>
            <body>

            <img src="<?= $link ?>">
            <script>
                window.onload = function () {
                    window.print();
                }
            </script>
            </body>
            </html>
            <?php
            ob_end_flush();
            die();
        }
    }

    /**
     * Checks number of log files and deletes old logs.
     */
    public function check_num_logs()
    {
        $logs_path = Globals::get_upload_path( 'logs' );
        $files     = glob( $logs_path . "*.log" );

        if ( !empty( $files ) ) {

            foreach ( $files as $file ) {
                $name = basename( $file, '.log' );

                $date  = \DateTime::createFromFormat( 'd-m-Y', $name );
                $today = new \DateTime();

                $interval = date_diff( $date, $today );
                $days     = $interval->format( '%a' );

                if ( (int)$days > MAME_TW_DELETE_LOGS_AFTER ) {
                    unlink( $file );
                }

            }

        }
    }

    /**
     * Force requests via IPv4.
     *
     * @param $handle
     * @return mixed
     */
    public function curl_ipv4( $handle )
    {
        $ip_v4_only = get_option( 'mametw_ipv4_only' );
        if ( $ip_v4_only == 'yes' ) {
            curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
        }
        return $handle;
    }
}