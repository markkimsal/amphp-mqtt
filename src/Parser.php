<?php

namespace MarkKimsal\Mqtt;
use function MarkKimsal\Mqtt\dumphex;

class Parser {

	private $responseCallback;
	private $buffer = "";

	public function __construct(callable $responseCallback) {
		$this->responseCallback = $responseCallback;
	}


	public function read(string $bytes) {

		$this->buffer .= $bytes;
		while (true) {
			if (strlen($this->buffer) <  2) {
				return;
			}

			//check first 2 bytes for type and length
			$type      = unpack('C', substr($this->buffer, 0, 1));
			$type      = $type[1];
			$offset     = 0;
			$multiplier = 1;
			$length     = 0;
			#echo "D/Parser: buffer contents\n";
			#dumphex($this->buffer);
			do {
				$offset++;
				$lengthbit = unpack('C', substr($this->buffer, $offset, 1));
				$length += ($lengthbit[1] & 127) * $multiplier;
				$multiplier *= 128;
				//echo  "got packet length of ". $length."\n";
				//echo "offset $offset\n";
			} while (($lengthbit[1] & 0x80) == 0x80);

			#print "D/Parser: got packet length of : ".$length."\n";
			#print "D/Parser:  consisting of  ".$offset." bytes\n";

			//echo  "got packet length of ". $length."\n";
			if (strlen($this->buffer) < $length +$offset+1) {
				//not whole packet yet
				#echo "D/Parser not whole packet yet...\n";
				return;
			}
			$packet = substr($this->buffer, $offset+1, $length);
			$this->buffer = substr($this->buffer, $length+$offset+1);
			//echo "D/Parser: *** strlen of buffer *** ". $this->bytesRemaining()."\n";
			$callback = $this->responseCallback;
			$callback(
				Packet\Factory::create($type, $packet)
			);
		}
	}

	public function bytesRemaining() {
		return strlen($this->buffer);
	}

	public function reset() {
		$this->buffer = "";
	}
}
