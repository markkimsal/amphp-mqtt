<?php

namespace MarkKimsal\Mqtt\Packet;

class Publish extends Base {

	protected $type   = 0x30;
	protected $msg    = null;
	protected $topic  = null;
	protected $retain = false;
	protected $qos    = 0x00;
	protected $dup    = false;

	public function __construct() {

		$pid = rand(1,10000);
		$this->setId($pid);
	}

	public function fromNetwork($hdr, $data) {
//		$this->dumphex($hdr);
//		$this->dumphex($data);

		$dup = $hdr & 0x08;
		$this->setDup($dup);

		$qos = $hdr & 0x06;
		$qos = $qos >> 1;
		$this->setQos($qos);

		$len  = unpack('n', substr($data, 0, 2));
		$len  = $len[1];
		$data = substr($data, 2);
		
		$this->topic  = substr($data, 0, $len);
		$data = substr($data, $len);

		$pid  = unpack('n', substr($data, 0, 2));
		$pid  = $pid[1];
		$this->setId($pid);

		$this->msg = $data;
	}

	public function packbytes() {

		$topic   = $this->getTopic();
		$payload = $this->getMessage();

		$hdr = $this->type | 0x00;
		if ($this->getRetain()) {
			$hdr |= 0x01;
		}

		$hdr |= $this->getQos()<<1;

		$vhd  = pack('n', strlen($topic)).$topic;
		//only required for QoS > 0
		if ($this->getQos() > 0 ) {
			$vhd .= pack('n', $this->getId());
		}

		$len = strlen($vhd) + strlen($payload);
		$len = $this->encodeLength($len);

		$buffer  = pack('C*', $hdr).$len.$vhd.$payload; 
		//$this->dumphex($buffer);

		return $buffer;
	}

	public function setTopic($t) {
		$this->topic = $t;
	}

	public function getTopic() {
		return $this->topic;
	}

	public function setMessage($m) {
		$this->msg = $m;
	}

	public function getMessage() {
		return $this->msg;
	}

	public function setRetain($r=true) {
		$this->retain = (bool)$r;
	}
	public function getRetain() {
		return $this->retain;
	}

	public function setQos($qos) {
		if ($qos >= 0 && $qos <=2) {
			$this->qos = $qos;
		}
	}

	public function getQos() {
		return $this->qos;
	}

	public function setDup($dup) {
		$this->dup = false;
		if ($dup) {
			$this->dup = true;
		}
	}

	public function isDup() {
		return $this->dup;
	}
}
