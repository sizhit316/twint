<?php

namespace Mame_Twint;

use Mame_Twint\interfaces\iDataProvider;
use Mame_Twint\interfaces\iLogger;
use Mame_Twint\interfaces\iRESTHelper;
use Mame_Twint\lib\iDB_Lock;
use Mame_Twint\services\AbstractMailer;
use Mame_Twint\services\AdminTransactionHandler;
use Mame_Twint\services\TransactionHandler;

/**
 * Class TWINT
 * @package Mame_Twint
 */
class TWINT
{
    const DATAPROVIDER_CLASS = 'Mame_Twint\\services\\DataProvider';
    const EVENTHANDLER_CLASS = 'Mame_Twint\\services\\EventHandler';
    const RESTHELPER_CLASS   = 'Mame_Twint\\Services\\RESTHElper';
    const LOCK_CLASS         = 'Mame_Twint\\lib\\WC_DB_Lock';

    /** @var TransactionHandler|AdminTransactionHandler */
    public $transactionHandler;

    /** @var iDataProvider */
    public $dataProvider;

    /** @var AbstractMailer */
    public static $mailer = MAME_TW_MAILER_CLASS;

    /** @var iLogger */
    public static $logger = MAME_TW_LOGGER_CLASS;

    /** @var iRESTHelper */
    public static $rest = MAME_TW_REST_HELPER_CLASS;

    /** @var iDB_Lock */
    public static $db_lock = MAME_TW_DB_LOCK_CLASS;

    /**
     * TWINT constructor.
     */
    private function __construct()
    {
    }

    public static function create_for_transaction( $order_id, $merchant_id )
    {
        return static::create( $order_id, $merchant_id, TransactionHandler::class );
    }

    public static function create_for_admin( $order_id, $merchant_id )
    {
        return static::create( $order_id, $merchant_id, AdminTransactionHandler::class );
    }

    /**
     * @param $order_id
     * @param $merchant_id
     * @param $transaction_handler_class
     * @return static|null
     */
    private static function create( $order_id, $merchant_id, $transaction_handler_class )
    {
        $instance = new static();
        $dp_class = static::DATAPROVIDER_CLASS;
        /** @var iDataProvider $data_provider */
        $data_provider = new $dp_class();
        $data_provider->set_order( $order_id )->set_merchant( $merchant_id );

        $eh_class      = static::EVENTHANDLER_CLASS;
        $event_handler = new $eh_class( $data_provider );

        try {
            $transaction_handler = new $transaction_handler_class( $data_provider, $event_handler );
        } catch ( \Mame_Twint\exceptions\SoapNotLoadedException $e ) {
            static::$logger::log_error( 'SOAP not loaded.', 'SOAP extension not loaded', $data_provider );
            return null;
        } catch ( \Mame_Twint\exceptions\TwintCredentialsNotSetException $e ) {
            static::$logger::log_error( 'TWINT credentials not set.', 'All credentials must be set.', $data_provider );
            return null;
        }

        $instance->dataProvider       = $data_provider;
        $instance->transactionHandler = $transaction_handler;
        //$instance->transactionResponseHandler = new TransactionResponseHandler( $data_provider );

        return $instance;

    }

}