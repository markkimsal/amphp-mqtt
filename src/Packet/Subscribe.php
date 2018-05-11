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

	public function getTopic() {
		return $this->topic;
	}

	public function setTopic($t) {
		$this->topic = $t;
	}

	public function packbytes() {

		$topic = $this->getTopic();
		$qos   = 0x00;
		//unsigned short 16 byte
		$payload  = pack('n', strlen($topic)).$topic;
		$payload .= pack('c', $qos);
		//payload plus variable header (packet id field)
//		print strlen($payload)."\n";
//		print $payload."\n";

		$len = strlen($payload) + 2;

		$pid = pack('n', $this->id);

		$hdr = $this->type | 0x02;
			
		$buffer  = pack('C*', $hdr,  $len).$pid.$payload; 

		return $buffer;
	}
}
