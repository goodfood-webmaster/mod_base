<?php
	#1003
	include_once "_base.class.php";
		
	abstract class Module extends Base{		
		
		var $images = array();
		var $prefs = array('retina_optimization'=>false);
		
		protected function Module() {//constructor
			
			parent::__construct();
		
		
		}//end constructor
		
		
		
		public function generateMinifyGroup($path,$path_local,$path_global,$contentType,$findReplace=array(),$includes=array()){
			
			//generate minify group		
			$src = array();
			foreach($includes as $k=>$s){
				$src[] = new Minify_Source(array(
					'id' => 'h'.$k,
					'getContentFunc' => 'getContents',
					'contentType' => ($contentType=="CSS"?Minify::TYPE_CSS:Minify::TYPE_JS),    
					'lastModified' => $this->getLastModTime($s['file'],$path_local,$path_global),
					'args' => array($s['file'],$path,$findReplace)
				));
			}
			return $src;
		}
		
		public function getLastModTime($foil,$path_local,$path_global){
			
			$fs1=$fs2=0;
			if(is_file($path_global.$foil))
				$fs1 = filemtime($path_global.$foil);
				
			if(is_file($path_local.$foil))
				$fs2 = filemtime($path_local.$foil);
					
			return max($fs1,$fs2);
										  
		}
		
		
		public function getRecipeLink($recipe_id, $recipe_name=NULL, $full=false, $tag=false){
		
			//return formatted recipe link url
			global $accentBad,$accentGood,$permaBad;
			$out = "";
	
			if($full)
				$out = "http://".$_SERVER['HTTP_HOST'];
			
			if(empty($recipe_name) && is_numeric($recipe_id)){
				$sql = "SELECT recipe_name 
						  FROM `dwgreen_cms`.`recipes` 
						 WHERE `recipe_id`=$recipe_id 
						   AND `recipe_client` IN ('".implode("','",$this->clientObj->modules['recipes']['clients'])."')
						   AND `recipe_status` IN (".$this->clientObj->modules['recipes']['status'].") 
						 LIMIT 1";
				$result = $this->dbQuery($sql);
				$row = mysqli_fetch_array($result);
				$recipe_name = $row['recipe_name'];
			}
			$out .= $this->clientObj->modules['recipes']['path'];
			
			if(is_numeric($recipe_id) && !empty($recipe_name))
				$out .= "$recipe_id/".str_replace($permaBad,"_",str_replace($accentBad,$accentGood,htmlentities(html_entity_decode($recipe_name))));
			
			if($tag)
				$out = '<a href="'.$out.'">'.$recipe_name.'</a>';
				
			return $out;
		
		}//end getRecipeLink
		
		public function getArticleLink($article_id, $article_title=NULL, $full=false){
		
			//return formatted article link url
			global $accentBad,$accentGood,$permaBad;
			$out = "";
	
			if($full)
				$out = "http://".$_SERVER['HTTP_HOST'];
			
			if(empty($article_title) && is_numeric($article_id)){
				$sql = "SELECT article_title 
						  FROM `dwgreen_cms`.`wellness_articles` 
						 WHERE `article_id`=$article_id 
						   AND `article_status` IN ('".$this->clientObj->modules['wellness']['status']."') 
						 LIMIT 1";
				$result = $this->dbQuery($sql);
				$row = mysqli_fetch_array($result);
				$article_title = $row['article_title'];
			}
			$out .= $this->clientObj->modules['wellness']['path'];
			
			if(is_numeric($article_id) && !empty($article_title))
				$out .= "$article_id/".str_replace($permaBad,"_",str_replace($accentBad,$accentGood,htmlentities(html_entity_decode($article_title))));
				
			return $out;
		
		}//end getArticleLink
		
		
		public function getPhoto($file,$size="med",$course=""){
			//
			if(!$size)
				$size = "med";
			//	
			if(!isset($this->images[1000]) || $this->images[1000] != $file || empty($file)){
				//
				$this->images = array();
				if(strpos($file,"//")===false && !empty($file)){
					$file = (defined("_CDN_URL")?_CDN_URL:$this->prefs['cmsDomain']).$file;
					
				}else if(strpos($file,"/module_uploads")!==false){
					//handle amazon hosted images
					if(!is_numeric($size)){
						switch($size){
							case "small":
								$size = 100;
								break;
							case "med":
								$size = 300;
								break;
							case "large":
								$size = 500;
								break;
							case "xlarge":
								$size = 800;
								break;
							default:
								$size = 200;
						}
					}
					$size = $size*$res_mult;
					$file_name = substr($file,strpos($file,"/module_uploads")+15);
					return "http://media2.festivalfoods.net".$file_name."?w=".$size;
				}else{
					$file = str_replace("http://cms.dwgreen.com",(defined("_CDN_URL")?_CDN_URL:$this->prefs['cmsDomain']),$file);
				}
				//check to see if file available
				if(!$this->check4file($file) || empty($file)){
					$file = (defined("_CDN_URL")?_CDN_URL:$this->prefs['cmsDomain'])."/_photos/recipes/default".rand(1,10).".jpg";
				}else if($resolution >= 1000){
					$this->images[1000] = $file;
				}
				//check to see what file sizes are available
				
				if(!empty($file) && strpos($file,'amazon')===false){
					foreach(array(100,200,300,400,500,600,700,800,900) as $v){
						$this->images[$v] = $file;
					}
				}
				//
				foreach(array(100,200,300,400,500,600,700,800) as $v){
					if(!empty($this->images[$v]))
						$this->images['xlarge']=$this->images[$v];
				}
				foreach(array(100,200,300,400,500,600) as $v){
					if(!empty($this->images[$v]))
						$this->images['large']=$this->images[$v];
				}
				foreach(array(100,200,300,400) as $v){
					if(!empty($this->images[$v]))
						$this->images['med']=$this->images[$v];
				}
				foreach(array(100,200) as $v){
					if(!empty($this->images[$v]))
						$this->images['thumb']=$this->images[$v];
				}
				$this->images['small']=$this->images[100];
			}
			if($size=="all"){
				//return array of all sizes
				return $this->images;
			}else if($this->images[$size]){
				//return specific size
				return $this->images[$size];				
			}else if(is_numeric($size)){
				//return closest size available
				for($x=$size;$x<=1000;$x++){
					if(!empty($this->images[$x]))
						return $this->images[$x];
				}
				for($x=$size;$x>0;$x--){
					if(!empty($this->images[$x]))
						return $this->images[$x];
				}
			}
			return (defined("_CDN_URL")?_CDN_URL:$this->prefs['cmsDomain'])."/_images/content/spacer.gif?size=$size&file=$file";
			
		}
		
		protected function check4file($file){
			//use non cloudfronted url
			$file = str_replace((defined("_CDN_URL")?_CDN_URL:$this->prefs['cmsDomain']),"http://cms.dwgreen.com",$file);
			//
			//check if file exists
			$ch = curl_init($file);
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_exec($ch);
			$retcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			// $retcode > 400 -> not found, $retcode = 200, found.
			curl_close($ch);
			
			//cache result and return true/false
			if($retcode >= 300){
				return false;
			}else{
				return true;
			}
		}
		
		
		public function getLocations($what="drop",$sel="", $limit=array()){
			#get the fieldname metadata
			foreach($this->clientObj->locationsTable['fieldnames'] as $k=>$v){
				$cmsfieldset[] = $v.' as '.$k; 
			}	
			$cmsfieldset=implode(',',$cmsfieldset);
			$locs = array();
			$out = "";
			$q = "SELECT ".$this->clientObj->locationsTable['fields']." 
					FROM `".$this->clientObj->DB."`.`".$this->clientObj->locationsTable['table']."` 
				   WHERE ".$this->clientObj->locationsTable['active']." 
				ORDER BY ".$this->clientObj->locationsTable['order'];
			$r = $this->dbQuery($q);
			if(mysqli_num_rows($r) > 0){
				while($row = mysqli_fetch_array($r, MYSQLI_ASSOC)){
					if(in_array($row[$this->clientObj->locationsTable['ID']],$limit) || empty($limit)){
						$full[$row[$this->clientObj->locationsTable['ID']]] = $row;
						extract($row);
						$out .= '<option value="'.${$this->clientObj->userTable['storeCol']}.'" '.($_SESSION['userObj']->store == ${$this->clientObj->locationsTable['ID']} || $sel == ${$this->clientObj->locationsTable['nickname']} || $sel == ${$this->clientObj->locationsTable['ID']}?"selected":"").'>'.eval("return \"".$this->clientObj->locationsTable['output']."\";").'</option>';
						$locs[${$this->clientObj->userTable['storeCol']}] = eval("return \"".$this->clientObj->locationsTable['output']."\";");
					}
				}#end while
				
			}#end num_rows
			
			switch($what){
				case 'name';
					$out = $locs[$sel];
				break;
				case 'array';
					$out = $locs;
				case 'full';
					$out = $full;
				break;
			}
			
			return $out;
		}//end getLocations
		
		
		public function getMedia($url,$w=0,$h=0,$crop=0){
			
			/*Request image hosted by Amazon S3 with full path of image as url which is redirected here by .htaccess
			* Pass the following variables in the Query String to resize:
			* &w = width to resize file to
			* &h = height to resize file to
			* &c = direction and amount to crop, 0 or nothing passed with crop the middle, + will crop from bottom, - will crop from top
			* if only a width or height is passed the image will keep aspect and not be cropped
			*
			* example:
			* http://media.dwgreen.com/product_images/07142/071429010772.jpg?w=120&h=120&c=-120
			* redirected to:
			* http://s3.dwgreen.com/product_images_cache/07142/071429010772_120x120x-120.jpg*/
			
			//http://s3.dwgreen.com/product_images/10000/11210/1121000015CF.jpg
			
			#$out = str_replace("s3.dwgreen.com","media.dwgreen.com",$url);
			
			if(strpos($url,"//")===false)
				$url = "https://webbythefrog.com$url";
			$out = str_replace(array("dwgreen.s3.amazonaws.com","s3.dwgreen.com"),"webbythefrog.com",$url);
			
			if($this->clientObj->abbr == "BFS")
				$out = str_replace("webbythefrog.com/product_images/","cdn.bristolfarms.com/",$out);
			else if($this->clientObj->abbr == "FES")
				$out = str_replace("webbythefrog.com/product_images/","dgwc6fpilyqe5.cloudfront.net/",$out);
			else
				$out = str_replace("webbythefrog.com/product_images/","d3p2varhcu19og.cloudfront.net/",$out);
			
			$out .= (strpos($out,"?")===false?"?":"&")."w=$w&h=$h&c=$crop";
			
			return $out;
		}
		
		
	}//end Base class


