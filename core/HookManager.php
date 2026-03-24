<?php
/**
 * ./core/HookManager.php
 * Centralized Hook System untuk integrasi modul
 */

class HookManager
{
    private $hooks = [];

    public function subscribe(string $hookName, callable $callback, int $priority = 10): void
    {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }
        if (!isset($this->hooks[$hookName][$priority])) {
            $this->hooks[$hookName][$priority] = [];
        }
        $this->hooks[$hookName][$priority][] = $callback;
    }

    public function trigger(string $hookName, ...$args): void
    {
        if (empty($this->hooks[$hookName])) {
            return;
        }
        ksort($this->hooks[$hookName]);
        foreach ($this->hooks[$hookName] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $callback(...$args);
                } catch (Exception $e) {
                    error_log("Hook error [{$hookName}]: " . $e->getMessage());
                }
            }
        }
    }

    public function filter(string $hookName, $value, ...$args)
    {
        if (empty($this->hooks[$hookName])) {
            return $value;
        }
        ksort($this->hooks[$hookName]);
        foreach ($this->hooks[$hookName] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $value = $callback($value, ...$args);
                } catch (Exception $e) {
                    error_log("Hook error [{$hookName}]: " . $e->getMessage());
                }
            }
        }
        return $value;
    }

    public function count(string $hookName): int
    {
        if (empty($this->hooks[$hookName])) {
            return 0;
        }
        $count = 0;
        foreach ($this->hooks[$hookName] as $callbacks) {
            $count += count($callbacks);
        }
        return $count;
    }
}