<?php

	class Jump{
		
		var $dbc = NULL;	
		var $client = NULL;
		var $build = NULL;
		var $adminBuild = NULL;
		var $path = "/wp-content/themes/bristolfarms_2025/_inc/vendor/goodfood-webmaster/";
		var $vendor = "goodfood-webmaster";
		
		public function __construct($client=NULL,$build=NULL,$buildOverrides=array(),$admin=false){
			
			if(!defined("_DB_HOST") || !defined("_DB_USER") || !defined("_DB_PASS")){
				echo "no database vars";
				exit;
			}
			if(!empty($client)){
				$this->dbc = mysqli_connect(_DB_HOST, _DB_USER, _DB_PASS, "dwgreen_cms")
					or die("Jump Database selection failed!");
				mysqli_set_charset($this->dbc, "utf8");

				$this->client = mysqli_real_escape_string($this->dbc,$client);	
				
				if(!is_numeric($build) || !is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$build)){									
					$q = "SELECT `meta_value` 
							FROM `dwgreen_cms`.`client_meta` 
						   WHERE `meta_name` = 'client_info_basejump' 
							 AND `meta_client_id` = '$this->client' 
						   LIMIT 1";
					$result = mysqli_query($this->dbc,$q)
						or die("You have an error in your Query:($q) " .mysqli_error($this->dbc));
					$num = mysqli_num_rows($result);
					
					if(!empty($num)){
						$row = mysqli_fetch_array($result);
						$meta = json_decode($row['meta_value'],true);
						if(is_numeric($meta['build']) && is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$meta['build']))
							$this->build = $meta['build'];
						else
							echo "build dir not found (".$_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$meta['build'].")";
					}else{
						echo "no build in db";
					}
				}else{
					$this->build = $build;
				}
				
				if($admin)
					$this->initAdmin($buildOverrides);
				else
					$this->init($buildOverrides);
				
			}else{
				echo "no client";
			}
		
		}#end __construct
		
		
		function init($buildOverrides=array()){
			
			if(!is_null($this->build)){	
				include_once $_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$this->build."/_client.class.php";
				define("_BASE_PATH","vendor/{$this->vendor}/mod_base/".$this->build);
			}
			if($this->build >= 1003){
				
				$c = Client::global_instance();
				$c->init($this->client);
				if($c->instantiated){
					$c->define_module_paths($buildOverrides);
				}else{
					echo "no instantiated client";
					#$c->dump();
					#echo "client is ".$this->client;
					#print_r($this);
				}
				
			}else if(!is_null($this->build)){
			
				if(isset($_SESSION['clientObj'])){
					$_SESSION['clientObj'] = $this->fix_obj($_SESSION['clientObj']);
					if($_SESSION['clientObj']->abbr != $this->client){
						unset($_SESSION['clientObj']);
						$_SESSION['clientObj'] = new Client($this->client);	
					}
				}else{
					$_SESSION['clientObj'] = new Client($this->client);
				}
				$_SESSION['clientObj']->define_module_paths($buildOverrides);
			}
			
		}#end init
		
		
		function initAdmin($buildOverrides=array()){
			
			if(!is_numeric($buildOverrides['admin']) || !is_dir($this->path."mod_admin/".$buildOverrides['admin'])){
				$q = "SELECT `meta_value` 
						FROM `dwgreen_cms`.`client_meta` 
					   WHERE `meta_name` = 'admin_info' 
						 AND `meta_client_id` = '$this->client' 
					   LIMIT 1";
				$result = mysqli_query($this->dbc,$q)
					or die("You have an error in your Query:($q) " .mysqli_error($this->dbc));
				$num = mysqli_num_rows($result);
				
				if(!empty($num)){
					$row = mysqli_fetch_array($result);
					$meta = json_decode($row['meta_value'],true);
					if(is_numeric($meta['build']) && is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$meta['build']))
						$this->adminBuild = $meta['build'];
					else
						echo "no admin build dir ({$_SERVER["DOCUMENT_ROOT"]}{$this->path}mod_admin/{$meta['build']})";
				}else{
					echo "no admin build in db";
				}
			}else{
				$this->adminBuild = $buildOverrides['admin'];
			}
			
			if(!is_null($this->build))
				define("_BASE_PATH","vendor/{$this->vendor}/mod_base/".$this->build);
			
			if(!is_null($this->adminBuild)){
				include_once $_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$this->adminBuild."/_inc/_admin.class.php";
				define("_ADMIN_PATH","vendor/{$this->vendor}/mod_admin/".$this->adminBuild);
			
				if(isset($_SESSION['adminObj'])){
					$_SESSION['adminObj'] = $this->fix_obj($_SESSION['adminObj']);
				}else{
					$_SESSION['adminObj'] = new Admin($this->client);
				}
				$_SESSION['adminObj']->define_module_paths($buildOverrides);
				
				return true;
			}
			
		}// end adminInit
		
		
		public function admin($build=NULL){
			
			if(!is_numeric($build)){
				$q = "SELECT `meta_value` 
						FROM `dwgreen_cms`.`client_meta` 
					   WHERE `meta_name` = 'admin_info' 
						 AND `meta_client_id` = '$this->client' 
					   LIMIT 1";
				$result = mysqli_query($this->dbc,$q)
					or die("You have an error in your Query:($q) " .mysqli_error($this->dbc));
				$num = mysqli_num_rows($result);
				
				if(!empty($num)){
					$row = mysqli_fetch_array($result);
					$meta = json_decode($row['meta_value'],true);
					if(is_numeric($meta['build']) && is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$meta['build']))
						$this->adminBuild = $meta['build'];
					else
						echo "no admin build dir";
				}else{
					echo "no admin build in db";
				}
			}else if(is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$build)){
				$this->adminBuild = $build;
			}else{
				echo "admin build dir not found (".$_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$build.")";
			}
			if(!is_null($this->adminBuild)){
				define("_ADMIN_PATH","mod_admin/".$this->adminBuild);
				include_once $_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$this->adminBuild."/_inc/_admin.class.php";
				if(isset($_SESSION['adminObj'])){
					$_SESSION['adminObj'] = $this->fix_obj($_SESSION['adminObj']);
				}	
			}
				
		}
		
		
		public function webservices($service,$key){
			
			switch($service){
				case 'cooking';
					$inc = _RECIPES_PATH."/webservices.php";
				break;
				case 'shop';
					$inc = _SHOP_PATH."/webservices.php";
				break;
				default;
					echo 'Webservice not found';
					return false;
				break;
			}
			
			if(!empty($inc) && stream_resolve_include_path($inc)){
				include_once $inc;
			}else{
				echo "webservice not found ($inc)";
				return false;
			}
		}
		
		
		private function fix_obj(&$object){
			
			//recast session objects
			if (get_class($object) == '__PHP_Incomplete_Class')
				return ($object = unserialize(serialize($object)));
			else
				return $object;
		
		}#end fix_obj
	
	}#end Jump
	

	
