<?php

define( 'MAME_TW_PLUGIN_VERSION', '5.5.1' );
define( 'MAME_TW_DB_VERSION', '2.0.5' );
define( 'MAME_TW_UPDATE_URL', 'http://www.mamedev.ch' );
define( 'MAME_TW_PLUGIN_NAME', 'TWINT for WooCommerce' );
define( 'MAME_TW_PLUGIN_DISPLAY_NAME', 'mame TWINT for WooCommerce' );
define( 'MAME_TW_SOFTWARE_NAME', 'mame TWINT for WooCommerce' );

defined( 'MAME_TW_ENVIRONMENT' ) || define( 'MAME_TW_ENVIRONMENT', 'prod' );

define( 'MAME_TW_DEBUG', false );
define( 'MAME_TW_PREFIX', 'mame_tw' );
define( 'MAME_TW_PLUGIN_DIRNAME', basename( dirname( dirname( __FILE__ ) ) ) ) ;
define( 'MAME_TW_OPENSSL_ACTIVE', extension_loaded( 'openssl' ) );
define( 'MAME_TW_PLUGIN_DOWNLOAD_ID', 751 );

define( 'MAME_TW_PLUGIN_PATH', dirname( dirname( __FILE__ ) ) );
define( 'MAME_TW_PLUGIN_URL', plugin_dir_url( __DIR__ ) );
define( 'MAME_TW_PLUGIN_FILE', dirname( dirname( __FILE__ ) ) . '/mame-twint-woocommerce.php' );
define( 'MAME_TW_UPLOAD_DIR', wp_upload_dir()[ 'basedir' ] . '/mame_twint/' );

//define( 'MAME_TW_MAX_LOG_FILE_SIZE', 2 ); // In MB.
define( 'MAME_TW_MAX_NUM_LOG_FILES', 30 );

// Payment
define( 'MAME_TW_CHECK_PAYMENT_SUCCESS_AFTER', 300 );

// Logs
define( 'MAME_TW_DELETE_LOGS_AFTER', get_option( 'mametw_delete_logs_after' ) ?: 30 ); // days

// System check
define( 'MAME_TW_TIMES_RETRY_SYSTEM_CHECK', 3 );
define( 'MAME_TW_SYSTEM_CHECK_RELATIVE_INTERVAL', 2 );

define( 'MAME_TW_DEFAULT_SYSTEM_CHECK_INTERVAL', 24 ); // Hours
$interval = get_option( 'mametw_system_check_interval' );
if ( !$interval || $interval < 1 ) {
    $interval = MAME_TW_DEFAULT_SYSTEM_CHECK_INTERVAL;
}
define( 'MAME_TW_SYSTEM_CHECK_INTERVAL', $interval );

define( 'MAME_TW_ORDER_CHECK_INTERVAL', get_option( 'mametw_order_check_interval' ) ?: 15 );
define( 'MAME_TW_ORDER_CHECK_MIN_TIME', 5 );
define( 'MAME_TW_ORDER_CHECK_MAX_TIME', 120 );
define( 'MAME_TW_ORDER_CHECK_NUM_POSTS', 10 );

define( 'MAME_TW_DEFAULT_ASYNC_REQUEST_IMMEDIATE', 'yes' );
define( 'MAME_TW_DEFAULT_ASYNC_REQUEST_SHUTDOWN', 'no' );
define( 'MAME_TW_DEFAULT_ASYNC_REQUEST_AJAX', 'yes' );
define( 'MAME_TW_DEFAULT_ASYNC_REQUEST_CRON', 'yes' );
define( 'MAME_TW_DEFAULT_SOAP_INTERVAL', 5 );

define( 'MAME_TW_DEFAULT_STATUS_ORDER', 'processing' );
define( 'MAME_TW_DEFAULT_STATUS_VIRTUAL', 'completed' );
define( 'MAME_TW_DEFAULT_STATUS_DEFERRED', 'on-hold' );
define( 'MAME_TW_DEFAULT_CONNECTION_TIMEOUT', 25 );

define( 'MAME_TW_TEMPLATES_PATH', MAME_TW_PLUGIN_PATH . '/templates/' );