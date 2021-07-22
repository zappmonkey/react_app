<?php

namespace ReactApp;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Drift\DBAL\Connection;
use Drift\DBAL\Driver\Mysql\MysqlDriver;
use Drift\DBAL\Driver\SQLite\SQLiteDriver;
use Drift\DBAL\Credentials;
use React\EventLoop\LoopInterface;
use ZAPPStudio\API\Base\Model;

class DBAL
{
    private static ?Connection $connection = null;
    private static ?LoopInterface $loop = null;
    private static ?DBALConfig $config = null;

    /**
     * DBAL constructor.
     */
    public function __construct(LoopInterface $loop, ?DBALConfig $config = null)
    {
        self::$loop = $loop;
        if ($config !== null) {
            $this->addConfig($config);
        }
    }

    public function addConfig(DBALConfig $config)
    {
        self::$config = $config;
        switch ($config->getDriver()) {
            case DBALConfig::DRIVER_SQLITE;
                $platform = new SqlitePlatform();
                $driver = new SQLiteDriver(self::$loop);
                break;
            case DBALConfig::DRIVER_MYSQL;
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