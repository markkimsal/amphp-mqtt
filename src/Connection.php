<?php

namespace MarkKimsal\Mqtt;

use Amp\Deferred;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\Socket;
use Amp\Success;
use Amp\Uri\Uri;
use Evenement\EventEmitterTrait;
use Evenement\EventEmitterInterface;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\Socket\connect;


class Connection implements EventEmitterInterface {

	use EventEmitterTrait;

    /** @var Deferred */
    private $connectPromisor;

    /** @var Parser */
    private $parser;

    /** @var int */
    private $timeout = 5000;

    /** @var Socket */
    private $socket;

    /** @var string */
    private $uri;

    public function __construct(string $uri) {
        $this->applyUri($uri);
		/*
        $this->handlers = [
            "connect"  => [],
            "message"  => [],
            "response" => [],
            "error"    => [],
            "close"    => [],
        ];
		 */

        $this->parser = new Parser(function ($response) {

            if ($response instanceof BadFormatException) {
                $this->onError($response);
            }

            if ($response instanceof Packet\Publish) {
				$this->emit('message', [$response] );
				return;
			}

            if ($response instanceof Packet\Connack) {
				$this->emit('connect', [$response] );
				return;
			}


			$this->emit('response', [$response] );
        });
    }

    private function applyUri($uri) {
        $uri = new Uri($uri);

        $this->timeout = (int) ($uri->getQueryParameter("timeout") ?? $this->timeout);
        $this->uri = $uri->getScheme() . "://" . $uri->getHost() . ":" . $uri->getPort();
    }

    public function send(object $packet) {
		#echo "D/Connection: sending packet ... ". get_class($packet)."\n";
		
		$buffer = $packet->packbytes();
        return call(function () use ($buffer) {
            yield $this->connect();
            yield $this->socket->write($buffer);
        });
    }

    public function connect() {
        // If we're in the process of connecting already return that same promise
        if ($this->connectPromisor) {
            return $this->connectPromisor->promise();
        }

        // If a read watcher exists we know we're already connected
        if ($this->socket) {
            return new Success;
        }

        $this->connectPromisor = new Deferred;

        $socketPromise = connect($this->uri, (new ClientConnectContext)->withConnectTimeout($this->timeout));

        $socketPromise->onResolve(function ($error, $socket) {
            $connectPromisor = $this->connectPromisor;
            $this->connectPromisor = null;

            if ($error) {
                $connectPromisor->fail(new \Exception(
                    "Connection attempt failed",
                    $code = 0,
                    $error
                ));

                return;
            }

            $this->socket = $socket;

            asyncCall(function () {
                while (null !== $chunk = yield $this->socket->read()) {
                    $this->parser->read($chunk);
                }

                $this->close();
            });
            $connectPromisor->resolve();
        });

        return $this->connectPromisor->promise();
    }

    private function onError(\Throwable $exception) {
		$this->emit('error', [$exception]);
        $this->close();
    }

    public function close() {
        $this->parser->reset();

        if ($this->socket) {
            $this->socket->close();
            $this->socket = null;
        }

		$this->emit('close', []);
    }

    public function __destruct() {
        $this->close();
    }
}
