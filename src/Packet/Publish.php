<?php

namespace MarkKimsal\Mqtt\Packet;

class Publish extends Base {

	protected $type   = 0x30;
	protected $msg    = null;
	protected $topic  = null;
	protected $retain = false;

	public function __construct() {
	}

	public function fromNetwork($hdr, $data) {
//		$this->dumphex($hdr);
//		$this->dumphex($data);

		$len  = unpack('n', substr($data, 0, 2));
		$len  = $len[1];
		$data = substr($data, 2);
		
		$this->topic  = substr($data, 0, $len);
		$data = substr($data, $len);

		$this->msg = $data;
	}

	public function packbytes() {

		$topic   = $this->getTopic();
		$payload = $this->getMessage();
		$qos     = 0x00;

		$hdr = $this->type | 0x00;
		if ($this->getRetain()) {
			$hdr |= 0x01;
		}

		$hdr |= $qos;

		$vhd  = pack('n', strlen($topic)).$topic;
		//only required for QoS > 0
		if ($qos > 0 ) {
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
}
