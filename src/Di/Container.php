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
        if ($this->hasBinding($id)) {
            $id = $this->getBinding($id);
        }
        if (in_array($id, $alreadyResolvedIds)) {
            throw new RuntimeException("Cycle dependency was detect");
        }
        $alreadyResolvedIds[] = $id;

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $reflection = new ReflectionClass($id);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Need to bind the abstract {$reflection->getName()} to a concrete class");
        }

        $constructor = $reflection->getConstructor();

        if (!isset($constructor)) {
            return $this->createInstance($reflection);
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

       return $this->createInstance($reflection, $dependencies);
    }

    private function createInstance(ReflectionClass $reflection, ?array $dependencies = null)
    {
        $instance = null;
        if (isset($dependencies))
        {
            $instance = $reflection->newInstanceArgs($dependencies);
        }
        $instance = $reflection->newInstance();
        $name = $reflection->getName();
        if (in_array($name, $this->singletonIds))
        {
            $this->instances[$reflection->getName()] = $instance;   
        }
        return $instance;
    }

    private function hasBinding(string $abstractId) : bool {
        return isset($this->bindings[$abstractId]);
    }

    private function getBinding(string $abstractId) : string {
        return $this->bindings[$abstractId];
    }
}
