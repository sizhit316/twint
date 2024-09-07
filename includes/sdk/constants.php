<?php

//define( 'MAME_TW_DEBUG', false );

use Mame_Twint\Twint_Helper;

define( 'MAME_TW_LOGGER_CLASS', 'Mame_Twint\\services\\Logger' );
define( 'MAME_TW_REST_HELPER_CLASS', 'Mame_Twint\\services\\RESTHelper' );
define( 'MAME_TW_MAILER_CLASS', 'Mame_Twint\\services\\Mailer' );
define( 'MAME_TW_DB_LOCK_CLASS', 'Mame_Twint\\lib\\WC_DB_Lock' );

define( 'MAME_TW_TIMEOUT', 180 );
define( 'MAME_TW_SOAP_INTERVAL', 2 );
define( 'MAME_TW_LOCKFILES_LOCATION', Twint_Helper::get_locks_dir() );
//define( 'MAME_TW_OPENSSL_ACTIVE', extension_loaded( 'openssl' ) );

define( 'MAME_TW_CERTIFICATE_NAME', 'twint.pem' );
define( 'MAME_TW_CASHREGISTER_ID', Twint_Helper::get_cash_register_id() );
define( 'MAME_TW_CERTIFICATE_PATH', Twint_Helper::get_uploads_dir() . MAME_TW_CERTIFICATE_NAME );
define( 'MAME_TW_CERTIFICATE_PASS', Twint_Helper::get_certificate_password() );
define( 'MAME_TW_MERCHANT_UUID', Twint_Helper::get_merchant_uuid() );
define( 'MAME_TW_CONNECTION_TIMEOUT', Twint_Helper::get_connection_timeout() );
define( 'MAME_TW_MAX_LOCK_TIME', 120 ); // seconds
define( 'MAME_TW_MAX_EXEC_TIME', 30 );

define( 'MAME_TW_PROXY_HOST', get_option( 'mametw_settings_prget_optionoxyhost' ) );
define( 'MAME_TW_PROXY_PORT', get_option( 'mametw_settings_proxyport' ) );

define( 'MAME_TW_TWINT_PM_PAGE', true );