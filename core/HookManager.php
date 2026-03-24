<?php
/**
 * ./core/HookManager.php
 * Centralized Hook System - Global singleton
 */

class HookManager
{
    private static $instance = null;
    private $hooks = [];
    private $filters = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Subscribe to hook
     */
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

    /**
     * Trigger hook
     */
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

    /**
     * Apply filter - return modified value
     */
    public function filter(string $filterName, $value, ...$args)
    {
        if (empty($this->hooks[$filterName])) {
            return $value;
        }

        ksort($this->hooks[$filterName]);

        foreach ($this->hooks[$filterName] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $value = $callback($value, ...$args);
                } catch (Exception $e) {
                    error_log("Filter error [{$filterName}]: " . $e->getMessage());
                }
            }
        }

        return $value;
    }

    /**
     * Get hook count
     */
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