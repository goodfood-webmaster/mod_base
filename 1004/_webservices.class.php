<?php

	include_once _BASE_PATH."/_module.class.php";

	abstract class Webservices extends Module {
		
		var $instantiated = false;
		var $clientID;
		var $client = array();
		var $meta = array();
		var $errors = array();
		var $format = "xml";
		var $CData = true;
		
		public function __construct($key) {
			
			parent::__construct();
			
			$back = $this->loadClient($key);
			if($back)
				$this->instantiated = true;	
			else
				$this->throwError("Webservice client not found",400);		
			
			if(isset($_GET['format']))
				$this->format = $_GET['format'];
			
		}//end constructor
		
		
		private function loadClient($key_){
			
			//load client info from db	
			$key = $this->dbEscape($key_);
			$q = "SELECT *
				    FROM `webservice_clients`
				   WHERE `client_key` = '$key'
				     AND `client_status` IN ('Y')
				   LIMIT 1
				 ";
			$result = $this->dbQuery($q);
			if(mysqli_num_rows($result) != 1)
				return false;
			$row = mysqli_fetch_assoc($result);
			foreach($row as $k=>$v)
				$this->client[str_replace("client_","",$k)] = $v;
			$this->clientID = $row['client_id'];
			$this->setMeta("lastAccess",$key_);	
			$this->loadMeta();
			return true;
			
		}
		
		
		private function loadMeta(){
		
			//load client meta
			if(!$this->instantiated) return false;
			$this->meta = array();
			$q = "SELECT *
				    FROM `webservice_meta`
				   WHERE `webservice_client_id` = $this->clientID
				ORDER BY `meta_name`
				 ";
			$result = $this->dbQuery($q);
			while($row = mysqli_fetch_assoc($result))
				$this->meta[$row['meta_name']][] = ($this->isJson($row['meta_value'])?json_decode($row['meta_value'],true):$row['meta_value']);
			return true;
		}
		
		
		protected function setMeta($key_,$val_,$overwrite=false){
		
			//save meta to db
			//if(!$this->instantiated) return false;
			$key = $this->dbEscape($key_);
			if(is_array($val_))
				$val = json_encode($val_);
			else
				$val = $this->dbEscape($val_);
			if($overwrite){
				$q = "DELETE FROM `webservice_meta`
						    WHERE `webservice_client_id` = $this->clientID
							  AND `meta_name` = '$key'
					 ";
				$result = $this->dbQuery($q);
			}
			$q = "INSERT INTO `webservice_meta`
						  SET `meta_name` = '$key',
							  `meta_value` = '$val',
							  `webservice_client_id` = $this->clientID
				 ";
			$result = $this->dbQuery($q);
			return true;
			
		}
		
		
		protected function formatOutput($what,$status=200){
			$what = array('status' => $status) + $what;
			if($this->format == 'json') {
				header('Content-type: application/json');
				echo json_encode($what);
			
			}else if($this->format == 'csv' && $what['csv']) {
				header('Content-Type: application/octet-stream');
				header("Content-Transfer-Encoding: Binary"); 
				header("Content-disposition: attachment; filename=\"" . ($what['filename']?:"export.csv") . "\""); 
				echo $what['csv'];
			
			}else{
				header('Content-Type: text/xml; charset=UTF-8');
				$xmlObj = new SimpleXMLExtended("<?xml version=\"1.0\"?><response></response>");
				$this->array_to_xml($what,$xmlObj);
				echo $xmlObj->asXML();	
			}
			
		}
		
		
		public function throwError($msg,$status=500){
			
			$out = array("error" => array('message' => $msg));
			
			$this->formatOutput($out,$status);
			exit;
			
		}
		
		
		private function array_to_xml($myArray, &$xmlObj) {
			foreach($myArray as $key => $value) {
				if(is_array($value)) {
					preg_match_all('/(\d)|(\w)/', $key, $matches);
					$num = implode($matches[1]);
					$let = implode($matches[2]);
					$subnode = $xmlObj->addChild($let?:"item");
					if(!empty($num) || $num=="0"){
						$subnode->addAttribute('id', "$num");
					}
					$this->array_to_xml($value, $subnode);
				}else {
					if(is_numeric($key))
						$key = "item";
					if(!$this->CData){
						$node_note = $xmlObj->addChild("$key","$value");
					}else{
						$node_note = $xmlObj->addChild("$key");
						$node_note->addCData("$value");
					}
				}
			}
		}
		
		
	}//end Webservices class
	
		
	class SimpleXMLExtended extends SimpleXMLElement{ 
		public function addCData($cdata_text){ 
			$node= dom_import_simplexml($this); 
			$no = $node->ownerDocument;
			$node->appendChild($no->createCDATASection($cdata_text)); 
		} 
	} 
	
	