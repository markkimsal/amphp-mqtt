<?php

namespace MarkKimsal\Mqtt;

use Amp\Uri\Uri;
use Amp\Deferred;
use Amp\Promise;
use Amp\Succes;
use Evenement\EventEmitterTrait;
use Evenement\EventEmitterInterface;
use function Amp\call;


class Client implements EventEmitterInterface {
	use EventEmitterTrait;

	/** @var Deferred[] */
	protected $deferreds;

	/** @var Deferred[] */
	protected $deferredsById = [];

	/** @var Connection */
	protected $connection;

	/** @var array */
	protected $topicList = [];

	/** @var int */
	protected $timeout = 0;

	/** @var string */
	public $clientId = '';

	/** @var array */
	protected $queue = [];

	protected $connackReceived = false;
	protected $isConnected     = false;
	protected $connackPromisor = null;

	public function __construct(string $uri) {
		$this->applyUri($uri);

		$this->deferreds = [];

		$this->connection = new Connection($uri);

		$this->connection->on("response", function ($response) {
			if ($pid = $response->getId()) {
				echo "D/Client: Response is deferreds by id: ($pid) ".get_class($response)."\n";
				$deferred = $this->deferredsById[$pid];
				//must unset here because
				//some packets send new packets with same ID
				//during their onResolve
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
			if ($this->connackPromisor) {
				$this->connackPromisor->fail(new \Exception('closing socket'));
			}
			$this->isConnected     = false;
			$this->connackReceived = false;
			
			// Fail any outstanding promises
			while ($this->deferreds) {
				/** @var Deferred $deferred */
				$deferred = array_shift($this->deferreds);
				if ($error) {
					$deferred->fail($error);
				} else {
					$deferred->fail(new \Exception("Connection closed"));
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

		if (count($this->topicList) && !empty($this->topicList)) {
			$this->connection->on("connect", function () {
				$promiseList = $this->subscribeToAll($this->topicList, function($err, $resp) {
					#echo "Got subscribe to all response.\n";
				});
			});
		}

		$this->connection->on("open", function () {
			echo "D/Client: socket is open.\n";
		});

		$this->connection->on("connect", function ($response) {
			echo "D/Client: connack received: ".get_class($response)."\n";
			$this->connackReceived = true;
			$this->isConnected     = true;
			$this->connackPromisor->resolve();
			$this->connackPromisor = null;
			$this->flushQueue();
		});
	}

	public function connect($callback = NULL) {
		if ($this->connackPromisor) {
			return $this->connackPromisor->promise();
		}
		if ($this->isConnected) {
			return new Success();
		}

		$this->connackPromisor = new Deferred();

		$connPromise = $this->connection->connect();
		$connPromise->onResolve(function ($err, $result) use ($callback){
			if ($err) {
				$connackPromisor = $this->connackPromisor;
				$this->connackPromisor = null;
				$connackPromisor->fail(new \Exception('socket failed'));
				return;
			}
			echo "D/Client: conn connect resolved.\n";
			$packet = new Packet\Connect();
			if ($this->clientId) {
				$packet->setClientId($this->clientId);
			}
			if ($this->timeout) {
				$packet->setTimeout($this->timeout);
			}
			$packet->setVersion311();

			$this->send($packet , $callback);
		});
		return $this->connackPromisor->promise();
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
			$packet->setQos($qos);
		} else {
			$packet = $msg;
		}
		$packet->setTopic($topic);
		if ($qos < 1) {
			return $this->sendAndForget( $packet , $callback );
		}
		if ($qos == 2) {
			$client = $this;

			$deferred = new Deferred();
			//wrap final callback in pubrel auto-generating callback
			$sendp = $this->send( $packet , function($err, $result) use($client, $deferred) {
				if ($err) {
					$callback($err);
					$deferred->fail($err);
					return;
				}
				$packet = new Packet\Pubrel();
				$packet->setId( $result->getId() );
				$pubcomp = $client->send( $packet );
				$pubcomp->onResolve(function($err, $result) use ($deferred) {
					if ($err) {
						$deferred->fail($err);
					} else {
						$deferred->resolve($result);
					}
				});
			});
			$qosPromise = $deferred->promise();
			$qosPromise->onResolve($callback);
			return $qosPromise;
		}
		if ($qos == 1) {
			return $this->send( $packet , $callback);
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
		if (strlen($newuri->getQueryParameter("topics"))) {
			$this->topicList = explode(',', $newuri->getQueryParameter("topics"));
		}
		$this->clientId  = $newuri->getQueryParameter("clientId");
		$this->timeout   = (int)$newuri->getQueryParameter("timeout");
	}

	private function sendAndForget($packet, callable $callback = null): Promise {
		if (! $this->isConnected) {
			$this->connect();
		}
		if (! $this->connackReceived && !($packet  instanceof Packet\Connect)) {
			$d = new Deferred();
			$p = $d->promise();
			if ($callback) {
				$p->onResolve($callback);
			}
			$this->queue[] = [$packet, $callback, $d];
			return $p;

		}
		$p = $this->_asyncsend($packet);
		if ($callback) {
			$p->onResolve($callback);
		}
		return $p;
	}

	public function send($packet, callable $callback = null): Promise {
		if (! $this->isConnected) {
			$this->connect();
		}

		$deferred = new Deferred();
		if($pid = $packet->getId()) {
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

	/**
	 * Send packets that were queued before we got CONACK
	 * If they are packets which will not have any ack then we
	 * will resolve their deferreds right here after sending.
	 */
	protected function flushQueue() {
		foreach ($this->queue as $_idx => $_struct) {
			$p = $this->_asyncsend($_struct[0]);

			//sometimes we have fire and forget packets for which
			//we will never get a response, just resolve these.
			if (isset($_struct[2]) && $_struct[2] instanceof Deferred) {
				$def = $_struct[2];
				$def->resolve(null, null);
			}
			unset($this->queue[$_idx]);
		}
	}

	protected function _asyncsend($packet, $promise=NULL) {
		return call(function () use ($packet, $promise) {
			yield $this->connection->send($packet);
			if ($promise instanceof \Amp\Promise) yield $promise;
		});
	}
}
