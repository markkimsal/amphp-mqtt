<?php

namespace MarkKimsal\Mqtt\Packet;

class Connect extends Base {

	public $version = 0x04;
	public $keepalive = 10;
	public $clientId  = '';
	public $flagCleanSession = 0x02;
	public $flagWill         = 0x04;
	public $flagWillQos1     = 0x08;
	public $flagWillQos2     = 0x10;

	public function getId() {
		return FALSE;
	}

	public function setClientId($cid) {
		$this->clientId = $cid;
	}

	public function setVersion311() {
		$this->version = 0x04;
	}

	public function packbytes() {


		//unsigned short 16 byte
		$payload = pack('n', strlen($this->clientId)). $this->clientId;
		$len = strlen($payload) + 6 + 4;


		$buffer  = pack('C*', 0x10, $len , 0x00 , 0x04). 'MQTT';

		$flags = 0x00;
		$flags = $flags | $this->flagCleanSession;
//		$flags = $flags | $this->flagWillQos2;
//		$flags = $flags | $this->flagWill;

		//unsigned char, unsigned short 16byte
		$buffer .= pack('CCn', $this->version, $flags , $this->keepalive);

		return $buffer.$payload;
	}

}
