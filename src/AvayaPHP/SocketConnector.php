<?php

namespace AvayaPHP;

class SocketConnector {

	private $sessionID = false, $socket = false;
	private $host, $port, $username, $password, $timeout, $invoke_id, $switchName;
	private $protocol_version, $xmlns_xsi, $xmlns_xsd, $xmlns;
	public $show_xml = false;

	public function __construct($host, $port, $username, $password, $switchName = '', $timeout = 180, $show_xml = false) {
		$this->protocol_version = 'http://www.ecma-international.org/standards/ecma-323/csta/ed3/priv2';
		$this->xmlns_xsi = "xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"";
		$this->xmlns_xsd = "xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"";
		$this->xmlns = "xmlns=\"http://www.ecma-international.org/standards/ecma-323/csta/ed3\"";
		$this->host = $host;
		$this->port = $port;
		$this->username = $username;
		$this->password = $password;
		$this->timeout = $timeout;
		$this->invoke_id = '0001';
		$this->switchName = $switchName;
		$this->show_xml = false;
	}

	public function getInvokeId() {
		$invoke_id = $this->invoke_id;
		$this->invoke_id = str_pad(((int)$this->invoke_id)+1, 4, "0", STR_PAD_LEFT);
		return $invoke_id;
	}

	public function start($session_duration = 180) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<StartApplicationSession " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		                "<applicationInfo>" .
		                    "<applicationID>RPTC</applicationID>" .
		                    "<applicationSpecificInfo>" .
		                        "<ns1:SessionLoginInfo xmlns=\"http://www.avaya.com/csta\">" .
		                            "<ns1:userName>" . $this->username . "</ns1:userName>" .
		                            "<ns1:password>" . $this->password . "</ns1:password>" .
		                            "<ns1:sessionCleanupDelay>60</ns1:sessionCleanupDelay>" .
		                        "</ns1:SessionLoginInfo>" .
		                    "</applicationSpecificInfo>" .
		                "</applicationInfo>" .
		                "<requestedProtocolVersions>" .
		                "<protocolVersion>" . $this->protocol_version . "</protocolVersion>" .
		                "</requestedProtocolVersions>" .
		                "<requestedSessionDuration>".$session_duration."</requestedSessionDuration>" .
		            "</StartApplicationSession>";

