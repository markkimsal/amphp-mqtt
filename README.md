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

Loop::run( function($l) {

	$client = new Client('tcp://172.17.0.1:1883?topics=foo,bar&clientId=abc123');

	$p = $client->connect();

	$client->on('message', function($publishPacket) {
		echo "****** got a message on topic: [".$publishPacket->getTopic()."] ***** \n";
		echo $publishPacket->getMessage()."\n";
	});

	$p->onResolve(function($err, $resp) use($p, $client){
		echo "****** CONNECT Resolved ********\n";

		$p2 = $client->subscribe('test/', function($err, $resp) {
			echo "***** SUBSCRIBE Resolved *******\n";
			var_dump($err);
			var_dump($resp);
		});

		$p2->onResolve(function($err, $res) {
			echo "***** SUBSCRIBE Resolved in a different way *******\n";
			var_dump($err);
			var_dump($res);
		});

	});
});
```
