<?php

namespace MiniLaravel\Di;

use ReflectionClass;
use RuntimeException;

class Container {
    public function get(string $id) : object {
        $reflection = new ReflectionClass($id);
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

            $dependencies[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
