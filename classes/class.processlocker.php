<?php
/**
 * Creates a process lock transients with information about the running process.
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 */
if(!class_exists('ProcessLocker')) {
        
        class ProcessLocker {
                
                
                /**
                 *
                 * @var string The transient key for locking 
                 */
                private $lockkey;
                
                /**
                 *
                 * @var string The transient key for storing process information 
                 */
                private $infokey;
                
                /**
                 * 
                 * 
                 * @param string $processkey The process key used to name unique transients
                 */
                function __construct($processkey) {
                        
                        $this->infokey     = 'wp-post-indexer-process-info'.$processkey;
                        $this->lockkey          = 'wp-post-indexer-lock'.$processkey;
                        $this->is_locked();
                }
                
                /**
                 * 
                 */
                function __destruct() {
                        // remove lock on destruct, so it is freed up for the next process
                        delete_transient($this->lockkey);
                }
                
                /**
                 * Checks if this process has a lock. If not attempts to lock
                 * 
                 * @return type
                 */
                public function is_locked() {
                        $locked = get_transient($this->lockkey);
                        
                        // the transient is not set, no process is working on this key
                        if($locked === false) {
                                // set transient for locking by this process
                                $lock = set_transient($this->lockkey, 1);
                                return $lock;
                        }
                        
                        // Otherwise transient exists, then a process has already locked this,
                        // we can't lock it
                        return false;
                        
                        
                        }
                
                /**
	             *
                 * @param type $locker_info
                 * @return type
                 */
                function set_locker_info( $locker_info = array() ) {


                        // if the lock transient is not set for this key, then it is not the same process
            if( ! $this->is_locked() ) {

                                return;

                        } else {

                        // only this process would be allowed to set the information
                $locker_info['time_start'] = time();
                $locker_info['pid'] = getmypid();
                $set_lock = set_transient($this->infokey, $locker_info);
                                return $set_lock;

            }
                        
        }
                
                /**
                 * 
                 * @param type $info_key
                 * @return boolean
                 */
                function get_locker_info($info_key = false) {
                        $locker_info = get_transient($this->infokey);
                        
                        if(empty($locker_info)) {
                                return false;
                        }
                        if($info_key!==false && isset($locker_info[$info_key])) {
                                return $locker_info[$info_key];
                        }
                        
                        if($info_key === false){
                                return $locker_info;
                        }
                        
                        return false;        
                        
                }
        }
        
}
