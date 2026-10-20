<?php

namespace MiniLaravel\Di;

use ReflectionClass;
use RuntimeException;

class Container {

    private $bindings = [];
    private $singletonIds = [];
    private $instances = [];


    public function get(string $id) : object {
        $alreadyResolvedIds = [];
        return $this->getInternal($id, $alreadyResolvedIds);
    }

    public function bind(string $abstractId, string $concreteId) : void
    {
        $this->bindings[$abstractId] = $concreteId;
    }

    public function singleton(string $abstractId, string $concreteId, ?object $instance = null) : void {
        if ($abstractId != $concreteId) {
            $this->bind($abstractId, $concreteId);
        }

        $this->singletonIds[] = $concreteId;
        if (isset($instance)) {
            $this->instances[$concreteId] = $instance;
        }
    }

    private function getInternal(string $id, array &$alreadyResolvedIds) : object
    {
        if ($this->isAbstract($id)) {
            $id = $this->getConcrete($id);
        }

        $this->validateAlreadyResolved($id, $alreadyResolvedIds);

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $reflection = new ReflectionClass($id);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Need to bind the abstract {$reflection->getName()} to a concrete class");
        }

        return $this->createInstance($reflection, $alreadyResolvedIds);
    }

    private function validateAlreadyResolved(string $id, array &$alreadyResolvedIds) : void {
        if (in_array($id, $alreadyResolvedIds)) {
            throw new RuntimeException("Cycle dependency was detect");
        }
        $alreadyResolvedIds[] = $id;
    }

    private function createInstance(ReflectionClass $reflection, array $alreadyResolvedIds)
    {
        $constructor = $reflection->getConstructor();
        if (!isset($constructor)) {
            return $this->newInstance($reflection);
        }

        $dependencies = [];
        foreach($constructor->getParameters() as $parameter)
        {
            $type = $parameter->getType();

            if (!isset($type)) {
                throw new RuntimeException("Cannot resolve parameter {parameter->getName()}");
            }

            $dependencies[] = $this->getInternal($type->getName(), $alreadyResolvedIds);
        }

        return $this->newInstance($reflection, $dependencies);
    }

    private function newInstance(ReflectionClass $reflection, ?array $dependencies = null)
    {
        $instance = null;
        if (isset($dependencies))
        {
            $instance = $reflection->newInstanceArgs($dependencies);
        }
        else
        {
            $instance = $reflection->newInstance();
        }
        if ($this->isSingleton($reflection->getName()))
        {
            $this->instances[$reflection->getName()] = $instance;
        }
        return $instance;
    }

    private function isSingleton(string $class) : bool {
        return in_array($class, $this->singletonIds);
    }

    private function isAbstract(string $abstractId) : bool {
        return isset($this->bindings[$abstractId]);
    }

    private function getConcrete(string $abstractId) : string {
        return $this->bindings[$abstractId];
    }
}
