<?php

	include_once "_base.class.php";
 
	class Client extends Base{	
		
		static $_singleton;
		var $instantiated = false;
		var $q;
		var $locations = array();
		var $gas = array();
		var $name = 'Not Instantiated';
		var $path = "/_inc/vendor/dwgreenco/";
		var $abbr = '';
		var $errors = array();
		var $locationsTable = array();
		var $locationListStart;
		var $locationListEnd;
		var $debug = false;
		
		private function construct__() {
			//NO CONSTRUCTING ALLOWED
		}
		
		public static function global_instance() {
			if(!self::$_singleton) {
				self::$_singleton = new Client();
			}
			return self::$_singleton;
			
		}#end global_instance
		
		public function init($abbr){
			
			parent::__construct();
			$this->instantiated = false;
			
			$this->getClient($abbr);
			
			return $this->instantiated;
					
		}#end construct
		
		public function loadModule($module,$path,$global=false,$args=array(),$init=true){
			
			//check if class loaded
			if(!class_exists($module)){
				//check if class file exists
				if(stream_resolve_include_path($path."/_inc/_".$module.".class.php"))
					include_once $path."/_inc/_".$module.".class.php";
			}else{
				echo "class not found! (".$path."/_inc/_".$module.".class.php)";
			}
			$mod = strtolower($module)."Obj";
			$ref = new ReflectionClass($module);
			
			if($global){
				if(gettype($_SESSION[$mod]) == 'object'){
					$_SESSION[$mod] = $this->fix_obj($_SESSION[$mod]);
					return $_SESSION[$mod];
				}else{
					$$mod = $ref->newInstanceArgs($args);
					return $$mod;
				}
			}else if($init){
				return $ref->newInstanceArgs($args);
			}
			
		}
		
		public function define_module_paths($buildOverrides=array()){
			//define constants for module files
			$this->modules = $this->modules ?? [];
			if(is_array($this->modules)){
				foreach($this->modules as $k=>$m){
					if(isset($buildOverrides[$k]) && !$buildOverrides[$k]){
						//skip module
					}else if(isset($buildOverrides[$k]) && is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_".$m['include_path'].$buildOverrides[$k])){
						define("_".strtoupper($k)."_PATH","vendor/dwgreenco/mod_".$m['include_path'].$buildOverrides[$k]);
					}else if(is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_".$m['include_path'].$m['build'])){
						define("_".strtoupper($k)."_PATH","vendor/dwgreenco/mod_".$m['include_path'].$m['build']);
					}else{
						#echo "<!--module not found (".$_SERVER["DOCUMENT_ROOT"].$this->path."mod_".$m['include_path'].$m['build'].")-->";
					}
				}	
			}
		}
		
		public function getPublicModuleVars($what){
			
			//return only public vars of module
			$out = array();
			if(is_array($this->modules[$what])){
				foreach($this->modules[$what] as $k=>$v){
					if(strpos($k,"__")===false)
						$out[$k] = $v;	
				}
			}
			return $out;
			
		}
		
		
		public function getShipping(){
			
			//get client shipping options
			if(is_array($this->modules['shop'])){
				$shipping = array();
				$sql = "SELECT `shipping_value`
							  ,`shipping_id`
							  ,`shipping_label`
							  ,`shipping_vars`
						  FROM `products_shipping` 
						 WHERE `shipping_client` IN ('".implode("','",$_SESSION['clientObj']->modules['shop']['clients'])."') 
						   AND `shipping_active` = 'Y' 
					  ORDER BY `shipping_order` ASC";
				$result = $this->dbQuery($sql);
				$numb = mysqli_num_rows($result);
				if(!empty($numb)){
					while($row = mysqli_fetch_array($result))
						$shipping[$row['shipping_id']] = array("shipping_id"=>$row['shipping_id'],
															   "shipping_value"=>$row['shipping_value'],
															   "shipping_label"=>$row['shipping_label'],
															   "vars"=>$this->ifJson($row['shipping_vars']));
				}else{
					return false;
				}
				return $shipping;
			}else{
				return false;
			}
		}
		
		
		public function getLocations() {
			
			if($this->locationsTable['table']){
				$locationListStart = '';
				$locationList = array();
				$locationListEnd = '';
				$table = $this->locationsTable['table'] ?? '';
				$live = $this->locationsTable['live'] ?? '';
				$liveValue = $this->locationsTable['liveValue'] ?? '';
				$where = $this->locationsTable['where'] ?? '';
				
				if(is_array($liveValue))
					$liveValue = implode("','",$liveValue);
				if(!is_array($this->locationsTable['fieldnames'])){
					$ID = $this->locationsTable['ID'];
					$name = $this->locationsTable['name'];
					$sel = "*";
				}else{
					$selA = array();
					foreach($this->locationsTable['fieldnames'] as $k=>$fn){
						if(!empty($fn))
						$selA[] = "$fn as '$k'";
					}
					$ID = "ID";
					$name = "name";
					$sel = implode(",",$selA);
				}
				$orderBy = $this->locationsTable['order']?:$name;
				
				$q = "SELECT $sel 
						FROM `$this->DB`.`$table` 
					   WHERE $live IN ('$liveValue') 
						   ".($where?"
						 AND $where
							":"")."
					ORDER BY $orderBy";
				$this->q = $q;
				$r = $this->dbQuery($q);
				$this->locationListStart= '<select name="location[]" class="price_location">';
				$this->locationListStart.= '<option value="0">All</option>';
				if(mysqli_num_rows($r) > 0){		
					while($row = mysqli_fetch_assoc($r)){
						$locationList[$row[$ID]] = $row[$name];
						foreach($row as $k=>$v){
							$this->locations[$row[$ID]][$k] = $this->ifJson($v);
						}
					}#end while
					
				}
				$this->locationListEnd ='</select>';
				
				return $locationList;
			}else{
				return false;
			}
		}
		
		public function getGas() {
			
			$gasListStart = '';
			$gasList = array();
			$gasListEnd = '';
			$table = $this->gasTable['table'];
			$live = $this->gasTable['live'];
			$liveValue = $this->gasTable['liveValue'];
			if(is_array($liveValue))
				$liveValue = implode("','",$liveValue);
			if(!is_array($this->gasTable['fieldnames'])){
				$ID = $this->gasTable['ID'];
				$name = $this->gasTable['name'];
				$sel = "*";
			}else{
				$selA = array();
				foreach($this->gasTable['fieldnames'] as $k=>$fn){
					$selA[] = "$fn as '$k'";
				}
				$ID = "ID";
				$name = "name";
				$sel = implode(",",$selA);
			}
			$orderBy = $this->gasTable['order']?:$name;
			
			$x = "SELECT $sel FROM `$this->DB`.`$table` WHERE $live IN ('$liveValue') ORDER BY $orderBy";
			
			$this->x = $x;
			$r2 = $this->dbQuery($x);
			$this->gasListStart= '<select name="location[]" class="price_location">';
			$this->gasListStart.= '<option value="0">All</option>';
			if(mysqli_num_rows($r2) > 0){		
			
				while($row2 = mysqli_fetch_assoc($r2)){
					$gasList[$row2[$ID]] = $row2[$name];
					
					foreach($row2 as $k=>$v)
						$this->gas[$row2[$ID]][$k] = $this->ifJson($v);
				}#end while
				
			}
			$this->gasListEnd ='</select>';
			
			return $gasList;
		}
		
		public function getClient($abbr_){
			
			// query client_meta table for all meta_name's starting with client_info_ 
			$abbr = $this->dbEscape($abbr_);
			if($abbr){
				$q = "SELECT meta_name, meta_value 
					  FROM `client_meta` 
					  WHERE meta_client_id = '$abbr' 
						AND (meta_name LIKE 'client_info_%' || meta_name='ordering_login')
					  ORDER BY meta_name ASC";
				$r = $this->dbQuery($q);
				if(mysqli_num_rows($r) > 0){	
					while($row = mysqli_fetch_assoc($r)){
						$col = str_replace("client_info_","",$row['meta_name']);
						$underCnt = substr_count($col,"_");
						if(empty($underCnt)){
							$this->$col = $this->ifJson($row['meta_value']);
						}else if ($underCnt == 1){
							$pts = explode("_",$col);
							if(empty($this->{$pts[0]}))
								$tempA = array();
							else
								$tempA = $this->{$pts[0]};
							$tempA[$pts[1]]= $this->ifJson($row['meta_value']);
							$this->{$pts[0]} = $tempA;	
						}else{
							$pts = explode("_",$col);
							if(empty($this->{$pts[0]}))
								$tempA = array();
							else
								$tempA = $this->{$pts[0]};
							$tempA[$pts[1]][$pts[2]]= $this->ifJson($row['meta_value']);
							$this->{$pts[0]} = $tempA;
						}
					}
					$this->getLocations();
					if(isset($this->gasTable['table']))
						$this->getGas();
					//
					$this->abbr = $abbr;		
					$this->instantiated = true; 
				}else{
					echo "no client info";
					echo $q;
				}
			}else{
				echo "no abbr (".$abbr_.")";
			}
			
		}//end getClient
		
			
	}#end Client Class
	
