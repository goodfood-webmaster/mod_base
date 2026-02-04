<?php

		
	abstract class Base {		
		
		var $msg = array();
		var $err = array();
		var $loadClientInfo = false;
		var $clientInfo = array();
		var $clientObj;
		
		protected function Base() {//constructor
				
			$this->dbconnect();
			
			if($this->loadClientInfo){
				if(is_object($_SESSION['clientObj']))
					$this->clientInfo = (array)$_SESSION['clientObj'];
				else if(is_object($_SESSION['adminObj']))
					$this->clientInfo = (array)$_SESSION['adminObj']->client;
			}

			
		}//end constructor
		
		protected function dbconnect(){
			//load in singleton object
			$this->dbcs = DBConnectSingleton::global_instance();
			
			if(!$this->dbcs->initialized)
				$this->dbcs = false;
		}
		
		protected function dbQuery($q){
						
			//run a query after checking for db connection
			if (!$this->dbcs || !$this->dbcs->dbc || !@mysqli_ping($this->dbcs->dbc)){
				$this->dbconnect();
			}

			if(!empty($q) && $this->dbcs){
				$r = mysqli_query($this->dbcs->dbc, $q);
				
				if(!$r){ //return empty result on fail and email webmaster
					$this->sendError($q,mysqli_error($this->dbcs->dbc));
					$r = mysqli_query($this->dbcs->dbc, "SELECT 1 FROM `locations` WHERE 1=2");
				}
				return $r;
			}else{
				return false;
			}
		}
		
		protected function dbMultiQuery($q){
			
			//run a multi query after checking for db connection
			if(is_array($q))
				$q = implode(";\r",$q);	
			if (!$this->dbcs->dbc || !@mysqli_ping($this->dbcs->dbc))
				$this->dbconnect();
			if(!empty($q) && $this->dbcs){
				$results = array('rows'=>array(),'cnts'=>array(),'err'=>'');
				if (mysqli_multi_query($this->dbcs->dbc,$q)) { 
					$i = 0; 
					do { 
						$i++; 
						if ($result = @mysqli_store_result($this->dbcs->dbc)) {
							$res = array();
							while($row = @mysqli_fetch_assoc($result)){
								if(count($row)==1) //if only one column returned, just get value
									$res[] = end($row);
								else
									$res[] = $row;
							}
							$results['rows'][$i] = $res;
							$results['cnts'][$i] = mysqli_num_rows($result);
							mysqli_free_result($result);
						}
					} while (mysqli_next_result($this->dbcs->dbc)); 
				}
				$results['err'] = mysqli_error($this->dbcs->dbc);
				return $results;
			}else{
				return false;
			}
		}
		
		
		protected function dbExists($table){
		
			//check if table exists after checking for db connection
			if (!$this->dbcs->dbc || !@mysqli_ping($this->dbcs->dbc))
				$this->dbconnect();	
			
			if(!$this->dbcs)
				return false;
				
			return mysqli_query($this->dbcs->dbc, "SELECT 1 FROM $table");
		}
		
		
		protected function dbEscape($what){
			
			//escape a string after checking for db connection
			if (!$this->dbcs->dbc || !@mysqli_ping($this->dbcs->dbc))
				$this->dbconnect();
			
			if(!$this->dbcs)
				return false;
			
			return mysqli_real_escape_string($this->dbcs->dbc,$what);
		}
		
		
		protected function dbID(){
			
			//return last inserted id after checking for db connection
			if (!$this->dbcs->dbc || !@mysqli_ping($this->dbcs->dbc))
				$this->dbconnect();
			
			if(!$this->dbcs)
				return false;
			
			return mysqli_insert_id($this->dbcs->dbc);
		}
		
		
		protected function dbRows(){
			
			if(!$this->dbcs)
				return false;
		
			//don't ping here or will always return -1			
			return mysqli_affected_rows($this->dbcs->dbc);	
		}
		
		protected function dbColumns($table,$db=""){
			//return array of column names
			$out = array();
			
			if($db && $this->dbcs){				
				$q = "SELECT `COLUMN_NAME` 
					    FROM `INFORMATION_SCHEMA`.`COLUMNS` 
					   WHERE `TABLE_SCHEMA` = '$db' 
						 AND `TABLE_NAME` = '$table'
					 ";
				$result = $this->dbQuery($q);
				
				while($row = mysqli_fetch_assoc($result))
					$out[] = $row['COLUMN_NAME'];
			}
			return $out;
		}
		
		protected function logMe($what){
		
			//write to log if logging enabled
			if($this->logging)
				$this->log[] = date("n/j/y g:i:s a")." - ".$what;	
				
			if($this->log2session)
				$_SESSION['log'][] = date("n/j/y g:i:s a")." - ".get_class($this)." - ".$what;	
		}
		
		public function printLog(){
			
			//print log if logging enabled
			if($this->logging){
				echo '<pre>';
				print_r($this->log);
				echo '</pre>';
			}
		}
		
		
		protected function sendError($err="",$msg=array()){
						
			$token = uniqid();
			
			if(!is_dir($_SERVER['DOCUMENT_ROOT'].'/admin/errors/'.date("Y-m-d", strtotime($_SESSION['datertimer'])))){
				mkdir($_SERVER['DOCUMENT_ROOT'].'/admin/errors/'.date("Y-m-d", strtotime($_SESSION['datertimer'])));
			}
			
			$error_file = '/admin/errors/'.date("Y-m-d", strtotime($_SESSION['datertimer'])).'/'.$token.'.log';
			
			$subject = "bad query in ".get_class($this)." class";
			$error_text = print_r(array('err'=>$err,'msg'=>$msg),true).PHP_EOL.print_r($_SERVER,true);
            
            //email mysql error to webmaster
            mailout("webmaster@dwgreen.com",$subject,"Error on page https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."<br><br><pre>".print_r($err,true)."</pre><br><br>https://".$_SERVER['HTTP_HOST'].$error_file,"webmaster@dwgmail.com");
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'].$error_file, $error_text, FILE_APPEND);
            	
            return $err;
        }
		
	
		public function dump($how="screen",$what=NULL,$hideClient=true){
			
			// dump vars
			if(empty($what))
				$what = $this;
			//
			$vars = get_object_vars($what);
			if($hideClient)
				$this->recursive_unset($vars, 'clientObj');
			//	
			if ($how=="email"){
				mailout("webmaster@dwgreen.com",get_class($what)." class DUMP","DUMPING class on page ".$_SERVER['PHP_SELF']."<br><pre>".print_r($vars,true)."</pre>","webmaster@dwgmail.com");
			}else{
				echo '<pre>';
				print_r($vars);
				echo '</pre>';
			}
		}
		
		private function recursive_unset(&$array, $unwanted_key) {
			if(isset($array[$unwanted_key]))
				$array[$unwanted_key] = "HIDDEN";
			foreach ($array as &$value) {
				if(is_object($value)){
					$value = get_object_vars($value);
				}
				if (is_array($value)) {
					$this->recursive_unset($value, $unwanted_key);
				}
			}
		}
		
		
		public function dumpA($what,$how="screen"){
			
			//dump array
			if ($how=="email"){
				mailout("webmaster@dwgreen.com",get_class($this)." class DUMP","DUMPING array from class on page ".$_SERVER['PHP_SELF']."<br><br>".json_encode($what),"webmaster@dwgmail.com");
			}else{
				echo '<pre>';
				print_r($what);
				echo '</pre>';
			}
			
		}
		
		
		public function fix_obj(&$object){
			//recast session objects
			if (get_class($object) == '__PHP_Incomplete_Class')
				return ($object = unserialize(serialize($object)));
			else
				return $object;
		
		}#end fix_obj
		
				
		protected function isJson($string) {
			//check if passed string parses to json object without error
			$json = json_decode(stripslashes($string),true);
			return (json_last_error() == JSON_ERROR_NONE && is_array($json));
		
		}#end isJson
		
				
		protected function ifJson($string) {
			
			//if no bracket, skip json check
			if(strpos($string,"{")===false && strpos($string,"[")===false)
				return $string;
			// if passed string parses to json object without error return decode array, otherwise string
			$out = json_decode(stripslashes($string),true);
			if(json_last_error() == JSON_ERROR_NONE)
				return $out;
			$out = json_decode($string,true);
			if(json_last_error() == JSON_ERROR_NONE)
				return $out;
			else
				return $string;
		
		}#end ifJson
		
		
		protected function shorten($str,$lim=150,$ellip=true,$strip=false) {
			//strip all tags, if desired
			if ($strip)
				$str = strip_tags(str_replace(array("<br />","<br>","<br/>")," ",$str));
			//trim characters, ignoring html and not breaking words	
			$out = ''; 
			$cnt = 0;
			foreach(explode('>',trim($str)) as $pts1){
				$pts2 = explode('<',$pts1); 
				$len = strlen($pts2[0]);
				if(($cnt+$len)>$lim){
					$last_space = strrpos(substr($pts2[0], 0, ($lim-$cnt)), ' ');
					$pts2[0] = substr($pts2[0], 0, $last_space);
				}
				$cnt += $len;
				$out .= $pts2[0];
				if (!empty($pts2[1])) 
					$out .= '<' . $pts2[1] . '>';    
			} 
			$out = trim($out," .,:");
			//add ellipses, if desired
			if ($ellip && $cnt > $lim)
				$out .= '&hellip;';
				
			return $out;
		}# end shorten
		
		
		protected function dedangle($str,$orphans=3,$maxChars=100){
			
			//replace last few spaces with non-breaking spaces to prevent dangling word-wrap
			$words = preg_split('/\s+/', trim(stripslashes($str)));
			if(count($words)>2){
				$ends = array();
				for($o=1;$o<=($orphans+1);$o++)
					$ends[] = array_pop($words);
				$end = implode("&nbsp;",array_reverse($ends));
				if(strlen($end)>$maxChars)
				    $end = preg_replace('/&nbsp;/',' ',$end,1);
				$out = implode(" ",$words).' '.$end;
				return $out;
			}else{
				return $str;
			}

		}#end dedangle
				
		
		protected function clenseString($str) {
			
			$bad = array('%C3%A2%E2%82%AC%E2%84%A2'
						,'%C3%83%C2%A9'
						,'%C3%A2%E2%82%AC%E2%80%9C'
						,'%C3%83%C2%B1');
			$good = array("'"
						 ,"&eacute;"
						 ,"&mdash;"
						 ,"&ntilde;");	
			$res = urldecode(str_replace($bad,$good,urlencode($str)));
			return stripslashes($res);
			
		}#end clenseString
		
		
		protected function farscape($str,$strip=true){
			
			$str = stripslashes($str);
			$str = html_entity_decode($str,ENT_QUOTES,'UTF-8');
			$str = stripslashes($str);
			$str = preg_replace('/\s\s+/', ' ', $str); //remove more than 1 space
			$str = str_replace("\n","",$str);
			$str = str_replace("\r","",$str);
			if($strip)
				$str = trim(strip_tags($str));
			#$str = trim($str,chr(0xC2).chr(0xA0)."&nbsp;"); //trim non-breaking spaces
			$str = trim($str);
			#$str = trim($str,chr(0xC2).chr(0xA0)."&nbsp;"); //trim non-breaking spaces
			#$str = trim($str,chr(0xC2).chr(0xA0)."&nbsp;"); //trim non-breaking spaces
			$str = str_replace("#039","#39",htmlentities($str,ENT_QUOTES,'UTF-8'));
			if(!$strip)
				$str = str_replace(array('&lt;','&gt;'),array('<','>'), $str);//put tags back
			return $this->dbEscape($str);
		
		}#end farscape
		
		
		public function formatPhone($phone){
			
			$phone = preg_replace("/[^0-9]/","", $phone);
			if(!empty($phone))
				if(strlen($phone)>7)
					return "(".substr($phone, 0, 3).") ".substr($phone, 3, 3)."-".substr($phone,6);
				else if(strlen($phone) > 5)
					return substr($phone, 0, 3)."-".substr($phone,3);
				else
					return $phone;
			else
				return "";
		}
		
		
		public function _o($str){
			//sanitize before outputing to page
			return htmlspecialchars(stripslashes($str), ENT_QUOTES, 'utf-8', false);
		}
		
		
		protected function logQueryProfiles(){
			
			$prof = $this->getProfiles();
			if(!is_array($this->dbcs->profiles))
				$this->dbcs->profiles = array();
			if(is_array($prof)){
				foreach($prof as $k=>$v){
					$qid = $v['Query_ID'];
					if(!in_array($qid,array_keys($this->dbcs->profiles))){
						$this->dbcs->profiles[$qid] = $v;	
					}
						
				}
			}
			
		}
		
		
		protected function getProfiles(){
			$q = "SHOW profiles;";
			$r = mysqli_query($this->dbcs->dbc, $q);
			$prof = array();
			while($row = mysqli_fetch_assoc($r))
				$prof[] = $row;
		
			return $prof;
		}
		
		
		public function getError($all=true){
			
			$cnt = count($this->err);
			if(!$cnt)
				return "";
			if($all)
				return implode(". ",$this->err);
			else
				return $this->err[$cnt-1];
			
		}
		
		
		public function extractInt($str){
			
			preg_match_all('!\d+!', $str, $matches);
			return implode("",$matches[0]);

		}
		
		
		public function getMsg($all=true){
			
			$cnt = count($this->msg);
			if(!$cnt)
				return "";
			if($all)
				return implode(". ",$this->msg);
			else
				return $this->msg[$cnt-1];
			
		}
		
		
		protected function deCamel($str) {
			//make sure first letter is uppercase
			$str[0] = strtoupper($str[0]);
			$func = create_function('$c', 'return " " . strtoupper($c[1]);');
			return preg_replace_callback('/([A-Z0-9])/', $func, $str);
		}
		
		
		protected function deUnderscore($str) {
			$func = create_function('$c', 'return " " . strtoupper($c[1]);');
			return trim(preg_replace_callback('/_([a-zA-Z0-9])/', $func, $str),"_");
		}
		
		
		protected function beautify($str){
			$str = str_replace("-","_",$str);
			$str = $this->deUnderscore($str);
			$str = $this->deCamel($str);	
			$str = str_replace("  "," ",$str);
			
			return ucwords($str);
		}
		
		
		public function fourOhFour(){
			header('Content-type: text/html');
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
			?>
<HTML>
<HEAD>
<meta name=viewport content="width=device-width, initial-scale=1">
<TITLE>404 Not Found</TITLE>
</HEAD>
<BODY>
<H1>Not Found</H1>
The requested document was not found on the dwg server.
<P>
<HR>
<ADDRESS>
Web Server at <?=$_SERVER['HTTP_HOST'];?>
</ADDRESS>
</BODY>
</HTML>

<!--
   - Unfortunately, Microsoft has added a clever new
   - \"feature\" to Internet Explorer. If the text of
   - an error's message is \"too small\", specifically
   - less than 512 bytes, Internet Explorer returns
   - its own error message. You can turn that off,
   - but it's pretty tricky to find switch called
   - \"smart error messages\". That means, of course,
   - that short error messages are censored by default.
   - IIS always returns error messages that are long
   - enough to make Internet Explorer happy. The
   - workaround is pretty simple: pad the error
   - message with a big comment like this to push it
   - over the five hundred and twelve bytes minimum.
   - Of course, that's exactly what you're reading
   - right now.
-->
			<?
			exit();
		}

		
	}//end Base class


###
#### DBConnect Singleton Class
###

	class DBConnectSingleton {
		private static $_singleton;
		public $dbc;
		public $profiles = array();
		public $initialized = false;
			
		private function construct__() {
			//NO CONSTRUCTING ALLOWED
		}
		
		public static function global_instance() {
			if(!self::$_singleton) {
				self::$_singleton = new DBConnectSingleton();
			}
			return self::$_singleton;
			
		}#end global_instance
		
		public function setCreds($db_username,$db_password,$db_name,$db_host="localhost"){
						
			$this->db_username = $db_username;
			$this->db_password = $db_password;
			$this->db_name = $db_name;
			$this->db_host = $db_host ?: "localhost";
			return true;
		}
		
		public function init(){	
			
			if(!$this->db_host || !$this->db_username || !$this->db_password || !$this->db_name)
				return false;
			
			$this->dbc = mysqli_connect($this->db_host, $this->db_username, $this->db_password, $this->db_name);
			
			if (!$this->dbc)
				mailout("webmaster@dwgreen.com","DB connection error in ".get_class($this)." class","DB connection error on page http://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']."<br><br>>MYSQLI ERROR:<BR>".mysqli_connect_error()."<br><br>".json_encode($_SERVER),"webmaster@dwgmail.com");
				
			mysqli_set_charset($this->dbc, "utf8");
			
			if($_SERVER['REMOTE_ADDR'] == "98.191.98.108") {		
				$q = "SET profiling=1;";
				$r = mysqli_query($this->dbc, $q);
			}
			
			$this->initialized = true;
			
			return true;
		
		}#end init	
		
		public function setCharset($charset){
			
			mail("webmaster@dwgreen.com","charset change","Charset was ".mysqli_character_set_name($this->dbc),"From:webmaster@dwgmail.com");
					
			if(!empty($charset))
				mysqli_set_charset($this->dbc, $charset);
			
			mail("webmaster@dwgreen.com","charset change","Charset now ($charset) ".mysqli_character_set_name($this->dbc),"From:webmaster@dwgmail.com");
			
		}
		
		
		public function showProfiles($minDur=0.001){
		
			?>
            <pre>
            	<? foreach($this->profiles as $p){
					  if((float)$p['Duration'] > $minDur){ 
						print_r($p);
					  }
					}
				?>
            </pre>
            <?	

		}
		
	}
	
	$dbcs = DBConnectSingleton::global_instance();
	$dbcs->setCreds(@constant('_DB_USER'),@constant('_DB_PASS'),@constant('_DB_NAME'),@constant('_DB_HOST'));
	$dbcs->init();
	
	