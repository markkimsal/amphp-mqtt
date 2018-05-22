# MQTT

`markkimsal/amphp-mqtt` is an asynchronous MQTT client for PHP based on Amp.

## Installation

```
composer require markkimsal/amphp-mqtt
```

## Usage

```php
<?php
include('vendor/autoload.php');
use \Amp\Loop;
use \MarkKimsal\Mqtt\Client;

Loop::run( function($w) {

	$client = new Client('tcp://172.17.0.1:1883?topics=foo,bar&clientId=abc123');

	$p = $client->connect();

	$p2 = $client->subscribe('test/', function($err, $resp) {
		echo "***** SUBSCRIBE Resolved *******\n";
		var_dump($err);
		var_dump($resp);
	});

	$p->onResolve(function($err, $resp) use($p, $client){
		echo "****** CONNECT Resolved ********\n";
	});

	$p2->onResolve(function($err, $res) {
		echo "***** SUBSCRIBE Resolved in a different way *******\n";
		var_dump($err);
		var_dump($res);
	});

	$client->on('message', function($publishPacket) {
		echo "****** got a message on topic: [".$publishPacket->getTopic()."] ***** \n";
		echo $publishPacket->getMessage()."\n";
	});

	Loop::repeat(1000, function() use($client){
		$client->publish('Current time is: '.date('H:i:s'), 'time', 0, function($err, $result) {
			if (!$err) {
				echo "***** Socket fired off Publish Packet with qos 0 *****\n";
			}
		});
	});
});
```

Blocking mode (integration with sync code)
===
You can use this library in a synchronous backend to wait for sending packets with QoS 0,1, or 2.

```php
<?php
	include('vendor/autoload.php');

	$client = new MarkKimsal\Mqtt\Client('tcp://172.17.0.1:1883');

	$pconn = $client->connect();

	$p0 = $client->publishRetain('QoS0 Current time is: '.date('H:i:s'), 'time', 0, function($err, $result) {
		if (!$err) {
			echo "***** Socket fired off Publish Packet with qos 0 *****\n";
		}
	});

	$p1 = $client->publish('QoS1 Current time is: '.date('H:i:s'), 'time', 1, function($err, $result) {
		if (!$err) {
			echo "***** Got Publish Ack with qos 1 *****\n";
		}
	});

	$p2 = $client->publish('QoS2 Current time is: '.date('H:i:s'), 'time', 2, function($err, $result) {
		if (!$err) {
			echo "***** Got Publish Ack with qos 2 *****\n";
		}
	});


	Amp\Promise\wait($p0);
	Amp\Promise\wait($p1);
	Amp\Promise\wait($p2);
	return;
```
