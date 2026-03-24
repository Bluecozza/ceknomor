<?php

class ModuleRegistry {
    protected $modules = [];

    public function registerModule($name, $metadata) {
        $this->modules[$name] = $metadata;
    }

    public function getModule($name) {
        return isset($this->modules[$name]) ? $this->modules[$name] : null;
    }

    public function getAllModules() {
        return $this->modules;
    }
}