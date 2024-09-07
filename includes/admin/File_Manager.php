<?php

namespace Mame_Twint\admin;

use Mame_Twint\Globals;
use Mame_Twint\Twint_Helper;

class File_Manager
{
    /**
     * Sends the PDF file to be downloaded.
     *
     * @param $filename
     * @param string $type
     * @param string $name
     */
    public static function send_file( $filename, $type, $name )
    {
        $file     = Globals::get_upload_path( $type ) . $filename;
        $pathinfo = pathinfo( $file );

        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . $name . '.' . $pathinfo[ 'extension' ] . '"' );
        //header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . filesize( $file ) );
        flush(); // Flush system output buffer
        readfile( $file );
        die();
    }

    /**
     * Displays the PDF in the browser.
     *
     * @param $filename
     * @param string $type
     */
    public static function display_pdf( $filename, $type = 'temp' )
    {
        header( 'Content-type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . $filename . '"' );
        //header('Content-Transfer-Encoding: binary');
        //header('Accept-Ranges: bytes');
        //@readfile();
        $file = Twint_Helper::get_uploads_dir() . $type . '/' . $filename;
        ob_start();
        readfile( $file );
        ?>
        <script type="text/javascript">
            window.onload = function () {
                window.print();
            }
        </script>
        <?php
        ob_end_flush();
        exit;
    }
}