		$xml = $this->sendXml($request_xml);
		if ($xml) {
			$this->sessionID = $xml->sessionID;
			return true;
		}
		else {
			return false;
		}
	}

	public function keepAlive($session_duration = 180) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<ResetApplicationSessionTimer " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		                "<sessionID>" . $this->sessionID . "</sessionID>" .
		                "<requestedSessionDuration>" . $session_duration . "</requestedSessionDuration>" .
		            "</ResetApplicationSessionTimer>";

		$xml = $this->sendXml($request_xml);
		if ($xml) {
			$this->sessionID = $xml->sessionID;
			return true;
		}
		else {
			return false;
		}
	}

	public function stop() {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<StopApplicationSession " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		                "<sessionID>" . $this->sessionID . "</sessionID>" .
		                "<sessionEndReason>" .
		                	"<appEndReason>Application Request</appEndReason>" .
		                "</sessionEndReason>" .
		            "</StopApplicationSession>";

		$xml = $this->sendXml($request_xml);
		if ($xml) {
			$this->sessionID = false;
			$this->closeSocket();
			return true;
		}
		else {
			return false;
		}
	}

	public function snapshotDevice($agent) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<SnapshotDevice " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		                "<snapshotObject typeOfNumber=\"other\" mediaClass=\"notKnown\">" . $agent . ':' . $this->switchName . "::0</snapshotObject>" .
		            "</SnapshotDevice>";

		$xml = $this->sendXml($request_xml, true);
		if ($xml) {
			if (isset($xml->crossRefIDorSnapshotData->snapshotData->snapshotDeviceResponseInfo->connectionIdentifier->callID)) {
				return $xml->crossRefIDorSnapshotData->snapshotData->snapshotDeviceResponseInfo->connectionIdentifier->callID;
			}
			return false;
			//return $xml->crossRefIDorSnapshotData->snapshotData;
		}
		else {
			return false;
		}
	}

	public function makeCall($agent, $destNo) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<MakeCall " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		                "<callingDevice typeOfNumber=\"other\" mediaClass=\"notKnown\">" . $agent . ':' . $this->switchName . "::0</callingDevice>" .
		           		"<calledDirectoryNumber typeOfNumber=\"other\" mediaClass=\"notKnown\">" . $destNo . ':' . $this->switchName . "::0</calledDirectoryNumber>" .
		            	"<userData>" .
		            		"<string></string>" .
		            	"</userData>" .
		            	"<callCharacteristics>" .
		            		"<priorityCall>false</priorityCall>" .
		            	"</callCharacteristics>" .
		            "</MakeCall>";

		$xml = $this->sendXml($request_xml);
		if ($xml) {
			// $callLinkageID = $xml->extensions->privateData->private->children('ns1', true)->MakeCallResponsePrivateData->callLinkageData->globalCallData->globalCallLinkageID->globallyUniqueCallLinkageID;
			// having issues getting callLinkageID via namespace.. had to regex
			preg_match('#<globallyUniqueCallLinkageID>(.+?)</globallyUniqueCallLinkageID>#is', $this->last_raw_xml, $linkage_tag);
			$callLinkageID = isset($linkage_tag[1]) ? $linkage_tag[1] : false;

			return (object) [
				'callLinkageID' => $callLinkageID, // useful for reporting
				'callID' => $xml->callingDevice->callID[0] // used for clear/hold etc
			];
		}
		else {
			return false;
		}
	}

	public function retrieveCall($agent, $callID) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<RetrieveCall " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		            	"<callToBeRetrieved>" .
		            		"<deviceID typeOfNumber=\"other\" mediaClass=\"notKnown\">".$agent.":".$this->switchName."::0</deviceID>" .
		            		"<callID>".$callID."</callID>" .
		            	"</callToBeRetrieved>" .
		            "</RetrieveCall>";

		$xml = $this->sendXml($request_xml);
		if ($xml) {
			if (isset($xml->unspecified)) return false; // unspecified error
			return true;
		}
		else {
			return false;
		}
	}

	public function holdCall($agent, $callID) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<HoldCall " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		            	"<callToBeHeld>" .
		            		"<deviceID typeOfNumber=\"other\" mediaClass=\"notKnown\">".$agent.":".$this->switchName."::0</deviceID>" .
		            		"<callID>".$callID."</callID>" .
		            	"</callToBeHeld>" .
		            "</HoldCall>";

		$xml = $this->sendXml($request_xml, true);
		if ($xml) {
			if (isset($xml->unspecified)) return false; // unspecified error
			return true;
		}
		else {
			return false;
		}
	}

	public function clearCall($agent, $callID) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<ClearCall " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		            	"<callToBeCleared>" .
		            		"<deviceID>".$agent.":".$this->switchName."::0</deviceID>" .
		            		"<callID>".$callID."</callID>" .
		            	"</callToBeCleared>" .
		            "</ClearCall>";

		$xml = $this->sendXml($request_xml);
		if ($xml) {
			if (isset($xml->unspecified)) return false; // unspecified error
			return true;
		}
		else {
			return false;
		}
	}

	// dial a sequence of digits - working perfectly
	public function generateDigits($agent, $callID, $digits) {
		$request_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" .
		            "<GenerateDigits " . $this->xmlns_xsi ."   " . $this->xmlns_xsd ." xmlns=\"http://www.ecma-international.org/standards/ecma-354/appl_session\">" .
		            	"<connectionToSendDigits>".
		            		"<deviceID typeOfNumber=\"other\" mediaClass=\"notKnown\">".$agent.":".$this->switchName."::0</deviceID>" .
		            		"<callID>".$callID."</callID>".
		            	"</connectionToSendDigits>".
		            	"<charactersToSend>".$digits."</charactersToSend>".
		            "</GenerateDigits>";

		$xml = $this->sendXml($request_xml);
		if ($xml) {
			return true;
		}
		else {
			return false;
		}
	}

	public function getSocket($force_reconnect = false) {
		if (!$this->socket or $force_reconnect) {
			$this->socket = socket_create(AF_INET,SOCK_STREAM,0) or die("Unable to create a socket");
			socket_connect($this->socket, $this->host, $this->port) or die("Unable to connect to the socket");
			// socket_set_option($this->socket, SOL_SOCKET, SO_KEEPALIVE, 1);
		}
		return $this->socket;
	}

	public function closeSocket() {
		if ($this->socket) {
			socket_close($this->socket);
			$this->socket = false;
		}
	}

	/*
	 * For header information refer to: 
	 *      http://www.ecma-international.org/flat/publications/files/ECMA-ST/ECMA-323.pdf
	 * 
	 * | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 |   
	 *  VERSION|LENGTH |   INVOKE ID   |   XML PAYLOAD
	 * 
	 * VERSION: 2 bytes (we hard-code to '00' aka XML without SOAP)
	 * LENGTH: 2 bytes (big-endian packed, that's how we get it down to 2 bytes)
	 * INVOKE ID: 4 bytes (we define this ourselves, from '0001' to '9999')
	 */
	public function sendXml($request_xml) {
		if (!$this->socket) $this->socket = $this->getSocket();

		// if (!$this->socket) die(socket_strerror(socket_last_error())); // show error

		$xml_header_len = 8;
		$msg = $this->getInvokeId().$request_xml;
		$total_len = strlen($request_xml) + $xml_header_len;
		socket_write($this->socket, '00', 2);
		$n_o_total_len = pack('n', $total_len);
		socket_write($this->socket, $n_o_total_len, strlen($n_o_total_len));
		socket_write($this->socket, $msg, strlen($msg));

		usleep(150000);

		$read = socket_read($this->socket, 1024);
		
		$response_xml = $this->last_raw_xml = substr($read, 8);
		if ($this->show_xml) {
			$code = socket_last_error();
			echo $code.PHP_EOL;
			echo socket_strerror($code).PHP_EOL;
			echo $response_xml.PHP_EOL;
		}

		$xml = simplexml_load_string($response_xml);
		return $xml;
	}

}