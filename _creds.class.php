<?php

	use Defuse\Crypto\Crypto;
	use Defuse\Crypto\Key;
	use Defuse\Crypto\KeyProtectedByPassword;


    /*
	 *
	 * Creds CLASS
	 *
	 */	
	class Creds {
        
        
        public static function decrypt($enc,$file=false){
            
            $val = false;
            $key = Key::loadFromAsciiSafeString($_SERVER['ENC_KEY']);
            
            if($file)
                $enc = self::get_key_file($enc);
            
            if($key && $enc){                
                $val = Crypto::decrypt($enc, $key);                
            }
            
            return $val;
            
        }
        
        
        public static function encrypt($val){
            
            $enc = false;
            $key = Key::loadFromAsciiSafeString($_SERVER['ENC_KEY']);
            
            if($key){                
                $enc = Crypto::encrypt($val, $key);                
            }
            
            return $enc;
            
        }
        
        
        public static function create_key(){
            
            $key = Key::createNewRandomKey();
            $ascii_key = $key->saveToAsciiSafeString();
            
            return $ascii_key;
            
        }
        
        
        public static function get_key_file($filename){
            
            $parts = explode("/",trim($_SERVER['DOCUMENT_ROOT'],"/"));
            $dir = array_pop($parts);
            $parts[] = "includes";
            $parts[] = $dir;
            $path = implode("/",$parts);
            $file = "/{$path}/{$filename}";
            
            
            if(file_exists($file))
                return file_get_contents($file);
            else
                return false;
            
        }
        
        
        
    } /*end Creds class*/