<?php

namespace ReactApp\DBAL;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Drift\DBAL\Connection;
use Drift\DBAL\Driver\Mysql\MysqlDriver;
use Drift\DBAL\Driver\SQLite\SQLiteDriver;
use Drift\DBAL\Credentials;
use React\EventLoop\LoopInterface;

class DBAL
{
    private static ?Connection $connection = null;
    private static ?LoopInterface $loop   = null;
    private static ?Config        $config = null;

    /**
     * DBAL constructor.
     */
    public function __construct(LoopInterface $loop, ?Config $config = null)
    {
        self::$loop = $loop;
        if ($config !== null) {
            $this->addConfig($config);
        }
    }

    public function addConfig(Config $config)
    {
        self::$config = $config;
        switch ($config->getDriver()) {
            case Config::DRIVER_SQLITE;
                $platform = new SQLitePlatform();
                $driver = new SQLiteDriver(self::$loop);
                break;
            case Config::DRIVER_MYSQL;
            default:
                $platform = new MySqlPlatform();
                $driver = new MysqlDriver(self::$loop);
        }
        $credentials = new Credentials(...$config->get());
        self::$connection = Connection::createConnected(
            $driver,
            $credentials,
            $platform
        );
        Model::setConnection(self::$connection);
    }
    public function connection(): Connection
    {
        return self::get();
    }

    public static function get(): Connection
    {
        if (self::$connection === null) {
            $mysqlPlatform = new MySqlPlatform();
            $mysqlDriver = new MysqlDriver(self::$loop);
            $credentials = new Credentials(
                '127.0.0.1',
                '3306',
                'root',
                'root',
                'test',
            );

            self::$connection = Connection::createConnected(
                $mysqlDriver,
                $credentials,
                $mysqlPlatform
            );
        }
        return self::$connection;
    }
}
