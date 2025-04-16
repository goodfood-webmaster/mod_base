<?php

	include_once "_base.class.php";
		
	class Page extends Base{		
		
		var $instantiated = false;
		var $js = array();
		var $css = array();
		var $page = array();
		var $loadClientInfo = false;

		public function Page($uri=NULL) {//constructor
			
			parent::__construct();
		
			if($this->loadPage($uri))
				$this->instantiated = true;
		
		}//end constructor
		
		
		
		private function loadPage($uri=NULL){
			//load page info from db
			if(empty($uri))
				$uri = $_SERVER['REQUEST_URI'];
			$uri_ = $this->dbEscape($uri);
			$q = "SELECT *
					FROM `".$_SESSION['clientObj']->DB."`.`content_pages`
				   WHERE `page_permalink` = '$uri_'
				     AND `page_status` = 'Y'
				   LIMIT 1
				  ";
			$result = $this->dbQuery($q);
			if(mysqli_num_rows($result)<1)
				return false;
			foreach(mysqli_fetch_assoc($result) as $k=>$v)
				$this->page[str_replace("page_","",$k)] = $this->ifJson($v);
			return true;
		}
		
		
		public function load_scripts(){
		
			//lazy load enqueued external javascript
			?>
			<script>
                <?
				echo "var js = [";
				foreach($this->js as $k=>$v)
					echo '"'.(!empty($v['ver'])?str_replace(".js",".".$v['ver'].".js",$v['src']):$v['src']).'",';
				echo "];\r";
				?>
                function dljs() {
                    var element = document.createElement("script");
                    element.src = "/_scripts/_deferScripts.0.1.js";
                    document.body.appendChild(element);
                }
                if (window.addEventListener)
                    window.addEventListener("load", dljs, false);
                else if (window.attachEvent)
                    window.attachEvent("onload", dljs);
                else 
                    window.onload = dljs;
            </script>
            <?	
			
			
		}
		
		
		public function getTemplate(){
			
			return "_templates/".$this->page['template'].".php";	
			
		}
		
		
		public function enqueue_script($name,$src,$ver=1.0){

			$this->js[$name] = array('src'=>$src,'ver'=>$ver);
		
		
		}
		
		
		public function enqueue_css($name,$src,$ver=1.0){

			$this->css[$name] = array('src'=>$src,'ver'=>$ver);
		
		
		}
		
		
		public function head(){
			
			return "_templates/_header.php";
			
		}
		
		
		public function startBody(){
			
			return "_templates/_bodyStart.php";
			
		}
		
		
		public function endBody(){
			
			return "_templates/_bodyEnd.php";
			
		}



		
		
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
			$out = str_replace(array("dwgreen.s3.amazonaws.com","s3.dwgreen.com"),"webbythefrog.com",$url);
			
			$out .= "?w=$w&h=$h&c=$crop";
			
			return $out;
		}
		
		
	}//end Page class


