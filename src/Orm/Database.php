<?php

namespace MiniLaravel\Orm;

use PDO;

class Database {
    private PDO $pdo;

    public function __construct(string $dsn, ?string $user = null, ?string $password = null, ?array $options = null)
    {
        try {
            $this->pdo = new PDO($dsn, $user, $password, $options);
        } catch (\PDOException $e) {
            die("Ошибка подключение к бд:". $e->getMessage());
        }
    }

    public function getPdo() : PDO {
        return $this->pdo;
    }
    
}