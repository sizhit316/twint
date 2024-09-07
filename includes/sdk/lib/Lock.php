<?php

namespace Mame_Twint\lib;

class Lock
{
    protected $key  = null;
    protected $file = null;
    protected $own  = false;

    function __construct( $key, $dir )
    {
        $this->key = $key;

        //create a new resource or get exisitng with same key
        $locks_dir = $dir . 'locks' . DIRECTORY_SEPARATOR;
        if ( !file_exists( $locks_dir ) ) {
            mkdir( $locks_dir, 0775 );
            file_put_contents( $locks_dir . '.htaccess', 'deny from all' );
        }

        $path       = $locks_dir . "$key.lockfile";
        $this->file = fopen( $path, 'w+' );
    }

    function __destruct()
    {
        if ( $this->own == true )
            $this->unlock();
    }

    function lock()
    {
        if ( !flock( $this->file, LOCK_EX | LOCK_NB ) ) { //failed
            $key = $this->key;
            error_log( "ExclusiveLock::acquire_lock FAILED to acquire lock [$key]" );
            return false;
        }
        ftruncate( $this->file, 0 ); // truncate file
        //write something to just help debugging
        fwrite( $this->file, "Locked\n" );
        fflush( $this->file );

        $this->own = true;
        return TRUE; // success
    }

    function unlock()
    {
        $key = $this->key;
        if ( $this->own == true ) {
            if ( !flock( $this->file, LOCK_UN ) ) { //failed
                error_log( "ExclusiveLock::lock FAILED to release lock [$key]" );
                return false;
            }
            ftruncate( $this->file, 0 ); // truncate file
            //write something to just help debugging
            fwrite( $this->file, "Unlocked\n" );
            fflush( $this->file );
            $this->own = false;
        } else {
            error_log( "ExclusiveLock::unlock called on [$key] but its not acquired by caller" );
        }
        return TRUE; // success
    }

    /**
     * Removes all locks starting with a certain prefix from directory $dir.
     *
     * @param $prefix
     * @param $dir
     */
    public static function remove_all_locks( $prefix, $dir )
    {
        $locks_dir = $dir . 'locks' . DIRECTORY_SEPARATOR;
        $files     = preg_grep( '~^' . $prefix . '.*~', scandir( $locks_dir ) );

        if ( !empty( $files ) ) {
            foreach ( $files as $file ) {
                unlink( $locks_dir . $file );
            }
        }

    }
}

;