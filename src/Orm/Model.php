<?php

namespace MiniLaravel\Orm;

use MiniLaravel\Di\Container;
use ReflectionClass;

use PDO;

class Model{
    private static Database $database;
    private $fields = [];
    
    public static function setDatabase(Database $database)
    {
        self::$database = $database;
    }

    public static function getTableName() : string
    {
        $reflection = new ReflectionClass(static::class);
        return $reflection->getShortName() . "s";
    }

    public static function find(mixed $id) : ?static {
        $object = new static();

        $tablename = static::getTableName();
        $sql = "SELECT * FROM $tablename WHERE id = :id";
        
        $statement = self::$database
            ->getPdo()
            ->prepare($sql);

        $statement->execute([
            'id' => $id
        ]);

        $data = $statement->fetch(PDO::FETCH_ASSOC);

        if ($data == null)
        {
            return null;
        }

        $object->fill($data);

        return $object;
    }

    public function __get(string $name) : mixed
    {
        return $this->fields[$name];
    }

    public function __set(string $name, mixed $value) : void
    {
        $this->fields[$name] = $value;
    }

    public function __toString()
    {
        return json_encode($this->fields);
    }

    private function fill(array $data) : void {
        $this->fields = $data;
    }

    public function insert() : void {
        $tablename = static::getTableName();
        
        $columns = array_keys($this->fields);
        $columns = implode(", ", $columns);

        $valuesData = [];
        foreach ($this->fields as $key => $value) {
            $valuesData[":".$key] = $value;
        }

        $valuesTemplate = implode(", ", array_keys($valuesData));

        $sql = "INSERT INTO $tablename ($columns) VALUES ($valuesTemplate)";

        $statement = self::$database
            ->getPdo()
            ->prepare($sql);

        $statement->execute($valuesData);
        $this->fields['id'] = self::$database->getPdo()->lastInsertId();
    }

    //insert into Users (id, name, age) values (:id, :name, :age);
    //[:id => 1, :name => bob, :age => age]
}