/* Updated Jump class localizing the database away from dwgreen_cms for builds 1004+ */

	class Jump4{
		
		var $dbc = NULL;	
		var $client = NULL;
		var $build = NULL;
		var $adminBuild = NULL;
		var $path = "/_inc/vendor/";
		var $vendor = "goodfood-webmaster";
		
		public function __construct($client=NULL,$build=NULL,$buildOverrides=array(),$admin=false){

			$this->path .= $this->vendor."/";
			
			if(!defined("_DB_HOST") || !defined("_DB_USER") || !defined("_DB_PASS")){
				echo "no database vars";
				exit;
			}
			if(!empty($client)){
				$this->dbc = mysqli_connect(_DB_HOST, _DB_USER, _DB_PASS, _DB_NAME)
					or die("Jump Database selection failed! [".mysqli_connect_errno()."] ".mysqli_connect_error());
				mysqli_set_charset($this->dbc, "utf8");

				$this->client = mysqli_real_escape_string($this->dbc,$client);	
				
				if(!is_numeric($build) || !is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$build)){									
					$q = "SELECT `meta_value` 
							FROM `client_meta` 
						   WHERE `meta_name` = 'client_info_basejump' 
							 AND `meta_client_id` = '$this->client' 
						   LIMIT 1";
					$result = mysqli_query($this->dbc,$q)
						or die("You have an error in your Query:($q) " .mysqli_error($this->dbc));
					$num = mysqli_num_rows($result);
					
					if(!empty($num)){
						$row = mysqli_fetch_array($result);
						$meta = json_decode($row['meta_value'],true);
						if(is_numeric($meta['build']) && is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$meta['build']))
							$this->build = $meta['build'];
						else
							echo "build dir not found (".$_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$meta['build'].")";
					}else{
						echo "no build in db";
					}
				}else{
					$this->build = $build;
				}
				
				if($admin)
					$this->initAdmin($buildOverrides);
				else
					$this->init($buildOverrides);
				
			}else{
				echo "no client";
			}
		
		}#end __construct
		
		
		function init($buildOverrides=array()){
			
			if(!is_null($this->build)){	
				include_once $_SERVER["DOCUMENT_ROOT"].$this->path."mod_base/".$this->build."/_client.class.php";
				define("_BASE_PATH","vendor/{$this->vendor}/mod_base/".$this->build);
			}
			if($this->build >= 1003){
				
				$c = Client::global_instance();
				$c->init($this->client);
				if($c->instantiated){
					$c->define_module_paths($buildOverrides);
				}else{
					echo "no instantiated client";
					#$c->dump();
					#echo "client is ".$this->client;
					#print_r($this);
				}
				
			}else if(!is_null($this->build)){
			
				if(isset($_SESSION['clientObj'])){
					$_SESSION['clientObj'] = $this->fix_obj($_SESSION['clientObj']);
					if($_SESSION['clientObj']->abbr != $this->client){
						unset($_SESSION['clientObj']);
						$_SESSION['clientObj'] = new Client($this->client);	
					}
				}else{
					$_SESSION['clientObj'] = new Client($this->client);
				}
				$_SESSION['clientObj']->define_module_paths($buildOverrides);
			}
			
		}#end init
		
		
		function initAdmin($buildOverrides=array()){
			
			if(!is_numeric($buildOverrides['admin']) || !is_dir($this->path."mod_admin/".$buildOverrides['admin'])){
				$q = "SELECT `meta_value` 
						FROM `client_meta` 
					   WHERE `meta_name` = 'admin_info' 
						 AND `meta_client_id` = '$this->client' 
					   LIMIT 1";
				$result = mysqli_query($this->dbc,$q)
					or die("You have an error in your Query:($q) " .mysqli_error($this->dbc));
				$num = mysqli_num_rows($result);
				
				if(!empty($num)){
					$row = mysqli_fetch_array($result);
					$meta = json_decode($row['meta_value'],true);
					if(is_numeric($meta['build']) && is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$meta['build']))
						$this->adminBuild = $meta['build'];
					else
						echo "no admin build dir ({$_SERVER["DOCUMENT_ROOT"]}{$this->path}mod_admin/{$meta['build']})";
				}else{
					echo "no admin build in db";
				}
			}else{
				$this->adminBuild = $buildOverrides['admin'];
			}
			
			if(!is_null($this->build))
				define("_BASE_PATH","vendor/{$this->vendor}/mod_base/".$this->build);
			
			if(!is_null($this->adminBuild)){
				include_once $_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$this->adminBuild."/_inc/_admin.class.php";
				define("_ADMIN_PATH","vendor/{$this->vendor}/mod_admin/".$this->adminBuild);
			
				if(isset($_SESSION['adminObj'])){
					$_SESSION['adminObj'] = $this->fix_obj($_SESSION['adminObj']);
				}else{
					$_SESSION['adminObj'] = new Admin($this->client);
				}
				$_SESSION['adminObj']->define_module_paths($buildOverrides);
				
				return true;
			}
			
		}// end adminInit
		
		
		public function admin($build=NULL){
			
			if(!is_numeric($build)){
				$q = "SELECT `meta_value` 
						FROM `client_meta` 
					   WHERE `meta_name` = 'admin_info' 
						 AND `meta_client_id` = '$this->client' 
					   LIMIT 1";
				$result = mysqli_query($this->dbc,$q)
					or die("You have an error in your Query:($q) " .mysqli_error($this->dbc));
				$num = mysqli_num_rows($result);
				
				if(!empty($num)){
					$row = mysqli_fetch_array($result);
					$meta = json_decode($row['meta_value'],true);
					if(is_numeric($meta['build']) && is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$meta['build']))
						$this->adminBuild = $meta['build'];
					else
						echo "no admin build dir";
				}else{
					echo "no admin build in db";
				}
			}else if(is_dir($_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$build)){
				$this->adminBuild = $build;
			}else{
				echo "admin build dir not found (".$_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$build.")";
			}
			if(!is_null($this->adminBuild)){
				define("_ADMIN_PATH","mod_admin/".$this->adminBuild);
				include_once $_SERVER["DOCUMENT_ROOT"].$this->path."mod_admin/".$this->adminBuild."/_inc/_admin.class.php";
				if(isset($_SESSION['adminObj'])){
					$_SESSION['adminObj'] = $this->fix_obj($_SESSION['adminObj']);
				}	
			}
				
		}
		
		
		public function webservices($service,$key){
			
			switch($service){
				case 'cooking';
					$inc = _RECIPES_PATH."/webservices.php";
				break;
				case 'shop';
					$inc = _SHOP_PATH."/webservices.php";
				break;
				default;
					echo 'Webservice not found';
					return false;
				break;
			}
			
			if(!empty($inc) && stream_resolve_include_path($inc)){
				include_once $inc;
			}else{
				echo "webservice not found ($inc)";
				return false;
			}
		}
		
		
		private function fix_obj(&$object){
			
			//recast session objects
			if (get_class($object) == '__PHP_Incomplete_Class')
				return ($object = unserialize(serialize($object)));
			else
				return $object;
		
		}#end fix_obj
	
	}#end Jump4
	