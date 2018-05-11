<?php

namespace MarkKimsal\Mqtt\Packet;

class Connack extends Base {

	public $sessionPresent = false;

	public function getId() {
		return FALSE;
	}

	public function __construct() {
	}

	public function fromNetwork($hdr, $data) {

		$rsp = unpack('C', substr($data, 1, 1));
		$rsp = $rsp[1];
		if ($hdr & 0x01) {
			$this->sessionPresent = true;
		}

		if ($rsp == 0) {
			return;
		}
		if ($rsp & 0x01) {
			throw new \Exception("Unaccepted protocol version");
		}

		if ($rsp & 0x02) {
			throw new \Exception("Identifier Rejected");
		}

		if ($rsp & 0x03) {
			throw new \Exception("Server unavailable");
		}

		if ($rsp & 0x04) {
			throw new \Exception("Bad username");
		}

		if ($rsp & 0x05) {
			throw new \Exception("Not authorized");
		}
	}
}
