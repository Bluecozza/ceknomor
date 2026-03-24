<?php
/**
 * ./core/ModuleManager.php
 * Module manager — load, enable, disable, hook system
 * UPDATED: Pass HookManager ke boot method
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

    /** @var HookManager */
    private $hookManager = null;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->loadModuleStatus();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __clone() {}

    /**
     * Set HookManager instance
     */
    public function setHookManager($hookManager): void
    {
        $this->hookManager = $hookManager;
    }

    // ── Discovery ─────────────────────────────────────────────
    public function discoverModules(): array
    {
        $discovered = [];
        if (!is_dir(MODULE_PATH)) return $discovered;

        $allDirs = glob(MODULE_PATH . '/*');
        if (!$allDirs) return $discovered;

        foreach (array_filter($allDirs, 'is_dir') as $dir) {
            $slug = basename($dir);
            $mf   = $dir . '/module.json';
            if (!file_exists($mf)) continue;

            $raw = file_get_contents($mf);
            $m   = $raw ? json_decode($raw, true) : null;
            if (!is_array($m)) continue;

            $discovered[$slug] = $m;

            try {
                $existing = $this->db->fetchOne("SELECT id FROM modules WHERE slug = ?", [$slug]);
                if (!$existing) {
                    $this->db->insert('modules', [
                        'name'        => $m['name']        ?? $slug,
                        'slug'        => $slug,
                        'description' => $m['description'] ?? '',
                        'version'     => $m['version']     ?? '1.0.0',
                        'author'      => $m['author']      ?? '',
                        'is_enabled'  => $m['auto_enable'] ?? 0,
                        'is_core'     => $m['is_core']     ?? 0,
                        'config'      => json_encode($m['config'] ?? []),
                    ]);
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
        foreach ($this->moduleStatus as $slug => $mod) {
            if (empty($mod['is_enabled'])) continue;
            try {
                $this->loadModule($slug);
            } catch (Throwable $e) {
                $this->log("Boot failed for {$slug}: " . $e->getMessage());
                // Continue with other modules
            }
        }
    }

    public function loadModule(string $slug): bool
    {
        if (isset($this->loadedModules[$slug])) return true;

        $file = MODULE_PATH . '/' . $slug . '/Module.php';
        if (!file_exists($file)) return false;

        require_once $file;

        $cls = ucfirst($slug) . 'Module';
        if (!class_exists($cls)) return false;

        $instance = new $cls();

        $row    = $this->db->fetchOne("SELECT config FROM modules WHERE slug = ?", [$slug]);
        $config = [];
        if (!empty($row['config'])) {
            $d = json_decode($row['config'], true);
            if (is_array($d)) $config = $d;
        }

        // FIXED: Pass HookManager to boot method
        if (method_exists($instance, 'boot')) {
            $instance->boot($config, $this->hookManager);
        }

        $this->loadedModules[$slug] = $instance;
        return true;
    }

    // ── Status ────────────────────────────────────────────────
    public function enable(string $slug): bool
    {
        $mod = $this->db->fetchOne("SELECT id FROM modules WHERE slug = ?", [$slug]);
        if (!$mod) return false;
        $this->db->update('modules', ['is_enabled' => 1], 'slug = ?', [$slug]);
        $this->loadModuleStatus();
        $this->loadModule($slug);
        return true;
    }

    public function disable(string $slug): bool
    {
        $mod = $this->db->fetchOne("SELECT id, is_core FROM modules WHERE slug = ?", [$slug]);
        if (!$mod || $mod['is_core']) return false;
        $this->db->update('modules', ['is_enabled' => 0], 'slug = ?', [$slug]);
        $this->loadModuleStatus();
        unset($this->loadedModules[$slug]);
        return true;
    }

    public function getAllModules(): array
    {
        return $this->db->fetchAll("SELECT * FROM modules ORDER BY is_core DESC, name ASC");
    }

    public function getModule(string $slug): ?array
    {
        return $this->db->fetchOne("SELECT * FROM modules WHERE slug = ?", [$slug]);
    }

    public function getConfig(string $slug): array
    {
        if (!isset($this->moduleStatus[$slug])) return [];
        $cfg = $this->moduleStatus[$slug]['config'] ?? null;
        if (is_string($cfg)) { $d = json_decode($cfg, true); return is_array($d) ? $d : []; }
        return is_array($cfg) ? $cfg : [];
    }

    public function updateConfig(string $slug, array $cfg): bool
    {
        return (bool)$this->db->update('modules', ['config' => json_encode($cfg)], 'slug = ?', [$slug]);
    }

    /**
     * Get module instance yang sudah loaded
     */
    public function getLoadedInstance(string $slug)
    {
        return $this->loadedModules[$slug] ?? null;
    }

    // ── Hooks ─────────────────────────────────────────────────
    public function addHook(string $name, callable $cb, int $priority = 10): void
    {
        $this->hooks[$name][$priority][] = $cb;
    }

    public function triggerHook(string $name, ...$args): void
    {
        if (empty($this->hooks[$name])) return;
        ksort($this->hooks[$name]);
        foreach ($this->hooks[$name] as $cbs) {
            foreach ($cbs as $cb) {
                try { $cb(...$args); } catch (Exception $e) { $this->log("Hook error [{$name}]: " . $e->getMessage()); }
            }
        }
    }

    // ── Internal ──────────────────────────────────────────────
    private function loadModuleStatus(): void
    {
        try {
            $mods = $this->db->fetchAll("SELECT * FROM modules");
            $this->moduleStatus = [];
            foreach ($mods as $m) $this->moduleStatus[$m['slug']] = $m;
        } catch (Exception $e) {
            $this->moduleStatus = [];
        }
    }

    private function log(string $msg): void
    {
        if (!defined('LOG_PATH')) return;
        @file_put_contents(LOG_PATH . '/modules.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
    }
}