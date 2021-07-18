<?php

namespace ReactApp;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use function DI\create;
use DI\ContainerBuilder;
use FastRoute\DataGenerator;
use FastRoute\RouteParser;
use Psr\Container\ContainerInterface;
use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteParser\Std;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Container
{
    private static ?ContainerInterface $container = null;

    public static function get(?LoopInterface $loop = null): ContainerInterface
    {
        if (self::$container === null) {
            $builder = new ContainerBuilder;
            $log = new Logger('my_react');
            $log->pushHandler(new StreamHandler(__DIR__ . '../app.log', Logger::WARNING));
            $builder->addDefinitions([
                RouteParser::class     => create(Std::class),
                DataGenerator::class   => create(GroupCountBased::class),
                LoggerInterface::class => $log,
                LoopInterface::class   => $loop,
            ]);
            self::$container = $builder->build();
        }
        return self::$container;
    }
}