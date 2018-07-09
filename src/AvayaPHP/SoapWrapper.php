<?php

namespace AvayaPHP;

class SoapWrapper {

	private $soap, $sessionID = false, $last_raw_xml = '';

	public function __construct($wsdl, $username, $password) {
		// if (!file_exists($wsdl)) {
		// 	error_log("WSDL does not exist (Avaya)"); 
		// 	return false;
		// }

    	$opts = [
        	'http' => [
            	'user_agent' => 'PHPSoapClient-Avaya'
            ],
            'ssl' => [
            	'verify_peer' => false,
            	'verify_peer_name' => false,
            	'allow_self_signed' => true
            ],
        ];
    	$context = stream_context_create($opts);
		$options = [
			'uri'=>'http://schemas.xmlsoap.org/soap/envelope/',
			'stream_context' => $context,
			'connection_timeout'=>15,
			'trace'=>true,
			'encoding'=>'UTF-8',
			'exceptions'=>false,
			//'cache_wsdl' => WSDL_CACHE_MEMORY,
			'cache_wsdl' => WSDL_CACHE_NONE,
		    'login' => $username,
		    'password' => $password
		];

		try {
			$this->soap = new \SoapClient($wsdl, $options);
		}
		catch (Exception $e) {
			error_log("Failed to create new SoapClient (Avaya): ".$e->getMessage()); 
			return false;
		}
	}

	public function start() {
		try {
			$this->soap->attach();
			$xml = simplexml_load_string($this->soap->__getLastResponse());
			foreach ($xml->children('soapenv', true)->Header->children('ns1', true)->sessionID as $sid) {
				$this->sessionID = $sid;
			}
			$header = new \SoapHeader('http://xml.avaya.com/ws/session', 'sessionID', $this->sessionID);
			$this->soap->__setSoapHeaders($header);
		} catch (Exception $e) {
			error_log("SOAP attach failed (Avaya): ".$e->getMessage()); 
			return false; // failed to start session
		}
		return true; // session is active
	}

	public function request($method, $params) {
		if (!$this->sessionID and !$this->start()) return false; // failed to start session
		try {
			$response = $this->soap->$method($params);
		} catch (Exception $e) {
			error_log("SOAP request failed (Avaya): ".$e->getMessage()); 
		}
	}

}
