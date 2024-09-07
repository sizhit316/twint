<?php

namespace Mame_Twint\lib\updates;

use Mame_Twint\lib\Json_Response;
use Mame_Twint\lib\WP_Helper;
use Mame_Twint\services\Logger;

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Licensing_Handler
 * @package Mame_Twint\lib\updates
 */
class Licensing_Handler
{
    const REQUEST_TIMEOUT = 45;

    private        $prefix;
    private        $tab_name;
    private        $plugin_name;
    private        $plugin_display_name;
    private        $plugin_url;
    private        $plugin_path;
    private        $settings_tab;
    private static $instance;

    public function __construct()
    {
        $this->prefix              = MAME_TW_PREFIX;
        $this->plugin_name         = MAME_TW_PLUGIN_NAME;
        $this->plugin_display_name = MAME_TW_PLUGIN_DISPLAY_NAME;
        $this->tab_name            = 'twint';
        $this->plugin_url          = MAME_TW_UPDATE_URL;
        $this->plugin_path         = MAME_TW_PLUGIN_DIRNAME;
        $this->settings_tab        = true;

        add_action( 'wp_ajax_' . $this->prefix . '_ajax_license', array( $this, 'ajax_handler' ) );

        // Licensing notices.
        add_action( 'admin_notices', array( $this, 'license_notice' ) );
        add_action( 'network_admin_notices', array( $this, 'license_notice' ) );

        if ( $this->settings_tab && MAME_WC_ACTIVE ) {

            // Define custom licensing field.
            add_action( 'woocommerce_admin_field_' . $this->prefix . '_licensing', array( $this, 'display_licensing_field' ) );

            // Save custom licensing field.
//            add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'update_licensing_option' ), 10, 3 );
        }
    }

    public function network_valid_license( $force_update = true )
    {
        if ( !is_multisite() )
            return $this->valid_license();

        if ( !$force_update ) {
            $network_valid = get_site_option( MAME_TW_PREFIX . '_licence_network_valid', 0 );

            if ( $network_valid !== 0 ) {
                return $network_valid;
            }
        }

        $network_active = false;
        $valid          = true;

        $plugins = get_site_option( 'active_sitewide_plugins' );
        foreach ( $plugins as $key => $value ) {
            $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $key );
            if ( $data[ 'Name' ] === $this->plugin_display_name )
                $network_active = true;
        }

        global $wpdb;
        $blogs = $wpdb->get_results( "
        SELECT blog_id
        FROM {$wpdb->blogs}
        WHERE site_id = '{$wpdb->siteid}'
        AND spam = '0'
        AND deleted = '0'
        AND archived = '0'
    " );

        if ( $network_active ) {
            foreach ( $blogs as $blog ) {
                if ( !$this->valid_license( $blog->blog_id ) ) {
                    $valid = false;
                    break;
                }
            }
        } else {

            foreach ( $blogs as $blog ) {
                $plugins = get_blog_option( $blog->blog_id, 'active_plugins' );

                foreach ( $plugins as $key => $value ) {
                    $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $value );
                    if ( $data[ 'Name' ] === $this->plugin_display_name ) {
                        if ( !$this->valid_license( $blog->blog_id ) ) {
                            $valid = false;
                            break;
                        }
                        continue;
                    }
                }
            }
        }

        update_site_option( MAME_TW_PREFIX . '_licence_network_valid', $valid );

