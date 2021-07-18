<?php


namespace ReactApp;


class DBALConfig
{
    const DRIVER_MYSQL = 1;
    const DRIVER_SQLITE = 2;

    private int $driver;
    private string $host = '127.0.0.1';
    private int $port = 3306;
    private string $user = 'root';
    private string $password = 'root';
    private string $dbName = 'test';

    /**
     * DBALConfig constructor.
     * @param int $driver
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $dbName
     */
    public function __construct(int $driver, string $dbName, string $host = "", int $port = 0, string $user = "", string $password = "")
    {
        $this->driver = $driver;
        $this->dbName = $dbName;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * @return int
     */
    public function getDriver(): int
    {
        return $this->driver;
    }


    public function get(): array
    {
        return [
            $this->host,
            $this->port,
            $this->user,
            $this->password,
            $this->dbName,
        ];
    }
}