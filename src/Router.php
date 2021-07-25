<?php

namespace ReactApp;
use Closure;
use ReflectionFunction;
use LogicException;
use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

final class Router
{
    private ContainerInterface $container;
    private GroupCountBased $dispatcher;

    /**
     * Router constructor.
     * @param GroupCountBased $dispatcher
     */
    public function __construct(GroupCountBased $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->container = Container::get();
    }

    /**
     * @throws \ReflectionException
     */
    public function __invoke(ServerRequestInterface $request)
    {
        $this->container->set(ServerRequestInterface::class, $request);
        $route = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());
        switch ($route[0]) {
            case Dispatcher::NOT_FOUND:
                return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
            case Dispatcher::METHOD_NOT_ALLOWED:
                return new Response(405, ['Content-Type' => 'text/plain'], 'Method not allowed');
            case Dispatcher::FOUND:
                $controller = $route[1];
                $parameters = $route[2];
                $closure = $controller;
                if (!($controller instanceof Closure)) {
                    $closure = Closure::fromCallable($controller);
                }
                $reflect = new ReflectionFunction($closure);
                $args = [];
                foreach ($reflect->getParameters() as $parameter) {
                    if ($this->container->has($parameter->getType()->getName())) {
                        $args[] = $this->container->get($parameter->getType()->getName());
                    } else {
                        $args[] = $parameters[$parameter->getName()] ?? null;
                    }
                }
                if ($controller instanceof Closure) {
                    return $route[1](...$args);
                }
                return $this->container->call($controller, $args);
        }
        throw new LogicException('Something wrong with routing');
    }
}