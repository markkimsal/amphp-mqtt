<?php

namespace MarkKimsal\Mqtt\Packet;

class Pubcomp extends Base {

	protected $type   = 0x70;

	public function __construct() {
	}

	public function fromNetwork($hdr, $data) {
//		$this->dumphex($hdr);
//		$this->dumphex($data);

		$msgid = unpack('n', substr($data, 0, 2));
		$this->setId($msgid[1]);
	}

	public function packbytes() {

		$hdr = $this->type | 0x00;

		$pid = $this->getId();

		$vhd  = pack('cn', 2, $pid);

		$buffer  = pack('C*', $hdr).$vhd;

		return $buffer;
	}
}
