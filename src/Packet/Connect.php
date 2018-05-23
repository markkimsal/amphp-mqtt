<?php

namespace MarkKimsal\Mqtt\Packet;

class Connect extends Base {

	protected $version   = 0x04; //3.11
	protected $keepalive = 0;
	protected $clientId  = '';
	protected $flagCleanSession = 0x02;
	protected $flagWill         = 0x04;
	protected $flagWillQos1     = 0x08;
	protected $flagWillQos2     = 0x10;
	protected $enableCleanSession = false;


	public function withCleanSession() {
		$this->enableCleanSession = true;
	}

	public function getId() {
		return FALSE;
	}

	public function setTimeout($t=0) {
		$this->keepalive = $t;
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
		if ($this->enableCleanSession) {
			$flags = $flags | $this->flagCleanSession;
		}
//		$flags = $flags | $this->flagWillQos2;
//		$flags = $flags | $this->flagWill;

		//unsigned char, unsigned short 16byte
		$buffer .= pack('CCn', $this->version, $flags , $this->keepalive);

		return $buffer.$payload;
	}

}
