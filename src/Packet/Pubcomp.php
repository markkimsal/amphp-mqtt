<?php

namespace MarkKimsal\Mqtt\Packet;

class Pubcomp extends Base {

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
}
