<?php


namespace ReactApp\DBAL;


use Drift\DBAL\Connection;
use Drift\DBAL\Result;
use React\Promise\PromiseInterface;

abstract class Model implements \JsonSerializable
{
    protected string $_table;
    protected string $_model;
    protected array $_structure;

    public function __construct(array $structure)
    {
        $this->_structure = $structure;
    }

    abstract public function getId(): ?int;

    public function get(int $id): PromiseInterface
    {
        return self::$connection
            ->findOneBy($this->_table, [
                reset($this->_structure['primary']) => $id
            ])
            ->then(function(?array $result) use ($id) {
                if (is_null($result)) {
                    throw new \Exception(sprintf("%s not found with #%s", $this->_table, $id));
                } else {
                    $this->populate($result);
                }
            });
    }

    public function flush(): PromiseInterface
    {
        if ($this->getId() === null) {
            return self::$connection->insert(
                $this->_table,
                $this->jsonSerialize()
            )->then(function(Result $result) {
                $this->{reset($this->_structure['primary'])} = $result->getLastInsertedId();
                return $result;
            });
        }
        return self::$connection->update(
            $this->_table,
            [reset($this->_structure['primary']) => $this->getId()],
            $this->jsonSerialize()
        );
    }

    public function delete(): PromiseInterface
    {
        return self::$connection->delete($this->_table, [
            reset($this->_structure['primary']) => $this->getId()
        ]);
    }

    public function findOneBy(
        array $where
    ): PromiseInterface {
        return self::$connection
            ->findOneBy($this->_table, $where)
            ->then(function(?array $result) {
                if (is_null($result)) {
                    return null;
                }
                $model = new $this->_model();
                return $model->populate($result);
            });
    }

    public function findBy(
        array $where
    ): PromiseInterface {
        return self::$connection
            ->findBy($this->_table, $where)
            ->then(function (array $results) {
                $models = [];
                foreach ($results as $result) {
                    $models[] = (new $this->_model())->populate($result);
                }
                return $models;
            });
    }

    public function populate(array $data): self
    {
        foreach ($this->_structure['columns'] as $column => $info) {
            if (($data[$column] ?? '_empty_') === '_empty_') {
                continue;
            }
            switch ($info['type']) {
                case 'integer':
                    $this->{$column} = (int) $data[$column];
                    break;
                case 'datetime':
                    $this->{$column} = new \DateTime($data[$column]);
                    break;
                default:
                    $this->{$column} = $data[$column];
            }
        }
        return $this;
    }

    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->_structure['columns'] as $column => $info) {
            if (!isset($this->{$column})) {
                continue;
            }
            if ($this->{$column} instanceof \DateTime) {
                $data[$column] = $this->{$column}->format("Y-m-d H:i:s");
            } else {
                $data[$column] = $this->{$column};
            }
        }
        return $data;
    }

    protected static Connection $connection;
    public static function setConnection(Connection $connection)
    {
        self::$connection = $connection;
    }
}