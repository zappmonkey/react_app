<?php


namespace ReactApp\DBAL;


class Config
{
    const DRIVER_MYSQL  = 'mysql';
    const DRIVER_SQLITE = 'sqlite';

    private string $driver;
    private string $host = '127.0.0.1';
    private int $port = 3306;
    private string $user = 'root';
    private string $password = 'root';
    private string $dbName = 'test';

    /**
     * DBALConfig constructor.
     * @param string $driver
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $dbName
     */
    public function __construct(string $driver, string $dbName, string $host = "", int $port = 0, string $user = "", string $password = "")
    {
        $this->driver = $driver;
        $this->dbName = $dbName;
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getDatabase(): string
    {
        return $this->dbName;
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