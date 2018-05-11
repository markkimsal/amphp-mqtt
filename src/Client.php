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

	/** @var string */
	public $clientId = '';

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
    }

	public function connect() {
		$packet = new Packet\Connect();
		if ($this->clientId) {
			$packet->setClientId($this->clientId);
		}
		$packet->setVersion311();
		return $this->send( $packet , function($response) {
			echo "Connected\n";
			//$response is connack, mostly empty
		});
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

    private function applyUri(string $uri) {
        $this->topicList = explode(',', (new Uri($uri))->getQueryParameter("topics"));
        $this->clientId  = (new Uri($uri))->getQueryParameter("clientId");
    }

    private function send(object $packet, callable $callback = null): Promise {

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

        //return call(function () use ($packet, $callback, $promise) {
        call(function () use ($packet, $promise) {
            yield $this->connection->send($packet);
			yield $promise;
        });
		return $promise;
    }
}
