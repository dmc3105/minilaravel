<?php

namespace MiniLaravel\Di;

use ReflectionClass;
use RuntimeException;

class Container {

    private $bindings = [];

    public function get(string $id) : object {
        $alreadyResolvedId = [];
        return $this->getInternal($id, $alreadyResolvedId);
    }

    public function bind(string $abstractId, string $concreteId) : void
    {
        $this->bindings[$abstractId] = $concreteId;
    }

    private function getInternal(string $id, array &$alreadyResolvedId) : object
    {
        if ($this->hasBinding($id)) {
            $id = $this->getBinding($id);
        }
        if (in_array($id, $alreadyResolvedId)) {
            throw new RuntimeException("Cycle dependency was detect");
        }
        $alreadyResolvedId[] = $id;

        $reflection = new ReflectionClass($id);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Need to bind the abstract {$reflection->getName()} to a concrete class");
        }

        $constructor = $reflection->getConstructor();

        if (!isset($constructor)) {
            return $reflection->newInstance();
        }

        $dependencies = [];
        foreach($constructor->getParameters() as $parameter)
        {
            $type = $parameter->getType();

            if (!isset($type)) {
                throw new RuntimeException("Cannot resolve parameter {parameter->getName()}");
            }

            $dependencies[] = $this->getInternal($type->getName(), $alreadyResolvedId);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    private function hasBinding(string $abstractId) : bool {
        return isset($this->bindings[$abstractId]);
    }

    private function getBinding(string $abstractId) : string {
        return $this->bindings[$abstractId];
    }
}
