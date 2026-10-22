<?php

namespace MiniLaravel\Orm;

use PDO;
use ReflectionClass;
use RuntimeException;

class QueryBuilder
{
    private array $whereList = [];
    private array $parameters = [];
    private ?int $limit;
    private $orderList = [];

    public function __construct(
        private string $modelClass,
        private Database $database,
        private string $tableName
    ) {}

    //where age > :p1 and age < :p2 and name = :p3
    // p1 => 5, p2 => 10, p3 => 'bob'
    public function where(string $columnName, string $operator, mixed $value) : self {
        $paramName = $this->registerParameter($value);
        $this->whereList[] = "$columnName $operator $paramName";
        return $this;
    }

    public function orderBy(string $column, ?string $direction = "ASC") : QueryBuilder {
        $column = $this->escapeColumn($column);
        if ($direction != "ASC" and $direction != "DESC")
        {
            throw new RuntimeException("$direction is a wrong direction");    
        }
        $this->orderList[$column] = $direction;
        return $this;
    }

    public function limit(int $limit) : QueryBuilder {
        $this->limit = $limit;

        return $this;
    }

    public function execute() : array {  
        $sql = "SELECT * FROM $this->tableName ";
        
        $whereString = implode(" AND ", $this->whereList);
        $sql .= "WHERE $whereString ";

        if (count($this->orderList) > 0)
        {
            $sql .= "ORDER BY ";
            $strings = [];
            foreach ($this->orderList as $field => $direction) {
                $strings[] = "$field $direction";
            }
            $sql .= implode (", ", $strings) . " ";
        }

        if (isset($this->limit)) {
            $sql .= "LIMIT $this->limit ";
        }

        // echo "[sql] " . $sql . PHP_EOL;
        $statement = $this->database
            ->getPdo()
            ->prepare($sql);

        $statement->execute($this->parameters);

        $data = $statement->fetchAll(PDO::FETCH_ASSOC);

        $reflection = new ReflectionClass($this->modelClass);
        $models = [];
        foreach ($data as $line) {
            $model = $reflection->newInstance();
            foreach ($line as $key => $value) {
                $model->$key=$value;
            }
            $models[] = $model;
        }
        return $models;
    }

    public function first() : Model {
        return $this->limit(1)->execute()[0];
    }

    private function escapeColumn($column) : string {
        $column = preg_replace("/[^a-zA-Z0-9_]/", "", $column);

        if (empty($column)) {
            return '`id`';
        }

        return '`' .  str_replace('`' , '``', $column) . '`';
    }

    private function registerParameter($value) : string {
        $number = count($this->parameters) + 1;
        $paramName = ":p".$number;
        $this->parameters[$paramName] = $value;
        return $paramName;
    }
}
