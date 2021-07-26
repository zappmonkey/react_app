<?php

namespace ReactApp;
use Closure;
use Psr\Log\LoggerInterface;
use ReactApp\Exception\ServerException;
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
    private LoggerInterface $logger;

    /**
     * Router constructor.
     * @param GroupCountBased $dispatcher
     */
    public function __construct(GroupCountBased $dispatcher)
    {
        $this->dispatcher = $dispatcher;
        $this->container = Container::get();
        $this->logger = $this->container->get(LoggerInterface::class);
    }

    /**
     * @throws \ReflectionException
     */
    public function __invoke(ServerRequestInterface $request)
    {
        $this->container->set(ServerRequestInterface::class, $request);
        try {
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
        } catch (ServerException $e) {
            // Catch server exceptions and return as json
            $this->logger->error($e->getTitle(), $e->jsonSerialize());
            return new Response($e->getCode(), ['Content-Type' => 'application/json'], json_encode($e));
        } catch (\Throwable $e) {
            // Catch server exceptions and return as json
            $this->logger->error($e->getMessage(), $e->getTrace());
            return new Response($e->getCode(), ['Content-Type' => 'application/json'], json_encode([
                'code' => 500,
                'description' => $e->getMessage()
            ]));
        }
    }
}