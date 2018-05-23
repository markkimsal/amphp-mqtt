<?php

namespace MarkKimsal\Mqtt\Test;

use MarkKimsal\Mqtt\Parser;
use MarkKimsal\Mqtt\Packet;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {

	protected $parser;

	protected $parsedPacket;
	protected $parsedPacketList;

	public function setUp() {
		$this->parsedPacketList = [];
		$this->parsedPacket     = NULL;
		$this->parser = new Parser(function ($result) {
			$this->parsedPacket       = $result;
			$this->parsedPacketList[] = $result;
		});
	}

	public function test_suback_packet_id() {
		$this->parser->read(pack('C*', 0x90, 0x03, 0x24, 0x2e, 0x00));
		$this->assertSame(9262, $this->parsedPacket->getId());
	}

	public function test_parser_handles_more_data_than_one_packet() {
		//message size is 8 bytes, passing in 9 (after remaining length and header bytes)
		$this->parser->read(pack('C*', 0x30, 0x08, 0x00, 0x03, 0x66, 0x6f, 0x6f, 0x6b, 0x00, 0x01, 0x99));
		$this->assertTrue($this->parsedPacket instanceof Packet\Publish);
		$this->assertEquals(1, $this->parser->bytesRemaining());
	}

	public function test_parser_handles_two_packets() {
		//passing 2 6 byte publish packets
		$this->parser->read(pack('C*', 0x30, 0x08, 0x00, 0x03, 0x66, 0x6f, 0x6f, 0x6b, 0x00, 0x01, 0x30, 0x08, 0x00, 0x03, 0x66, 0x6f, 0x6f, 0x6b, 0x00, 0x01));
		$this->assertTrue($this->parsedPacketList[0] instanceof Packet\Publish);
		$this->assertTrue($this->parsedPacketList[1] instanceof Packet\Publish);
		$this->assertEquals(2, count($this->parsedPacketList));

		$this->assertEquals(0, $this->parser->bytesRemaining());
	}

	public function test_parser_throws_connack_exception() {
		$this->expectException(\Exception::class);
		$this->parser->read(pack('C*', 0x20, 0x02, 0x00, 0x01));

		$this->fail("Expected exception not thrown");
		#$this->assertTrue($this->parsedPacket instanceof Packet\Connack);
	}

	public function todo_parser_handles_dynamic_sizes() {
		//pass more than 128 bytes of information in one packet
	}
}
