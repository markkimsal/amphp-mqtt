<?php

namespace MarkKimsal\Mqtt\Packet;

class Subscribe extends Base {

	public $type    = 0x80;
	public $version = 0x04;
	public $keepalive = 6;
	public $topic     = '';
	public $flagCleanSession = 0x02;
	public $flagWill         = 0x04;
	public $flagWillQos1     = 0x08;
	public $flagWillQos2     = 0x10;
	public $qos              = 0x02;

	public function __construct() {
		$pid = rand(1,10000);
		$this->setId($pid);
	}

	/**
	 * Subscribing to a Topic Filter at QoS 2 is equivalent to saying,
	 * "I would like to receive Messages matching this filter at the QoS with which they were published".
	 */
	public function setQos($q=2) {
		if ($q >= 0 && $q <= 2) {
			$this->qos = $q;
		}
	}

	public function getTopic() {
		return $this->topic;
	}

	public function setTopic($t) {
		$this->topic = $t;
	}

	public function packbytes() {

		$topic = $this->getTopic();
		//unsigned short 16 byte
		$payload  = pack('n', strlen($topic)).$topic;
		$payload .= pack('c', $this->qos);

		$len = strlen($payload) + 2;

		$pid = pack('n', $this->id);

		//Bits 3,2,1 and 0 of the fixed header of the SUBSCRIBE Control Packet
		//are reserved and MUST be set to 0,0,1 and 0 respectively.
		$hdr = $this->type | 0x02;
			
		$buffer  = pack('C*', $hdr,  $len).$pid.$payload; 

		return $buffer;
	}
}
