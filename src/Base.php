<?php

namespace ReactApp;

use Drift\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Http\Server;
use React\EventLoop\Loop;
use FastRoute\Dispatcher\GroupCountBased;

class Base
{
    private LoopInterface $loop;
    private Routes $routes;
    private ContainerInterface $container;
    private DBAL $dbal;

    /**
     * Base constructor.
     */
    public function __construct()
    {
        $this->loop = Loop::get();
        $this->container = Container::get($this->loop);
        $this->routes = $this->container->make(Routes::class);
    }

    public function __call($method, $arguments)
    {
        $this->routes->{$method}(...$arguments);
    }

    public function setDBAL(DBAL $dbal)
    {
        $this->dbal = $dbal;
        $this->container->set(DBAL::class, $dbal);
        $this->container->set(Connection::class, $dbal->connection());
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function run()
    {
        $server = new Server(new Router(new GroupCountBased($this->routes->routes()->getData())));
        $socket = new \React\Socket\Server('127.0.0.1:8000', $this->loop);
        $server->on('error', function (\Throwable $e)
        {
            $this->container->get(LoggerInterface::class)->error($e->getMessage(), $e->getTrace());
        });
        $server->listen($socket);
        echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";
        $this->loop->run();
    }
}