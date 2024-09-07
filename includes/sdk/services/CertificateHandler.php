<?php

namespace Mame_Twint\services;

/**
 * Class CertificateHandler
 * @package Mame_Twint\services
 */
class CertificateHandler
{
    const ERROR_OPENSSL                = 'OPENSSL_NOT_ACTIVE';
    const ERROR_PARSE                  = 'PARSE_FAILED';
    const ERROR_PK_EXPORT              = 'PK_EXPORT';
    const ERROR_CERT_EXPORT            = 'CERT_EXPORT';
    const ERROR_SUCCESS                = 'SUCCESS';
    const ERROR_FILETYPE_NOT_SUPPORTED = 'FILETYPE_NOT_SUPPORTED';
    const ERROR_FILE_READ_FAILED       = 'FILE_READ_FAILED';

    /**
     * Converts the TWINT certificate file.
     *
     * @param $file
     * @param $password
     * @return array
     */
    public static function convert_certificate_file( $file, $password )
    {
        $extension = strtolower( substr( $file, strrpos( $file, '.' ) + 1 ) );

        $file_content = file_get_contents( $file );
        if ( !$file_content ) {
            $file_content = readfile( $file );
        }

        if ( !$file_content ) {
            return [ 'status' => false, 'error' => static::ERROR_FILE_READ_FAILED ];
        }

        switch ( $extension ) {

            case 'p12':
            case 'pfx':

                $result = static::convert_p12_to_pem( $file_content, $password );

                if ( !$result[ 'status' ] ) {
                    return $result;
                }

                return [ 'status' => true, 'file' => $result[ 'file' ] ];

                break;

            case 'txt':

                return [ 'status' => true, 'file' => $file_content ];

                break;

            default:

                return [ 'status' => false, 'error' => static::ERROR_FILETYPE_NOT_SUPPORTED ];
                break;
        }
    }

    /**
     * Converts a pkcs12 certificate to PEM.
     *
     * @param $p12_content
     * @param $password
     * @return array
     */
    public static function convert_p12_to_pem( $p12_content, $password )
    {
        if ( !MAME_TW_OPENSSL_ACTIVE ) {
            return [ 'status' => false, 'error' => static::ERROR_OPENSSL ];
        }

        $results = [];
        if ( !openssl_pkcs12_read( $p12_content, $results, $password ) ) {
            return [ 'status' => false, 'error' => static::ERROR_PARSE ];
        }

        $key = null;
        if ( !openssl_pkey_export( $results[ 'pkey' ], $key, $password ) ) {
            return [ 'status' => false, 'error' => static::ERROR_PK_EXPORT ];
        }

        $cert = null;
        if ( !openssl_x509_export( $results[ 'cert' ], $cert ) ) {
            return [ 'status' => false, 'error' => static::ERROR_CERT_EXPORT ];
        }

        $file = $key . $cert;

        return [ 'status' => true, 'file' => $file ];
    }
}