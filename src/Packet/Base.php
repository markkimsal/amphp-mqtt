<?php

namespace MarkKimsal\Mqtt\Packet;

use function MarkKimsal\Mqtt\dumphex;

class Base {

	public $id = '';

	public function fromNetwork($hdr, $data) {
	}

	public function setId($id) {
		$this->id = $id;
		return $this->getId();
	}

	public function getId() {
		return $this->id;
	}

	public function isFailure() {
		return FALSE;
	}

	public function dumphex($data) {
		dumphex($data);
	}
}
