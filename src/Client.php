<?php

namespace MarkKimsal\Mqtt;

use Amp\Uri\Uri;
use Amp\Deferred;
use Amp\Promise;
use Evenement\EventEmitterTrait;
use Evenement\EventEmitterInterface;
use function Amp\call;


class Client implements EventEmitterInterface {
	use EventEmitterTrait;

	/** @var Deferred[] */
	protected $deferreds;

	/** @var Deferred[] */
	protected $deferredsById;

	/** @var Connection */
	protected $connection;

	/** @var array */
	protected $topicList;

	/** @var int */
	protected $timeout = 0;

	/** @var string */
	public $clientId = '';

	protected $connackReceived = FALSE;

	public function __construct(string $uri) {
		$this->applyUri($uri);

		$this->deferreds = [];

		$this->connection = new Connection($uri);

		$this->connection->on("response", function ($response) {
			if ($pid = $response->getId()) {
				echo "D/Client: Response is deferreds by id: ($pid) ".get_class($response)."\n";
				$deferred = $this->deferredsById[$pid];
				unset($this->deferredsById[$pid]);
			} else {
				echo "D/Client: Response is untracked deferred: ".get_class($response)."\n";
				$deferred = array_shift($this->deferreds);
			}

			if ($response->isFailure() || $response instanceof \Throwable) {
				$deferred->fail($response);
			} else {
				$deferred->resolve($response);
			}
		});

		$this->connection->on("message", function ($response) {
			$this->emit('message', [$response]); //up the chain
		});

		$this->connection->on('close', function (Throwable $error = null) {
			if ($error) {
				// Fail any outstanding promises
				while ($this->deferreds) {
					/** @var Deferred $deferred */
					$deferred = array_shift($this->deferreds);
					$deferred->fail($error);
				}
			}
		});

		$this->connection->on('error', function (Throwable $error = null) {
			if ($error) {
				// Fail any outstanding promises
				while ($this->deferreds) {
					/** @var Deferred $deferred */
					$deferred = array_shift($this->deferreds);
					$deferred->fail($error);
				}
			}
		});

		if (count($this->topicList)) {
			$this->connection->on("connect", function () {
				$promiseList = $this->subscribeToAll($this->topicList, function($err, $resp) {
					#echo "Got subscribe to all response.\n";
				});
			});
		}

		$this->connection->on("connect", function ($response) {
			echo "D/Client: Response is untracked deferred: ".get_class($response)."\n";
			$this->connackReceived = true;
			$this->flushQueue();
		});
	}

	public function connect($callback = NULL) {
		$packet = new Packet\Connect();
		if ($this->clientId) {
			$packet->setClientId($this->clientId);
		}
		if ($this->timeout) {
			$packet->setTimeout($this->timeout);
		}
		$packet->setVersion311();

		return $this->send($packet , $callback);
	}

	public function subscribeToAll($topics, $callback = NULL) {
		if (!is_array($topics)) {
			$topics = array($topics);
		}

		$promiseList = [];
		foreach ($topics as $t) {
			$promiseList[] = $this->subscribe( $t, $callback);
		}
		return $promiseList;
	}

	public function subscribe($topic, $callback = NULL) {
		$packet = new Packet\Subscribe();
		$packet->setTopic($topic);
		return $this->send( $packet , $callback);
	}

	public function publish($msg, $topic, $qos=0, $callback=NULL) {
		if (! $msg instanceof Packet\Publish) {
			$packet = new Packet\Publish();
			$packet->setMessage($msg);
		} else {
			$packet = $msg;
		}
		$packet->setTopic($topic);
		if ($qos < 1) {
			return $this->sendAndForget( $packet , $callback );
		} else {
			return $this->send( $packet , $callback );
		}
	}

	public function publishRetain($msg, $topic, $qos=0, $callback=NULL) {
		$packet = new Packet\Publish();
		$packet->setMessage($msg);
		$packet->setRetain(true);
		return $this->publish($packet, $topic, $qos, $callback);
	}

	private function applyUri(string $uri) {
		$newuri = new Uri($uri);
		$this->topicList = explode(',', $newuri->getQueryParameter("topics"));
		$this->clientId  = $newuri->getQueryParameter("clientId");
		$this->timeout   = (int)$newuri->getQueryParameter("timeout");
	}

	private function sendAndForget($packet, callable $callback = null): Promise {
		if (! $this->connackReceived && !($packet  instanceof Packet\Connect)) {
			$this->queue[] = [$packet, $callback];
		}
		$p = $this->_asyncsend($packet);
		if ($callback) {
			$p->onResolve($callback);
		}
		return $p;
	}

	private function send($packet, callable $callback = null): Promise {
		$deferred = new Deferred();
		$pid = rand(1,10000);
		if($packet->setId($pid)) {
			echo "D/Client: Packet is deferred by id: ($pid) ".get_class($packet)."\n";
			$this->deferredsById[$pid] = $deferred;
		} else {
			echo "D/Client: Adding untracked deferred for packet: ". get_class($packet)."\n";
			$this->deferreds[] = $deferred;
		}
		$promise = $deferred->promise();
		if ($callback) {
			$promise->onResolve($callback);
		}

		if (! $this->connackReceived && !($packet  instanceof Packet\Connect)) {
			$this->queue[] = [$packet, $callback];
			return $promise;
		}

		$this->_asyncsend($packet, $promise);
		return $promise;
	}

	protected function flushQueue() {
		foreach ($this->queue as $_idx => $_struct) {
			$this->_asyncsend($_struct[0], $_struct[1]);
			unset($this->queue[$_idx]);
		}
	}

	protected function _asyncsend($packet, $promise=NULL) {
		return call(function () use ($packet, $promise) {
			yield $this->connection->send($packet);
			if ($promise) yield $promise;
		});
	}
}
