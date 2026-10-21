<?php

namespace MiniLaravel\Orm;

use MiniLaravel\Di\Container;
use ReflectionClass;

use PDO;
use RuntimeException;

class Model{
    private static Database $database;
    private $fields = [];
    protected $fillable;

    public function __construct(?array $data = null)
    {
        if (!isset($this->fillable)) {
            $this->fields = $data;
            return;
        }
        
        foreach ($data as $key => $value) {
            if(!in_array($key, $this->fillable))
            {
                throw new RuntimeException("$key is not allow to a mass assignment");
            }
        }

        $this->fields = $data;
    }

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

    public function delete() : void {
        $tablename = static::getTableName();

        $sql = "DELETE FROM $tablename WHERE id=:id";

        $statement = self::$database
            ->getPdo()
            ->prepare($sql);

        $statement->execute([":id" => $this->fields["id"]]);
    }

    public function save() : void {
        if (isset($this->fields["id"]))
        {
            $this->update();
            return;
        }
        $this->insert();
    }
    
    private function insert() : void {
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

    private function update() : void {
        $tablename = static::getTableName();
        
        $valuesData = [];
        foreach ($this->fields as $key => $value) {
            $valuesData[":".$key] = $value;
        }

        $id = $this->fields["id"];
        unset($this->fields["id"]);

        $valuesTemplate = implode(", ", array_map(fn($key) => "$key=:$key", array_keys($this->fields)));

        $sql = "UPDATE $tablename SET $valuesTemplate WHERE id=:id";

        $statement = self::$database
            ->getPdo()
            ->prepare($sql);

        $statement->execute($valuesData);
        $this->fields['id'] = $id;
    }
}