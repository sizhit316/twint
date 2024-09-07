<?php

namespace Mame_Twint\admin;

use Mame_Twint\Globals;
use Mame_Twint\Twint_Helper;

class DB_Updater
{
    public static function init()
    {
        add_action( 'wp_ajax_' . MAME_TW_PREFIX . '_db_update', __CLASS__ . '::ajax_apply_db_update' );
        add_action( 'admin_notices', __CLASS__ . '::show_update_admin_notice' );
        add_action( 'init', __CLASS__ . '::apply_db_update' );
    }

    /**
     * Displays the DB update notice if there is a newer version.
     */
    public static function show_update_admin_notice()
    {
        $last_db_version = get_option( MAME_TW_PREFIX . '_db_version' );

        if ( empty( $last_db_version ) ) {
            // Initial setup.
            Globals::create_dirs();;
            update_option( MAME_TW_PREFIX . '_db_version', MAME_TW_DB_VERSION );
            return;
        }

        if ( version_compare( MAME_TW_DB_VERSION, $last_db_version, ">" ) ) {

            $progress = get_option( MAME_TW_PREFIX . '_db_update_progress_' . MAME_TW_DB_VERSION );

            if ( !$progress ) {
                $class   = 'notice notice-warning';
                $message = __( 'Please update the database of the TWINT plugin.', 'mametwint' );
                $url     = add_query_arg( [ MAME_TW_PREFIX . "-update" => '1' ], get_admin_url() );
                printf( '<div id="' . MAME_TW_PREFIX . '-update-notice" class="%1$s"><p>%2$s</p><a href="' . $url . '" id="' . MAME_TW_PREFIX . '-update-db-btn">%3$s</a></div>', esc_attr( $class ), esc_html( $message ), __( 'Update now', 'mametwint' ) );

            } elseif ( $progress == 'processing' ) {
                $class   = 'notice notice-info';
                $message = __( 'The TWINT database update is in progress. The update is complete when this message disappears', 'mametwint' );
                printf( '<div id="' . MAME_TW_PREFIX . '-update-notice" class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
            }
        }
    }

    /**
     * Applies the TWINT database update.
     */
    public static function apply_db_update()
    {
        if ( current_user_can( 'administrator' ) ) {
            if ( isset( $_GET[ MAME_TW_PREFIX . "-update" ] ) ) {
                self::update_db();
            }
        }
    }

    /**
     * Applies the TWINT database updat via AJAX request.
     */
    public static function ajax_apply_db_update()
    {
        if ( !check_ajax_referer( MAME_TW_PREFIX . '_update_nonce', 'security' ) )
            return;

        self::update_db();
    }

    /**
     * Contains the database update.
     */
    private static function update_db()
    {
        $progress = get_option( MAME_TW_PREFIX . '_db_update_progress_' . MAME_TW_DB_VERSION );
        if ( !$progress && version_compare( MAME_TW_DB_VERSION, get_option( MAME_TW_PREFIX . '_db_version' ), ">" ) ) {

            flush_rewrite_rules();
            update_option( MAME_TW_PREFIX . '_db_update_progress_' . MAME_TW_DB_VERSION, 'processing' );

            Globals::create_dirs();;

            if ( version_compare( get_option( MAME_TW_PREFIX . '_db_version' ), '2.0.4', "<" ) ) {
                wp_unschedule_hook( 'mame_tw_check_payment_status' );
                wp_unschedule_hook( 'mame_tw_check_payment_status_successful' );
                wp_unschedule_hook( 'mametw_check_license' );
                wp_unschedule_hook( 'wp_logging_prune_routine' );

                // Batch job to delete logs
                $query_args = array(
                    // 'post_parent'    => 0,
                    'post_type'      => 'wp_log',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish'
                );

                $logs                 = new \WP_Query( $query_args );
                $number_of_logs       = (int)( $logs->post_count );
                $number_of_batch_jobs = ( (int)( $number_of_logs / 100 ) ) + 1;

                $background_process = new Delete_Logs_Background_Process();
                for ( $i = $number_of_batch_jobs; $i > 0; $i-- ) {
                    $background_process->push_to_queue( $i );
                }

                $background_process->save()->dispatch();
            }

            // Since v1.0.4
            if ( version_compare( get_option( MAME_TW_PREFIX . '_db_version' ), '1.0.4', "<" ) ) {

                $dir = Twint_Helper::get_uploads_dir() . 'temp' . DIRECTORY_SEPARATOR;
                if ( !file_exists( $dir ) ) {
                    mkdir( $dir, 0775 );
                    file_put_contents( $dir . '.htaccess', 'deny from all' );
                }
            }

            // Since v1.0.3
            if ( version_compare( get_option( MAME_TW_PREFIX . '_db_version' ), '1.0.3', "<" ) ) {

                $password = get_option( 'mametw_settings_certpw' );
                if ( !empty( $password ) ) {
                    $password = stripcslashes( $password );
                    $password = addcslashes( $password, "\\'\"!~@#%^*_+-={}[]:,./`$&()|;<>?" );
                    update_option( 'mametw_settings_certpw', $password );
                }
            }

            update_option( MAME_TW_PREFIX . '_db_update_progress_' . MAME_TW_DB_VERSION, 'complete' );
            update_option( MAME_TW_PREFIX . '_db_version', MAME_TW_DB_VERSION );

        }
    }

    /**
     * Notice is displayed when the update was successful.
     */
    public static function success_notice()
    {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?= __( 'Database update successful.', 'mametwint' ) ?></p>
        </div>
        <?php
    }

}

