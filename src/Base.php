<?php

namespace ReactApp;

use Drift\DBAL\Connection;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\Server as SocketServer;
use React\Http\Server;
use React\EventLoop\Loop;
use FastRoute\Dispatcher\GroupCountBased;
use ReactApp\DBAL\DBAL;

class Base
{
    const APP_ROOT = 'app_root';

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

    /**
     * Create a route group with a common prefix.
     *
     * All routes created in the passed callback will have the given group prefix prepended.
     *
     * @param string $prefix
     * @param callable|string $callback
     */
    public function addGroup($prefix, callable|string $callback)
    {
        $this->routes->addGroup($prefix, $this->container->make($callback));
    }

    /**
     * Adds a GET route to the collection
     *
     * This is simply an alias of $this->addRoute('GET', $route, $handler)
     *
     * @param string $route
     * @param mixed  $handler
     */
    public function get($route, $handler)
    {
        $this->routes->get($route, $handler);
    }

    /**
     * Adds a POST route to the collection
     *
     * This is simply an alias of $this->routes->addRoute('POST', $route, $handler)
     *
     * @param string $route
     * @param mixed  $handler
     */
    public function post($route, $handler)
    {
        $this->routes->post($route, $handler);
    }

    /**
     * Adds a PUT route to the collection
     *
     * This is simply an alias of $this->routes->addRoute('PUT', $route, $handler)
     *
     * @param string $route
     * @param mixed  $handler
     */
    public function put($route, $handler)
    {
        $this->routes->put($route, $handler);
    }

    /**
     * Adds a DELETE route to the collection
     *
     * This is simply an alias of $this->routes->addRoute('DELETE', $route, $handler)
     *
     * @param string $route
     * @param mixed  $handler
     */
    public function delete($route, $handler)
    {
        $this->routes->delete($route, $handler);
    }

    /**
     * Adds a PATCH route to the collection
     *
     * This is simply an alias of $this->routes->addRoute('PATCH', $route, $handler)
     *
     * @param string $route
     * @param mixed  $handler
     */
    public function patch($route, $handler)
    {
        $this->routes->patch($route, $handler);
    }

    /**
     * Adds a HEAD route to the collection
     *
     * This is simply an alias of $this->routes->addRoute('HEAD', $route, $handler)
     *
     * @param string $route
     * @param mixed  $handler
     */
    public function head($route, $handler)
    {
        $this->routes->head($route, $handler);
    }

    /**
     * Adds an OPTIONS route to the collection
     *
     * This is simply an alias of $this->routes->addRoute('OPTIONS', $route, $handler)
     *
     * @param string $route
     * @param mixed  $handler
     */
    public function options($route, $handler)
    {
        $this->routes->addRoute('OPTIONS', $route, $handler);
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
        $socket = new SocketServer('127.0.0.1:8000', $this->loop);
        $server->on('error', function (\Throwable $e)
        {
            $this->container->get(LoggerInterface::class)->error($e->getMessage(), $e->getTrace());
        });
        $server->listen($socket);
        echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . "\n";
        $this->loop->run();
    }
}