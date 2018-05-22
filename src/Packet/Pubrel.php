<?php

namespace MarkKimsal\Mqtt\Packet;

class Pubrel extends Base {

	protected $type   = 0x60;

	public function __construct() {
	}

	public function fromNetwork($hdr, $data) {
//		$this->dumphex($hdr);
//		$this->dumphex($data);

		$msgid = unpack('n', substr($data, 0, 2)); 
		$this->setId($msgid[1]);
		//echo "Got message id of ". $this->getId()."\n";

//		$qos   = unpack('C', substr($data, 2, 1));
//		$this->qos   = $qos[1];
	}

	public function packbytes() {


		$hdr = $this->type | 0x02;

		$pid = $this->getId();

		$vhd  = pack('cn', 2, $pid);

		$buffer  = pack('C*', $hdr).$vhd; 
		//$this->dumphex($buffer);

		return $buffer;
	}

}
