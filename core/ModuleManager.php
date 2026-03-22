<?php
/**
 * core/ModuleManager.php
 * ---------------------------------------------------------------
 * Modul Manager — Kompatibel dengan PHP 7.4+
 * ---------------------------------------------------------------
 */

class ModuleManager
{
    /** @var ModuleManager|null */
    private static $instance = null;

    /** @var array */
    private $loadedModules = [];

    /** @var array */
    private $moduleStatus = [];

    /** @var array */
    private $hooks = [];

    /** @var Database */
    private $db;

    // ── Singleton ──────────────────────────────────────────────

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadModuleStatus();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}

    // ── Discovery ──────────────────────────────────────────────

    public function discoverModules(): array
    {
        $discovered = [];

        if (!is_dir(MODULE_PATH)) return $discovered;

        $allDirs = glob(MODULE_PATH . '/*');
        if (!$allDirs) return $discovered;
        $dirs = array_filter($allDirs, 'is_dir');

        foreach ($dirs as $dir) {
            $slug         = basename($dir);
            $manifestFile = $dir . '/module.json';

            if (!file_exists($manifestFile)) continue;

            $raw      = file_get_contents($manifestFile);
            $manifest = $raw ? json_decode($raw, true) : null;
            if (!$manifest || !is_array($manifest)) continue;

            $discovered[$slug] = $manifest;

            try {
                $existing = $this->db->fetchOne(
                    "SELECT id FROM modules WHERE slug = ?", [$slug]
                );

                if (!$existing) {
                    $this->db->insert('modules', [
                        'name'        => $manifest['name']        ?? $slug,
                        'slug'        => $slug,
                        'description' => $manifest['description'] ?? '',
                        'version'     => $manifest['version']     ?? '1.0.0',
                        'author'      => $manifest['author']      ?? '',
                        'is_enabled'  => $manifest['auto_enable'] ?? 0,
                        'is_core'     => $manifest['is_core']     ?? 0,
                        'config'      => json_encode($manifest['config'] ?? []),
                    ]);
                    $this->log("Registered: {$slug}");
                }
            } catch (Exception $e) {
                $this->log("Error registering {$slug}: " . $e->getMessage());
            }
        }

        $this->loadModuleStatus();
        return $discovered;
    }

    public function bootModules(): void
    {
        foreach ($this->moduleStatus as $slug => $module) {
            if (empty($module['is_enabled'])) continue;
            $this->loadModule($slug);
        }
    }

    public function loadModule(string $slug): bool
    {
        if (isset($this->loadedModules[$slug])) return true;

        $bootFile = MODULE_PATH . '/' . $slug . '/Module.php';

        if (!file_exists($bootFile)) {
            $this->log("Boot file not found: {$slug}");
            return false;
        }

        require_once $bootFile;

        // Konvensi: slug 'analytics' → class 'AnalyticsModule'
        $className = ucfirst($slug) . 'Module';

        if (!class_exists($className)) {
            $this->log("Class not found: {$className}");
            return false;
        }

        $instance = new $className();

        // Baca config dari database
        $row    = $this->db->fetchOne("SELECT config FROM modules WHERE slug = ?", [$slug]);
        $config = [];
        if (!empty($row['config'])) {
            $decoded = json_decode($row['config'], true);
            if (is_array($decoded)) $config = $decoded;
        }

        if (method_exists($instance, 'boot')) {
            $instance->boot($config);
        }

        $this->loadedModules[$slug] = $instance;
        return true;
    }

    // ── Status ─────────────────────────────────────────────────

    public function enable(string $slug): bool
    {
        $module = $this->db->fetchOne("SELECT * FROM modules WHERE slug = ?", [$slug]);
        if (!$module) return false;

        $this->db->update('modules', ['is_enabled' => 1], 'slug = ?', [$slug]);
        $this->loadModuleStatus();
        $this->loadModule($slug);
        return true;
    }

    public function disable(string $slug): bool
    {
        $module = $this->db->fetchOne("SELECT * FROM modules WHERE slug = ?", [$slug]);
        if (!$module || $module['is_core']) return false;

        $this->db->update('modules', ['is_enabled' => 0], 'slug = ?', [$slug]);
        $this->loadModuleStatus();
        unset($this->loadedModules[$slug]);
        return true;
    }

    public function isEnabled(string $slug): bool
    {
        return !empty($this->moduleStatus[$slug]['is_enabled']);
    }

    public function getConfig(string $slug): array
    {
        if (!isset($this->moduleStatus[$slug])) return [];
        $cfg = $this->moduleStatus[$slug]['config'] ?? null;
        if (is_string($cfg)) {
            $d = json_decode($cfg, true);
            return is_array($d) ? $d : [];
        }
        return is_array($cfg) ? $cfg : [];
    }

    public function updateConfig(string $slug, array $config): bool
    {
        return (bool) $this->db->update(
            'modules', ['config' => json_encode($config)], 'slug = ?', [$slug]
        );
    }

    public function getAllModules(): array
    {
        return $this->db->fetchAll("SELECT * FROM modules ORDER BY is_core DESC, name ASC");
    }

    public function getModule(string $slug)
    {
        return $this->loadedModules[$slug] ?? null;
    }

    // ── Hook System ────────────────────────────────────────────

    public function addHook(string $hookName, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hookName][$priority][] = $callback;
    }

    public function triggerHook(string $hookName, ...$args): void
    {
        if (empty($this->hooks[$hookName])) return;

        ksort($this->hooks[$hookName]);

        foreach ($this->hooks[$hookName] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $callback(...$args);
                } catch (Exception $e) {
                    $this->log("Hook error [{$hookName}]: " . $e->getMessage());
                }
            }
        }
    }

    // ── Internal ───────────────────────────────────────────────

    private function loadModuleStatus(): void
    {
        try {
            $modules            = $this->db->fetchAll("SELECT * FROM modules");
            $this->moduleStatus = [];
            foreach ($modules as $m) {
                $this->moduleStatus[$m['slug']] = $m;
            }
        } catch (Exception $e) {
            $this->moduleStatus = [];
        }
    }

    private function log(string $message): void
    {
        if (!defined('LOG_PATH')) return;
        @file_put_contents(
            LOG_PATH . '/modules.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
