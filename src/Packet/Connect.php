<?php

namespace MarkKimsal\Mqtt\Packet;

class Connect extends Base {

	protected $type      = 0x10;
	protected $version   = 0x04; //3.11
	protected $keepalive = 0;
	protected $clientId  = '';
	protected $flagCleanSession = 0x02;
	protected $flagWill         = 0x04;
	protected $flagWillQos1     = 0x08;
	protected $flagWillQos2     = 0x10;
	protected $flagUsername     = 0x80;
	protected $flagPassword     = 0x40;
	protected $enableCleanSession = false;
	protected $username         = '';
	protected $password         = '';


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
	public function setUsername($un) {
		$this->username = $un;
	}
	public function setPassword($pwd) {
		$this->password = $pwd;
	}

	public function setVersion311() {
		$this->version = 0x04;
	}

	public function packbytes() {

		$flags = 0x00;
		if ($this->enableCleanSession) {
			$flags = $flags | $this->flagCleanSession;
		}

		//unsigned short 16 byte
		$payload  = pack('n', strlen($this->clientId)). $this->clientId;

		if ($this->username) {
			$payload .= pack('n', strlen($this->username)). $this->username;
			$flags = $flags | $this->flagUsername;
		}
		if ($this->password) {
			$payload .= pack('n', strlen($this->password)). $this->password;
			$flags = $flags | $this->flagPassword;
		}

		//+6 for MSB/LSB and MQTT
		//+4 for protocol level (1), flags (1), and keepalive (2)
		$len = strlen($payload) + 6 + 4;

		$hdr  = pack('C*', $this->type, $len , 0x00 , 0x04). 'MQTT';

//		$flags = $flags | $this->flagWillQos2;
//		$flags = $flags | $this->flagWill;

		//unsigned char, unsigned short 16byte
		$hdr .= pack('CCn', $this->version, $flags , $this->keepalive);

		return $hdr.$payload;
	}

}
