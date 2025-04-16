<?php
	/*
		EXAMPLE FUNCTION CALL:
			mailout($to, $subject, $body, $from, $replyto[optional], $type[optional])
	*/	
	
	function mailout($to, $subject, $body, $from, $replyto = NULL, $type = "text/html"){	
		// check for name in from variable, strip out for dns check
		if(strpos($from,"<")!== false){
			$chkFrom = substr($from,strpos($from,"<")+1,-1);
			$fromName = substr($from,0,strpos($from,"<"));
			$fromName = str_replace(array("."),"",$fromName);
			$from = "$fromName <$chkFrom>";
		}else{
			$chkFrom = $from;
		}
		// take a given email address and split it into the username and domain.
		list($user, $mailDomain) = explode("@", $chkFrom);
		//
		if (!empty($from) && !empty($mailDomain) && checkdnsrr($mailDomain, "MX")){
			// this is a valid email domain!
			if(!$replyto)
				$replyto = $from;
		}else{
			// this email domain doesn't exist! bad dog! no biscuit!
			$from = "webmaster@axythemander.com";
			if(!$replyto)
				$replyto = "NO VALID EMAIL ADDRESS PROVIDED <no@email.provided>";
		}
		//
		$who = array();
		if(is_array($to)){
			foreach($to as $k=>$v)
				$who[$k] = str_replace(" ","+",$v);
		}else{
			$who[] = str_replace(" ","+",$to);
		}
		//
		if(!class_exists("Mailgun\Mailgun")){
			//if Mailgun not available try mail()
			if(is_array($to))
				$to = implode(", ",$to);
			mail($to,$subject,$body,"From:$from\nContent-Type:$type\n");
			if($to != "webmaster@dwgreen.com")
				mail("webmaster@dwgreen.com","NO MAILGUN! ".$subject,$body,"From:$from\nContent-Type:$type\n");
			//
		}else{
			//use Mailgun
			if(defined("_MG_DOMAIN"))
				$domain = _MG_DOMAIN;
			else
				$domain = "axythemander.com";
			$key = Creds::decrypt('mailgun2020.key',true);
			$mg = Mailgun\Mailgun::create($key);		
			$headers = array('from' => $from,
							 'to' => $who, 
							 'subject' => $subject, 
							 'reply-to' => $replyto,
							 'html' =>'',
							 'text' =>''
							);
			if($type == "text/html")
				$headers['html'] = $body;
			else
				$headers['text'] = $body;
			//				
			$mail = $mg->messages()->send($domain, $headers);
			//
			if(!in_array("webmaster@dwgreen.com",$who)){
				//send backup to webmaster				
				$headers['to'] = "webmaster@dwgreen.com";
				$headers['subject'] = "BACKUP!!! $subject";
				$headers['to'] = "webmaster@dwgreen.com";
				$headers['text'] .= PHP_EOL."BACKUP!!! From: https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].
									PHP_EOL."Sent to: ".(is_array($to)?implode(",",$to):$to).
									PHP_EOL."IP:".$_SERVER['REMOTE_ADDR'];			
				$mg->messages()->send($domain, $headers);
			}
		}
	}//end mailout
	
