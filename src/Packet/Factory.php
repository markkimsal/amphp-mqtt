<?php

namespace MarkKimsal\Mqtt\Packet;

use function MarkKimsal\Mqtt\dumphex;

class Factory {

	public const CONNACK = 0x20;
	public const SUBACK  = 0x90;
	public const PUBLISH = 0x30;

	public static function create($hdr, $data) {
		$packet = NULL;
		#echo "D/Factory: hdr ";
		#dumphex($hdr);
		#echo "D/Factory: data \n";
		#dumphex($data);
		$highbit = $hdr & 0xF0;
		if ($highbit == Factory::CONNACK) {
			if (strlen($data) < 2) {
				throw new \Exception('not enough payload for connack');
			}
			$packet = new Connack();
			$packet->fromNetwork($hdr, $data);
		}
		if ($highbit == Factory::SUBACK) {
			if (strlen($data) < 2) {
				throw new \Exception('not enough payload for suback');
			}
			$packet = new Suback();
			$packet->fromNetwork($hdr, $data);
		}
		if ($highbit == Factory::PUBLISH) {
			if (strlen($data) < 2) {
				throw new \Exception('not enough payload for publish');
			}
			$packet = new Publish();
			$packet->fromNetwork($hdr, $data);
		}

		if ($packet == NULL) {
				throw new \Exception('Unknown packet of type: '.$highbit);

		}

		return $packet;
	}
}
