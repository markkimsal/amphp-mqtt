<?php

namespace MarkKimsal\Mqtt\Packet;

use function MarkKimsal\Mqtt\dumphex;

class Factory {

	const CONNACK   = 0x20;
	const SUBACK    = 0x90;
	const PUBLISH   = 0x30;
	const PUBACK    = 0x40;
	const PUBREC    = 0x50;
	const PUBREL    = 0x60;
	const PUBCOMP   = 0x70;
	const SUBSCRIBE = 0x80;

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
		if ($highbit == Factory::PUBACK) {
			if (strlen($data) < 2) {
				throw new \Exception('not enough payload for publish');
			}
			$packet = new Puback();
			$packet->fromNetwork($hdr, $data);
		}
		if ($highbit == Factory::PUBREC) {
			if (strlen($data) < 2) {
				throw new \Exception('not enough payload for publish');
			}
			$packet = new Pubrec();
			$packet->fromNetwork($hdr, $data);
		}
		if ($highbit == Factory::PUBREL) {
			if (strlen($data) < 2) {
				throw new \Exception('not enough payload for publish');
			}
			$packet = new Pubrel();
			$packet->fromNetwork($hdr, $data);
		}
		if ($highbit == Factory::PUBCOMP) {
			if (strlen($data) < 2) {
				throw new \Exception('not enough payload for publish');
			}
			$packet = new Pubcomp();
			$packet->fromNetwork($hdr, $data);
		}

		if ($packet == NULL) {
			echo "D/Factory: hdr ";
			dumphex($hdr);
			echo "D/Factory: data \n";
			dumphex($data);
			throw new \Exception('Unknown packet of type: '.$highbit);

		}

		return $packet;
	}
}