        return $valid;
    }

    /**
     * Get single instance of this class.
     *
     * @return Licensing_Handler
     */
    public static function get_instance()
    {
        if ( empty( static::$instance ) ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function ajax_handler()
    {
        if ( !check_ajax_referer( $this->prefix . '_license_nonce', 'security', false, false ) ) {
            return;
        }
        $action = $_POST[ 'license_action' ];
        if ( $action == 'activate' ) {
            $response      = $this->activate_license( $_POST[ 'license' ], get_current_blog_id() );
            $json_response = new Json_Response();
            $json_response->status( $response[ 'status' ] )->add_attribute( 'message', $response[ 'message' ] )->respond();
        } elseif ( $action == 'deactivate' ) {
            $response      = $this->deactivate_license( get_current_blog_id() );
            $json_response = new Json_Response();
            $json_response->status( $response[ 'status' ] )->add_attribute( 'message', $response[ 'message' ] )->respond();
        } elseif ( $action == 'check' ) {
            $response      = $this->check_license( get_current_blog_id() );
            $json_response = new Json_Response();
            $json_response->status( $response[ 'status' ] )->add_attribute( 'message', $response[ 'message' ] )->respond();
        } elseif ( $action == 'notice' ) {
            $response      = $this->ignore_notice();
            $json_response = new Json_Response();
            $json_response->status( $response[ 'status' ] )->respond();
        }
    }

    public function license_notice()
    {
        if ( is_network_admin() ) {
            if ( !$this->network_valid_license( false ) ) {
                echo '<div class="error"><p>';
                printf( __( 'The license for %1$s has not been activated for all sites that use the plugin.', 'mametwint' ), $this->plugin_name );
                echo '</p></div>';
            }
        } else {
            if ( !$this->valid_license( get_current_blog_id() ) ) {
                echo '<div class="error"><p>';
                $url = $this->settings_tab ? admin_url( 'admin.php?page=wc-settings&tab=' . $this->tab_name ) : admin_url( 'admin.php?page=' . $this->prefix . '_menu_settings' );
                printf( __( 'License for %1$s is invalid or missing. <a href="%2$s">ACTIVATE HERE</a>.', 'mametwint' ), $this->plugin_name, $url );
                echo '</p></div>';
            }
        }
    }

    /**
     * Dismiss notice
     */
    public function ignore_notice()
    {
        global $current_user;
        $user_id = $current_user->ID;

        update_user_meta( $user_id, $this->prefix . '_notice_ignore', true );

        return [ 'status' => 'true' ];
    }

    /**
     * Display custom WooCommerce admin field type 'licensing'.
     *
     * woocommerce_admin_field_{licensing}
     * @param $field
     */
    public function display_licensing_field( $field )
    {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $field[ 'id' ] ); ?>"><?php echo esc_html( $field[ 'title' ] ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo sanitize_title( $field[ 'type' ] ) ?>">
                <?php $this->license_activation_field() ?>
            </td>
        </tr>
        <?php

    }

    /**
     * Choose action (activate/deactivate) based on POST action.
     *
     * @param $value
     * @param$option
     * @param $raw_value
     * @return mixed
     */
    public function update_licensing_option( $value, $option, $raw_value )
    {
        return $value;
    }

    /**
     * Display the licensing field. Activate/check the license.
     *
     * @param string $message
     */
    public function license_activation_field( $message = '' )
    {
        wp_nonce_field( $this->prefix . '_license_nonce', $this->prefix . '_license_nonce' );

        $license_options = $this->get_license_options( get_current_blog_id() );
        ?>

        <div id="<?= $this->prefix ?>-license-field-wrapper" class="mame-license-field-wrapper">
            <?php $license_valid = $this->valid_license( get_current_blog_id() );
            ?>
            <div id="<?= $this->prefix ?>-license-invalid"
                 class="mame-license-invalid-msg" <?php echo $license_valid ? 'style="display:none;"' : '' ?>>
                <div>
                    <p class="description"
                       style="margin-bottom:0.5em"><?php printf( __( 'Enter your license code to receive updates and support for the %1$s plugin', 'mametwint' ), $this->plugin_name ); ?></p>

                </div>
                <input id="<?= $this->prefix ?>_license_key" name="<?= $this->prefix ?>_license_key"
                       class="mame-license-input" size="70"
                       type="text"
                       value="<?php echo $license_options && isset( $license_options[ 'license_prev' ] ) ? $license_options[ 'license_prev' ] : '' ?>"/>
                <input type="submit" name="<?= $this->prefix ?>-license-activate-btn"
                       class="button button-primary <?= $this->prefix ?>-license-activate-btn"
                       value="<?php echo __( 'Activate', 'mametwint' ) ?>"/>
            </div>

            <div
                    id="<?= $this->prefix ?>-license-valid"
                    class="mame-license-valid-msg" <?php echo $license_valid ? 'class="' . $this->prefix . '-check-license"' : 'style="display:none;"' ?>>
                <p style="margin-bottom:0.5em"><?php echo __( 'License: ', 'mametwint' ); ?><span
                            id="<?= $this->prefix ?>-current-license-key"><?php echo $this->get_license( get_current_blog_id() ); ?></span>
                </p>
                <input type="submit" name="<?= $this->prefix ?>_license_deactivate"
                       class="button mame-admin-button red <?= $this->prefix ?>-license-deactivate-btn"
                       value="<?php echo __( 'Deactivate', 'mametwint' ) ?>"/>
                <input type="submit" name="<?= $this->prefix ?>-license-check-btn"
                       class="button <?= $this->prefix ?>-license-check-btn"
                       value="<?php echo __( 'Check license', 'mametwint' ) ?>"/>
            </div>
            <?php
            ?>
            <p id="<?= $this->prefix ?>-license-status-message"
               class="mame-license-status-msg invalid"><?php echo $message; ?></p>
        </div>
        <div id="<?= $this->prefix ?>-loader" class="mame-loader"><img
                    src="<?= MAME_TW_PLUGIN_URL . '/assets/images/loader.gif' ?>"></div>
        <div>
            <span class="description"><?php printf( __( 'You can manage your license in <a href="%1$s" target="_blank"> your account at mamedev.ch</a>.', 'mametwint' ), 'http://mamedev.ch/checkout/purchase-history/' ); ?></span>
        </div>
        <?php

    }

    /**
     * License activation.
     *
     * @param $license
     * @param int $blog_id
     * @return array
     */
    public function activate_license( $license, $blog_id = 1 )
    {
        $api_params = array(
            'edd_action' => 'activate_license',
            'license'    => $license,
            'item_name'  => urlencode( $this->plugin_name ),
            'url'        => WP_Helper::get_blog_option($blog_id, 'home'),
        );

        $response = wp_remote_post( $this->plugin_url, array(
            'timeout'   => static::REQUEST_TIMEOUT,
            'sslverify' => false,
            'body'      => $api_params
        ) );

        $status = false;

        if ( is_wp_error( $response ) ) {

            $error_string = $response->get_error_message();
            Logger::log_error( 'License activation request: ', $api_params );
            Logger::log_error( 'License activation response: ', $response );

            $message = sprintf( __( 'Error: %1$s.', 'mametwint' ), $error_string );

        } else {

            $license_data = json_decode( wp_remote_retrieve_body( $response ) );

            if ( isset( $license_data->success ) ) {

                $status = $license_data->license == 'valid';

                if ( !$license_data->success || !$status ) {

                    $message = sprintf( __( 'Error: %1$s<br>License: %2$s<br>Expires: %3$s<br>Activations left: %4$s<br>API URL: %5$s<br>Requested domain: %6$s<br>Item name: %7$s', 'mametwint' ), $license_data->error, $license, $license_data->expires, $license_data->activations_left, $this->plugin_url, WP_Helper::get_blog_option($blog_id, 'home'), urlencode( $this->plugin_name ) );

                    // Errors
                    Logger::log_error( 'License activation request: ', $api_params );
                    Logger::log_error( 'License activation response: ', $response );

                } else {

                    $license_options                       = static::get_option( $this->prefix . '_license_options', $blog_id );
                    $license_options[ 'license_status' ]   = true;
                    $license_options[ 'license_key' ]      = sanitize_text_field( $license );
                    $license_options[ 'license_prev' ]     = sanitize_text_field( $license );
                    $license_options[ 'status' ]           = $license_data->license;
                    $license_options[ 'expires' ]          = $license_data->expires;
                    $license_options[ 'license_limit' ]    = $license_data->license_limit;
                    $license_options[ 'site_count' ]       = $license_data->site_count;
                    $license_options[ 'activations_left' ] = $license_data->activations_left;
                    $license_options[ 'customer_email' ]   = $license_data->customer_email;

                    static::update_option( $this->prefix . '_license_options', $license_options, $blog_id );

                    $message = __( 'License activation successful.', 'mametwint' );

                }

            } else {
                $message = __( 'Unable to activate license.', 'mametwint' );
                $message .= '<br>' . sprintf( __( 'Data: %1$s', 'mametwint' ), json_encode( $license_data ) );
                Logger::log_error( 'License activation request: ', $api_params );
                Logger::log_error( 'License activation license data: ', $license_data );
                Logger::log_error( 'License activation response: ', json_encode( $response ) );
            }

        }

        $this->network_valid_license();

        return [ 'status' => $status, 'message' => $message ];
    }

    /**
     * License deactivation
     *
     * @param int $blog_id
     * @return array
     */
    public function deactivate_license( $blog_id = 1 )
    {
        $license = $this->get_license( get_current_blog_id() );

        $api_params = array(
            'edd_action' => 'deactivate_license',
            'license'    => $license,
            'item_name'  => $this->plugin_name,
            'url'        => WP_Helper::get_blog_option($blog_id, 'home'),
        );

        $response = wp_remote_post( $this->plugin_url, array(
            'body'      => $api_params,
            'timeout'   => static::REQUEST_TIMEOUT,
            'sslverify' => false
        ) );

        $status = false;

        if ( is_wp_error( $response ) ) {

            $error_string = $response->get_error_message();
            Logger::log_error( 'License deactivation request: ', $api_params );
            Logger::log_error( 'License deactivation response: ', $response );

            $message = sprintf( __( 'Error: %1$s.', 'mametwint' ), $error_string );

        } else {

            $license_data = json_decode( wp_remote_retrieve_body( $response ) );

            if ( isset( $license_data->success ) ) {

                $status = $license_data->license == 'deactivated';

                if ( !$license_data->success || !$status ) {

                    $message = sprintf( __( 'Error: %1$s<br>License: %2$s<br>Expires: %3$s<br>API URL: %4$s<br>Requested domain: %5$s<br>Item name: %6$s', 'mametwint' ), $license_data->error, $license, $license_data->expires, $this->plugin_url, WP_Helper::get_blog_option($blog_id, 'home'), urlencode( $this->plugin_name ) );

                    // Errors
                    Logger::log_error( 'License deactivation request: ', $api_params );
                    Logger::log_error( 'License deactivation response: ', $response );

                } else {

                    $license_options                     = static::get_option( $this->prefix . '_license_options', $blog_id );
                    $license_options[ 'license_status' ] = false;
                    $license_options[ 'license_key' ]    = '';
                    $license_options[ 'status' ]         = $license_data->license;
                    $license_options[ 'expires' ]        = $license_data->expires;

                    static::update_option( $this->prefix . '_license_options', $license_options, $blog_id );

                    $message = __( 'License deactivation successful.', 'mametwint' );
                }

            } else {
                $message = __( 'Unable to deactivate license.', 'mametwint' );
                $message .= '<br>' . sprintf( __( 'Data: %1$s', 'mametwint' ), json_encode( $license_data ) );
                Logger::log_error( 'License activation request: ', $api_params );
                Logger::log_error( 'License activation response: ', $license_data );
            }

        }

        $this->network_valid_license();

        return [ 'status' => $status, 'message' => $message ];
    }

    public static function get_option( $option, $blog_id = null )
    {
        if ( is_multisite() ) {
            return get_blog_option( $blog_id ?: 1, $option );

        } else {
            return get_option( $option );
        }
    }

    public static function update_option( $option, $value, $blog_id = null )
    {
        if ( is_multisite() ) {
            update_blog_option( $blog_id ?: 1, $option, $value );

        } else {
            update_option( $option, $value );
        }
    }

    /**
     * Check if license is valid.
     *
     * @param int $blog_id
     * @return array
     */
    public function check_license( $blog_id = 1 )
    {
        $license = $this->get_license( get_current_blog_id() );

        $api_params = array(
            'edd_action' => 'check_license',
            'license'    => $license,
            'item_name'  => urlencode( $this->plugin_name ),
            'url'        => WP_Helper::get_blog_option($blog_id, 'home'),
        );
        $response   = wp_remote_get( add_query_arg( $api_params, $this->plugin_url ), array(
            'timeout'   => static::REQUEST_TIMEOUT,
            'sslverify' => false
        ) );

        $status = false;

        if ( is_wp_error( $response ) ) {

            $error_string = $response->get_error_message();
            Logger::log_error( 'License check request: ', $api_params );
            Logger::log_error( 'License check response: ', $response );

            $message = sprintf( __( 'Error: %1$s.', 'mametwint' ), $error_string );

        } else {

            $license_data = json_decode( wp_remote_retrieve_body( $response ) );

            if ( isset( $license_data->success ) ) {

                $status = $license_data->license == 'valid';

                if ( !$license_data->success || !$status ) {

                    $license_options                       = static::get_option( $this->prefix . '_license_options', $blog_id );
                    $license_options[ 'license_status' ]   = false;
                    $license_options[ 'license_key' ]      = '';
                    $license_options[ 'status' ]           = $license_data->license;
                    $license_options[ 'expires' ]          = $license_data->expires;
                    $license_options[ 'license_limit' ]    = $license_data->license_limit;
                    $license_options[ 'site_count' ]       = $license_data->site_count;
                    $license_options[ 'activations_left' ] = $license_data->activations_left;
                    $license_options[ 'customer_email' ]   = $license_data->customer_email;

                    static::update_option( $this->prefix . '_license_options', $license_options, $blog_id );

                    $message = sprintf( __( 'Licence invalid: %1$s<br>License: %2$s<br>Expires: %3$s<br>Activations left: %4$s<br>API URL: %5$s<br>Requested domain: %6$s<br>Item name: %7$s', 'mametwint' ), $license_data->error, $license, $license_data->expires, $license_data->activations_left, $this->plugin_url, WP_Helper::get_blog_option($blog_id, 'home'), urlencode( $this->plugin_name ) );

                    // Errors
                    Logger::log_error( 'License check request: ', $api_params );
                    Logger::log_error( 'License check response: ', $response );

                } else {

                    $message = sprintf( __( 'Licence valid<br>License: %1$s<br>Expires: %2$s<br>Activations left: %3$s<br>API URL: %4$s<br>Licensed domain: %5$s<br>Item name: %6$s', 'mametwint' ), $license, $license_data->expires, $license_data->activations_left, $this->plugin_url, WP_Helper::get_blog_option($blog_id, 'home'), urlencode( $this->plugin_name ) );
                }

            } else {
                $message = __( 'Unable to check license.', 'mametwint' );
                $message .= '<br>' . sprintf( __( 'Data: %1$s', 'mametwint' ), json_encode( $license_data ) );
                Logger::log_error( 'License check request: ', $api_params );
                Logger::log_error( 'License check response: ', $license_data );
            }

        }

        $this->network_valid_license();

        return [ 'status' => $status, 'message' => $message ];
    }

    public function get_license_options( $blog_id = 1 )
    {
        return static::get_option( $this->prefix . '_license_options', $blog_id );
    }

    /**
     * Return license key.
     *
     * @param int $blog_id
     * @return bool
     */
    public function get_license( $blog_id = 1 )
    {
        $license_options = $this->get_license_options( $blog_id );

        if ( $license_options ) {
            return $license_options[ 'license_key' ];
        }

        return false;
    }

    /**
     * Is the license valid?
     *
     * @param int $blog_id
     * @return bool
     */
    public function valid_license( $blog_id = 1 )
    {
        $license_options = $this->get_license_options( $blog_id );

        if ( $license_options ) {
            return $license_options[ 'license_status' ];
        }

        return false;
    }

    /**
     * Add network options for multisite installations.
     */
    public function register_network_settings()
    {
        register_setting( $this->prefix . '-network-settings-group', $this->prefix . '_network_license_key', array( $this, 'sanitize_network_options' ) );
    }

    /**
     * Sanitize the network settings.
     *
     * @param $input
     *
     * @return mixed
     */
    public function sanitize_network_options( $input )
    {
        $input = sanitize_text_field( $input );

        return $input;
    }

    /**
     * Add the network settings submenu page.
     */
    public function network_settings()
    {
        add_submenu_page( 'settings.php', __( $this->plugin_name . ' Network Settings', 'mametwint' ), __( $this->plugin_name, 'mametwint' ), 'manage_options', $this->prefix . '-network-settings', array( $this, 'display_network_settings' ) );
    }

    public function display_network_settings()
    {
        ?>
        <h1><?php _e( $this->plugin_name . ' license key', 'mametwint' ); ?></h1>
        <form action="settings.php?page=<?= $this->tab_name ?>-network-settings" method="post">
            <table>
                <tr valign="top">
                    <th scope="row" class="titledesc">

                    </th>
                    <td class="forminp forminp-licensing">

                        <?php $this->license_activation_field() ?>

                    </td>
                </tr>
            </table>
        </form>
        <?php
    }
}