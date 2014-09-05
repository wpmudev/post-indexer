<?php
/**
 * Description of processLocker
 *
 * @author saurabhshukla
 */
if(!class_exists('ProcessLocker')){
        
        class ProcessLocker {
                
                private $locked;
                
                private $processkey;
                
                private $lockkey;
                
                private $locekinfokey;
                
                function __construct($processkey){
                        $this->processkey       = $processkey;
                        $this->locekinfokey     = 'wp-post-indexer-lock-info'.$this->processkey;
                        $this->lockkey          = 'wp-post-indexer-lock'.$this->processkey;
                        $this->locked           = false;
                        set_transient($this->lockkey, 1);
                        $this->is_locked();
                }
                
                function __destruct() {
                        delete_transient($this->lockkey);
                }
                
                private function is_locked(){
                        $locked = get_transient($this->lockkey);
                        
                        $this->locked = (bool) $locked;
                        return $this->locked;
                        
                }
                
                function set_locker_info($locker_info = array()) {
			if(!$this->locked){
                                return;
                        }
			if ($this->is_locked()) {
				$locker_info['time_start'] = time();
				$locker_info['pid'] = getmypid();
				$set_lock = set_transient($this->locekinfokey, $locker_info);
                                return $set_lock;
			}
		}
                
                function get_locker_info($info_key = false){
                        $locker_info = get_transient($this->locekinfokey);
                        
                        if(empty($locker_info)){
                                return false;
                        }
                        if($info_key!==false && isset($locker_info[$info_key])){
                                return $locker_info[$info_key];
                        }
                        
                        if($info_key === false){
                                return $locker_info;
                        }
                        
                        return false;        
                        
                }
        }
        
}
