<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

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
                
                function __construct($processkey){
                        $this->processkey       = $processkey;
                        $this->lockkey          = 'wp-post-indexer-lock-'.$this->processkey;
                        $this->locked           = false;
                        
                        $this->is_locked();
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
				$set_lock = set_transient($this->lockkey, $locker_info);
                                return $set_lock;
			}
		}
                
                function get_locker_info($info_key = false){
                        $locker_info = get_transient($this->lockkey);
                        
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
