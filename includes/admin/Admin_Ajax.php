<?php

namespace Mame_Twint\admin;

use Mame_Twint\exceptions\SoapNotLoadedException;
use Mame_Twint\exceptions\TwintCredentialsNotSetException;
use Mame_Twint\gateway\Twint_Data;
use Mame_Twint\lib\Helper;
use Mame_Twint\lib\Json_Response;
use Mame_Twint\lib\Log;
use Mame_Twint\lib\Twint_Json_Response;
use Mame_Twint\lib\updates\Licensing_Handler;
use Mame_Twint\lib\WC_Helper;
use Mame_Twint\services\Logger;
use Mame_Twint\services\Mailer;
use Mame_Twint\services\TransactionHandler;
use Mame_Twint\TWINT;
use Mame_Twint\Twint_Helper;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Ajax
{
    public static function init()
    {
        // Register AJAX handler.
        add_action( 'wp_ajax_mametw_ajax', __CLASS__ . '::ajax_handler' );

        // Settings option update hook.
        add_filter( 'woocommerce_admin_settings_sanitize_option', __CLASS__ . '::update_enroll_register_option', 10, 3 );

        // Checks if the cashier register can be enrolled.
        add_action( MAME_TW_PREFIX . '_check_register_status_daily', __CLASS__ . '::check_register_status' );

        add_action( MAME_TW_PREFIX . '_check_register_status_single', __CLASS__ . '::check_register_status' );

        add_action( MAME_TW_PREFIX . '_automatically_renew_certificate', __CLASS__ . '::automatically_renew_certificate' );
        add_action( MAME_TW_PREFIX . '_check_certificate_status', __CLASS__ . '::check_certificate_status' );
    }

    /**
     * The AJAX handler for the "enroll cash register" call.
     */
    public static function ajax_handler()
    {
        if ( !check_ajax_referer( 'mame_tw_license_nonce', 'security' ) ) {
            return;
        }

        switch ( $_POST[ MAME_TW_PREFIX . '_action' ] ) {
            case 'enroll':

                $response = static::enroll_register();

                $json_response = new Twint_Json_Response();
                $json_response->enroll_response( $response[ 'status' ], $response[ 'message' ] )->respond();

                break;

            case 'save_file':
                static::save_file( sanitize_textarea_field( $_POST[ 'content' ] ) );
                break;

            case 'toggle_enable':

                $gateway_name = '\\Mame_Twint\\gateway\\WC_Gateway_Twint';
                $gateway      = new $gateway_name();
                $enabled      = $gateway->enabled;

                $json_response = new Twint_Json_Response();
                if ( $gateway->update_option( 'enabled', $enabled == 'yes' ? 'no' : 'yes' ) ) {
                    $elem = '#' . MAME_TW_PREFIX . '-enable-gateway-toggle';
                    if ( $enabled == 'yes' ) {
                        $json_response->add_class( $elem, 'woocommerce-input-toggle--disabled' )->remove_class( $elem, 'woocommerce-input-toggle--enabled' );
                    } else {
                        $json_response->add_class( $elem, 'woocommerce-input-toggle--enabled' )->remove_class( $elem, 'woocommerce-input-toggle--disabled' );
                    }
                }

                $json_response->respond();

                break;

            case 'convert':

                $result = static::convert_cert_file( $_POST[ 'attachment_id' ] );

                $json_response = new Twint_Json_Response();
                $json_response->enroll_response( $result[ 'status' ], $result[ 'message' ] )->respond();

                break;

            case 'check_order_status':

                $response      = static::check_order_status();
                $json_response = new Twint_Json_Response();
                $json_response->enroll_response( $response[ 'status' ], $response[ 'message' ] );
                $json_response->html( '#mame_tw-order-status .mametw-table-data', $response[ 'order_status' ] );
                $json_response->html( '#mame_tw-status-reason .mametw-table-data', $response[ 'status_reason' ] );
                $json_response->respond();

                break;

            case 'settle_transaction':

                $json_response = new Twint_Json_Response();

                $response = static::settle_transaction();

                if ( !$response[ 'status' ] ) {
                    $json_response->status( false )->respond();
                }

                $json_response->status( true )->message( __( 'Successfully settled transaction.', 'mametwint' ) );
                $json_response->html( '#mame_tw-order-status .mametw-table-data', $response[ 'order_status' ] );
                $json_response->html( '#mame_tw-status-reason .mametw-table-data', $response[ 'status_reason' ] );
                $json_response->hide( '#' . MAME_TW_PREFIX . '-settle-transaction-btn' );
                $json_response->hide( '#' . MAME_TW_PREFIX . '-cancel-transaction-btn' );
                $json_response->append( '#' . MAME_TW_PREFIX . '-transactions .mametw-table-data table tbody', '<tr><td>' . __( '(Settlement)', 'mametwint' ) . '</td><td></td><td></td><td>' . $response[ 'fee' ] . '</td></tr>' );
                $json_response->respond();

                break;

            case 'cancel_transaction':

                $json_response = new Twint_Json_Response();

                $response = static::cancel_transaction();

                if ( !$response[ 'status' ] ) {
                    $json_response->status( false )->respond();
                }

                $json_response->status( true )->message( __( 'Successfully cancelled transaction.', 'mametwint' ) );
                $json_response->html( '#mame_tw-order-status .mametw-table-data', $response[ 'order_status' ] );
                $json_response->html( '#mame_tw-status-reason .mametw-table-data', $response[ 'status_reason' ] );
                $json_response->hide( '#' . MAME_TW_PREFIX . '-settle-transaction-btn' );
                $json_response->hide( '#' . MAME_TW_PREFIX . '-cancel-transaction-btn' );
                $json_response->append( '#' . MAME_TW_PREFIX . '-transactions .mametw-table-data table tbody', '<tr><td>' . __( '(Cancellation)', 'mametwint' ) . '</td><td></td><td></td><td>' . $response[ 'fee' ] . '</td></tr>' );
                $json_response->respond();

                break;

            case 'renew_certificate':

                $renewal_allowed = static::check_if_renewal_allowed();

                $json_response = new Twint_Json_Response();

                if ( !$renewal_allowed[ 'status' ] ) {
                    $json_response->enroll_response( false, $renewal_allowed[ 'message' ] )->respond();
                }

                try {
                    $response = static::renew_certificate();
                } catch ( TwintCredentialsNotSetException $e ) {

                    $json_response->enroll_response( false, __( 'TWINT credentials are missing (certificate, certificate password).', 'mametwint' ) )->respond();
                    return;
                } catch ( SoapNotLoadedException $e ) {
                    $json_response->enroll_response( false, __( 'SoapClient not active on the server.', 'mametwint' ) )->respond();

                }

                $json_response->enroll_response( $response[ 'status' ], $response[ 'message' ] )->respond();
                break;

            case 'check_expiry':

                $json_response = new Twint_Json_Response();
                $response      = static::get_certificate_expiry_date();

                if ( $response[ 'status' ] ) {
                    $json_response->enroll_response( true, sprintf( __( 'Certificate expires %1$s', 'mametwint' ), $response[ 'expiry' ] ) )->respond();
                }

                $json_response->enroll_response( false, $response[ 'message' ] )->respond();

                break;


            case 'save_setup_step':

                $params = [];
                parse_str( $_POST[ 'form' ], $params );

                $page = intval( $_POST[ 'page' ] );

                $json_response    = new Twint_Json_Response();
                $errors_container = '#' . MAME_TW_PREFIX . '-setup-assistant-errors';

                switch ( $page ) {

                    case 1:

                        $license_key = $params[ 'license_key' ];

                        // Activate license.
                        $licensing_handler = Licensing_Handler::get_instance();
                        $result            = $licensing_handler->activate_license( $license_key, get_current_blog_id() );

                        if ( !$result[ 'status' ] ) {

                            $json_response->status( false )->errors( $errors_container, [ $result[ 'message' ] ] )->respond();
                        }

                        update_option( MAME_TW_PREFIX . '_setup_step', 2 );
                        update_option( MAME_TW_PREFIX . '_setup_done', false );

                        $json_response->hide( '#mame_tw-license-invalid' );
                        $json_response->show( '#mame_tw-license-valid' );
                        $json_response->text( '#mame_tw-current-license-key', $license_key );
                        $json_response->status( $result[ 'status' ] )->message( $result[ 'message' ] )->respond();

                        break;

                    case 2:

                        $errors = [];

                        if ( !isset( $params[ 'store_uuid' ] ) || empty ( $params[ 'store_uuid' ] ) ) {
                            $errors[] = __( 'Please provide a Store UUID.', 'mametwint' );
                        } elseif ( !Helper::is_v4_uuid( $params[ 'store_uuid' ] ) ) {
                            $errors[] = __( 'Wrong Store UUID format. UUID must be of the form xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx' );
                        }

                        if ( !isset( $params[ 'certificate_password' ] ) || empty ( $params[ 'certificate_password' ] ) ) {
                            $errors[] = __( 'Please provide a certificate password.', 'mametwint' );
                        }

                        if ( !isset( $params[ 'register_id' ] ) || empty ( $params[ 'register_id' ] ) ) {
                            $errors[] = __( 'Please provide a cash register ID.', 'mametwint' );
                        }

                        if ( !empty( $errors ) ) {
                            $json_response->status( false )->errors( $errors_container, $errors )->respond();
                        }

                        update_option( 'mametw_settings_uuid', $params[ 'store_uuid' ] );
                        update_option( 'mametw_settings_certpw', $params[ 'certificate_password' ] );
                        update_option( 'mametw_settings_registerid', $params[ 'register_id' ] );

                        update_option( MAME_TW_PREFIX . '_setup_step', 3 );
                        update_option( MAME_TW_PREFIX . '_setup_done', false );

                        $json_response->status( true )->message( __( 'Successfully saved credentials.', 'mametwint' ) );
                        $json_response->attr( '#mametw_settings_uuid', 'value', $params[ 'store_uuid' ] );
                        $json_response->attr( '#mametw_settings_registerid', 'value', $params[ 'register_id' ] );
                        $json_response->attr( '#mametw_settings_certpw', 'value', $params[ 'certificate_password' ] );
                        $json_response->respond();

                        break;

                    case 3:

                        $result = static::convert_cert_file( $params[ 'attachment_id' ] );

                        if ( !$result[ 'status' ] ) {

                            $json_response->status( false )->errors( $errors_container, [ $result[ 'message' ] ] )->respond();
                        }

                        update_option( MAME_TW_PREFIX . '_setup_step', 4 );
                        update_option( MAME_TW_PREFIX . '_setup_done', false );

                        $json_response->status( $result[ 'status' ] )->message( $result[ 'message' ] )->respond();

                        break;

                    case 4:

                        $result = static::enroll_register();

                        if ( !$result[ 'status' ] ) {

                            $json_response->status( false )->errors( $errors_container, [ $result[ 'message' ] ] )->respond();
                        }

                        update_option( MAME_TW_PREFIX . '_setup_step', 1 );
                        update_option( MAME_TW_PREFIX . '_setup_done', true );

                        $json_response->status( $result[ 'status' ] )->message( $result[ 'message' ] )->respond();

                        break;

                    default:

                        break;
                }


                break;

            case 'cancel_setup':

                update_option( MAME_TW_PREFIX . '_setup_step', 1 );
                update_option( MAME_TW_PREFIX . '_setup_done', true );

                $json_response = new Json_Response();
                $json_response->status( true )->respond();

                break;

            default:
                break;
        }
    }

    /**
     * @param $attachment_id
     * @return array
     */
    private static function convert_cert_file( $attachment_id )
    {
        $attachment = get_attached_file( $attachment_id );

        if ( empty( $attachment ) ) {
            return [ 'status' => false, 'message' => __( 'No file found', 'mametwint' ) ];
        }

        $filetype = wp_check_filetype( $attachment );
        $password = get_option( 'mametw_settings_certpw' );

        if ( empty( $password ) ) {
            return [ 'status' => false, 'message' => __( 'Please enter and save password first before uploading the certificate', 'mametwint' ) ];
        }

        $password = stripcslashes( $password );

        $file_content = file_get_contents( $attachment );
        if ( !$file_content ) {
            $file_content = readfile( $attachment );
        }

        if ( !$file_content ) {
            return [ 'status' => false, 'message' => __( 'Failed to read file.', 'mametwint' ) ];
        }

        switch ( $filetype[ 'ext' ] ) {

            case 'p12':
            case 'pfx':

                $result = static::convert_p12_to_pem( $file_content, $password );

                if ( !$result[ 'status' ] ) {
                    return [ 'status' => false, 'message' => $result[ 'message' ] ];
                }

                $file = $result[ 'file' ];

                break;

            case 'txt':

                $file = $file_content;

                break;

            default:
                return [ 'status' => false, 'message' => __( 'Filetype not supported. Please upload a p12 or txt file.', 'mametwint' ) ];
        }

        Twint_Helper::save_certificate_file( $file );

        wp_delete_attachment( $attachment_id );

        return [ 'status' => true, 'message' => __( 'File successfully converted.', 'mametwint' ) ];
    }

    private static function convert_p12_to_pem( $p12_content, $password )
    {
        if ( !MAME_TW_OPENSSL_ACTIVE ) {
            return [ 'status' => false, 'message' => __( 'The openssl extension is not active on your server. Either activate it or upload the converted certificate in txt format.', 'mametwint' ) ];
        }

        $results = [];
        if ( !openssl_pkcs12_read( $p12_content, $results, $password ) ) {
            return [ 'status' => false, 'message' => sprintf( __( 'Failed to parse certificate file. Please make sure that the password is correct. Provided password: %1$s', 'mametwint' ), $password ) ];
        }

        $key = null;
        if ( !openssl_pkey_export( $results[ 'pkey' ], $key, $password ) ) {
            return [ 'status' => false, 'message' => __( 'Unable to extract private key.', 'mametwint' ) ];
        }

        $cert = null;
        if ( !openssl_x509_export( $results[ 'cert' ], $cert ) ) {
            return [ 'status' => false, 'message' => __( 'Unable to extract export certificate', 'mametwint' ) ];
        }

        $file = $key . $cert;

        return [ 'status' => true, 'file' => $file ];
    }

    /**
     * @param $content
     */
    public static function save_file( $content )
    {
        $successful = Twint_Helper::save_certificate_file( $content );
        $status     = false;
        $message    = __( 'Failed to create file', 'mametwint' );

        if ( $successful ) {
            $status  = true;
            $message = __( 'File saved.', 'mametwint' );
        }

        ob_clean();
        echo json_encode( array( 'status' => $status, 'message' => $message ) );
        die();
    }

    /**
     * Enrolls the register for non-AJAX calls.
     */
    public static function update_enroll_register_option( $value, $option, $raw_value )
    {
        if ( $option[ 'type' ] == 'twintenroll' ) {

            if ( !check_admin_referer( 'mametw_enroll_nonce', 'mametw_enroll_nonce' ) ) {
                return;
            }

            if ( isset( $_POST[ 'mametw_enroll_register' ] ) ) {

                static::enroll_register();
            }
        }

        return $value;
    }

    /**
     * Enroll the cash register.
     *
     * Set $ajax to true if AJAX request.
     *
     * @return array
     */
    public static function enroll_register()
    {
        $twint = TWINT::create_for_admin( null, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return [ 'status' => false, 'message' => __( 'Error. Please check the logs.', 'mametwint' ) ];
        }

        return $twint->transactionHandler->enroll_cashier_register();
    }

    public static function check_order_status()
    {
        $order_id   = $_POST[ 'order_id' ];
        $order_uuid = Twint_Data::get_order_data( $order_id, 'order_uuid' );

        if ( empty( $order_uuid ) ) {
            return [ 'status' => false, 'message' => __( 'Order UUID missing', 'mametwint' ), 'type' => 'client' ];
        }


        $twint = TWINT::create_for_admin( $order_id, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return [ 'status' => false, 'message' => __( 'Error. Please check the logs.', 'mametwint' ) ];
        }

        $response = $twint->transactionHandler->check_order_status( $order_uuid );

        if ( !$response[ 'status' ] ) {
            return $response;
        }

        $order_status  = $response[ 'order_status' ];
        $status_reason = $response[ 'status_reason' ];

        Twint_Data::save_order_data( $order_id, [ 'status' => $order_status, 'status_reason' => $status_reason ] );

        $order = wc_get_order( $order_id );
        if ( $order && !WC_Helper::is_order_complete( $order ) && $order_status == TransactionHandler::TWINT_STATUS_SUCCESS && $status_reason == TransactionHandler::TWINT_REASON_ORDER_OK ) {
            WC_Helper::update_order_successful( $order );
        }

        return [ 'status' => true, 'message' => sprintf( __( 'Status: %1$s. Reason: %2$s.', 'mametwint' ), $order_status, $status_reason ), 'order_status' => $order_status, 'status_reason' => $status_reason ];
    }

    private static function settle_transaction()
    {
        $order_id = $_POST[ 'order_id' ];

        $twint = TWINT::create_for_admin( $order_id, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return [ 'status' => false, 'message' => __( 'Error. Please check the logs.', 'mametwint' ) ];
        }

        $response = $twint->transactionHandler->confirm_transaction();

        if ( $response[ 'status' ] ) {
            $order = wc_get_order( $order_id );
            WC_Helper::update_order_meta( $order, MAME_TW_PREFIX . '_transaction_settled', true );
            WC_Helper::update_order_successful( $order );
        }

        return $response;
    }

    private static function cancel_transaction()
    {
        $order_id = $_POST[ 'order_id' ];

        $twint = TWINT::create_for_admin( $order_id, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return [ 'status' => false, 'message' => __( 'Error. Please check the logs.', 'mametwint' ) ];
        }

        return $twint->transactionHandler->cancel_transaction();
    }

    /**
     * Checks the status of the register and sends an email if the register is not available or cannot be enrolled.
     */
    public static function check_register_status()
    {
        // Check if the register has been at least enrolled once.
        $enrolled_once = get_option( 'mametw_register_status' );
        if ( !$enrolled_once ) {
            return;
        }

        $response = static::enroll_register();

        if ( !$response[ 'status' ] ) {

            $count = get_option( MAME_TW_PREFIX . '_register_check_count', 1 );

            Logger::log_error( 'Register status check failed. Attempt: ' . $count, $response );

            if ( $count < MAME_TW_TIMES_RETRY_SYSTEM_CHECK ) {

                if ( !wp_next_scheduled( MAME_TW_PREFIX . '_check_register_status_single' ) ) {
                    wp_schedule_single_event( time() + ($count * MAME_TW_SYSTEM_CHECK_RELATIVE_INTERVAL * 60), MAME_TW_PREFIX . '_check_register_status_single' );
                }

                $count++;
                update_option( MAME_TW_PREFIX . '_register_check_count', $count );

            } else {

                delete_option( MAME_TW_PREFIX . '_register_check_count' );

                Mailer::send_system_check_failed_email( $response );
            }

        } else {

            delete_option( MAME_TW_PREFIX . '_register_check_count' );

        }
    }

    public static function check_successful_order_statuses()
    {
        Logger::log_event( 'check_successful_order_statuses', 'start' );

        $key                 = '_' . MAME_TW_PREFIX . '_payment_successful';
        $payment_started_key = '_' . MAME_TW_PREFIX . '_payment_initiated';

        $now      = time();
        $min_time = 60 * MAME_TW_ORDER_CHECK_MIN_TIME;
        $max_time = 60 * MAME_TW_ORDER_CHECK_MAX_TIME;

        $args = [
            'limit'      => MAME_TW_ORDER_CHECK_NUM_POSTS,
            'status'     => [ 'wc-pending' ],
            'meta_query' => array(
                array(
                    'key'     => $key,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => $payment_started_key,
                    'value'   => $now - $max_time,
                    'compare' => '>',
                ),
                array(
                    'key'     => $payment_started_key,
                    'value'   => $now - $min_time,
                    'compare' => '<',
                ),

            ),
            'date_query' => array(
                array(
                    'column' => 'date_created',
                    'after'  => '-' . MAME_TW_ORDER_CHECK_MAX_TIME . ' minutes',
                )
            ),
        ];

        $orders = wc_get_orders( $args );

        if ( !empty( $orders ) ) {

            foreach ( $orders as $order ) {

                $order_id = $order->get_id();
                Logger::log_event( 'cron:check_successful_order_statuses', 'Checking ' . $order_id, $order_id );

                // Get order from TWINT
                $twint    = TWINT::create_for_transaction( $order_id, MAME_TW_MERCHANT_UUID );
                $response = $twint->transactionHandler->get_order();
                if ( !$response ) {
                    Logger::log_error( 'cron:check_successful_order_statuses', 'Empty response.', $order_id );
                    continue;
                }

                $status = $response->Order->Status->Status->_;
                $reason = $response->Order->Status->Reason->_;
                $twint->dataProvider->save_order_data( [ 'status' => $status, 'status_reason' => $reason ] );

                $result = $twint->transactionHandler->handle_order_status( $response->Order );

                Logger::log_event( 'cron:check_successful_order_statuses', sprintf( 'Successfully updated paid order %1$s status. Result: %2$s', $order_id, json_encode( $result ) ), $order_id );
            }
        }
    }

    /**
     * Returns an array containing expiry data:
     * ['status', 'expiry', 'renewal_allowed']
     * If status is false:
     * ['status', 'message']
     *
     * @return array
     */
    public static function get_certificate_expiry_date()
    {
        $twint = TWINT::create_for_admin( null, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return [ 'status' => false, 'message' => __( 'Error. Please check the logs.', 'mametwint' ) ];
        }

        return $twint->transactionHandler->get_certificate_expiry_date();
    }

    /**
     * Called by a scheduled action to automatically renew the certificate
     */
    public static function automatically_renew_certificate()
    {
        $renew_certificate_automatically = get_option( 'mametw_automatically_renew_certificate' );
        if ( !isset( $renew_certificate_automatically ) || !$renew_certificate_automatically ) {
            return;
        }

        $renewal_allowed = static::check_if_renewal_allowed();

        if ( !$renewal_allowed[ 'status' ] ) {
            Logger::log_error( 'Certificate renewal failed', $renewal_allowed[ 'message' ] );
            return;
        }

        try {
            $result = static::renew_certificate();
        } catch ( TwintCredentialsNotSetException $e ) {
            Logger::log_error( 'TwintCredentialsNotSetException', $e->getMessage() );
            return;
        } catch ( SoapNotLoadedException $e ) {
            Logger::log_error( 'SoapNotLoadedException', $e->getMessage() );
            return;
        }

        if ( !$result[ 'status' ] ) {

            if ( isset( $result[ 'response' ] ) ) {
                Mailer::send_certificate_renewal_failed_email( $result[ 'response' ], $renewal_allowed[ 'expiry' ] );
            }
        }
    }

    /**
     * Renews the certificate if it can already be renewed.
     *
     * @return array
     * @throws SoapNotLoadedException
     * @throws TwintCredentialsNotSetException
     */
    public static function renew_certificate()
    {
        $twint = TWINT::create_for_admin( null, MAME_TW_MERCHANT_UUID );

        if ( !$twint ) {
            return [ 'status' => false, 'message' => __( 'Error. Please check the logs.', 'mametwint' ) ];
        }

        // Exception handled in create_admin_transaction_handler().
        return $twint->transactionHandler->renew_certificate();
    }

    /**
     * Checks the status of the certificate and sends an email if it expires soon or if it has already expired.
     */
    public static function check_certificate_status()
    {
        $expiry = static::get_certificate_expiry_date();

        if ( !$expiry[ 'status' ] ) {
            return;
        }

        $timestamp = strtotime( $expiry[ 'expiry' ] );
        $now       = time();

        $difference = $timestamp - $now;

        $days_before_expiring = 5;

        if ( $difference <= 0 ) {
            // Expired
            Mailer::send_certificate_expired_email();
        } elseif ( $difference < 60 * 60 * 24 * $days_before_expiring ) {
            // Expires in 5 days
            Mailer::send_certificate_expires_soon_email( $days_before_expiring );
        }
    }

    /**
     * @return array
     */
    private static function check_if_renewal_allowed()
    {
        $expiry = static::get_certificate_expiry_date();

        if ( !$expiry[ 'status' ] ) {
            return $expiry;
        }

        if ( $expiry[ 'renewal_allowed' ] ) {
            return [ 'status' => true ];
        }

        return [ 'status' => false, 'message' => __( 'Renewal not allowed yet. Renewals are not allowed earlier than 60 days before expiration.', 'mametwint' ), 'expiry' => $expiry[ 'expiry' ] ];
    }

}












