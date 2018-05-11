<?php

namespace MarkKimsal\Mqtt\Packet;

class Publish extends Base {

	protected $msg   = NULL;
	protected $topic = NULL;

	public function __construct($hdr, $data) {
//		$this->dumphex($hdr);
//		$this->dumphex($data);
		/*
		$len   = unpack('C', substr($data, 0, 1));
		$this->len   = $len[1];
		 */

		$len  = unpack('n', substr($data, 0, 2)); 
		$len  = $len[1];
		$data = substr($data, 2);
		
		$this->topic  = substr($data, 0, $len);
		$data = substr($data, $len);

		$this->msg = $data;
	}

	public function getTopic() {
		return $this->topic;
	}

	public function getMessage() {
		return $this->msg;
	}
}
