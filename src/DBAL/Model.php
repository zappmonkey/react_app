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
                return $this;
            });
    }

    public function flush(): PromiseInterface
    {
        if ($this->getId() === null) {
            return self::$connection->insert(
                $this->_table,
                $this->json()
            )->then(function(Result $result) {
                $this->{reset($this->_structure['primary'])} = $result->getLastInsertedId();
                return $this;
            });
        }
        return self::$connection->update(
            $this->_table,
            [reset($this->_structure['primary']) => $this->getId()],
            $this->json()
        )->then(function(Result $result) {
            return $this;
        });
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

    public function list()
    {
        $queryBuilder = self::$connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from($this->_table, 'm');
        return self::$connection
            ->query($queryBuilder)
            ->then(function(Result $results) {
                $items = [];
                if ($results->fetchCount() > 0) {
                    foreach ($results->fetchAllRows() as $result) {
                        $items[] = (new $this->_model())->populate($result);
                    }
                }
                return $items;
            });
    }

    public function getOptions(): array
    {
        $options = [];
        foreach ($this->_structure['columns'] as $column => $info) {
            if ($values = ($info['enum'] ?? false)) {
                $options[$column] = [
                    'values'  => $values,
                    'default' => $info['options']['default'] ?? null,
                ];
            }
        }
        return $options;
    }

    public function jsonSerialize(): array
    {
        return $this->json(false);
    }

    protected function json(bool $allowHidden = true): array
    {
        $data = [];
        foreach ($this->_structure['columns'] as $column => $info) {
            if (!property_exists($this, $column) || (!$allowHidden && ($info['hidden'] ?? false)) || ($allowHidden && !isset($this->{$column}))) {
                continue;
            }
            $value = $this->{$column} ?? null;
            if ($value instanceof \DateTime) {
                $data[$column] = $value->format("Y-m-d H:i:s");
            } else {
                $data[$column] = $value;
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