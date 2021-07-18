<?php

namespace ReactApp;

use FastRoute\RouteCollector;

class Routes
{
    private RouteCollector $routes;

    /**
     * Routes constructor.
     * @param RouteCollector $routes
     */
    public function __construct(RouteCollector $routes)
    {
        $this->routes = $routes;
    }

    public function routes(): RouteCollector
    {
        return $this->routes;
    }

    public function __call($name, $arguments)
    {
        $this->routes->{$name}(...$arguments);
    }
}