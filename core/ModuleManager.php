<?php
/**
 * core/ModuleManager.php
 * ---------------------------------------------------------------
 * Modul Manager
 * Otomatis mendeteksi, mendaftarkan, dan memuat modul dari folder
 * /modules. Admin dapat mengaktifkan/menonaktifkan via database.
 * ---------------------------------------------------------------
 */

class ModuleManager
{
    /** @var ModuleManager|null Singleton instance */
    private static ?ModuleManager $instance = null;

    /** @var array Modul yang sudah dimuat */
    private array $loadedModules = [];

    /** @var array Status modul dari database */
    private array $moduleStatus = [];

    /** @var Database */
    private Database $db;

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

    // ── Module Discovery ───────────────────────────────────────

    /**
     * Scan folder /modules dan daftarkan modul baru ke database
     * Dipanggil saat admin membuka halaman modules
     */
    public function discoverModules(): array
    {
        $discovered = [];

        if (!is_dir(MODULE_PATH)) return $discovered;

        $dirs = array_filter(glob(MODULE_PATH . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $slug        = basename($dir);
            $manifestFile = $dir . '/module.json';

            if (!file_exists($manifestFile)) continue;

            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!$manifest) continue;

            $discovered[$slug] = $manifest;

            // Daftarkan ke database jika belum ada
            $existing = $this->db->fetchOne(
                "SELECT id FROM modules WHERE slug = ?", [$slug]
            );

            if (!$existing) {
                $this->db->insert('modules', [
                    'name'        => $manifest['name'] ?? $slug,
                    'slug'        => $slug,
                    'description' => $manifest['description'] ?? '',
                    'version'     => $manifest['version'] ?? '1.0.0',
                    'author'      => $manifest['author'] ?? '',
                    'is_enabled'  => $manifest['auto_enable'] ?? 0,
                    'is_core'     => $manifest['is_core'] ?? 0,
                    'config'      => json_encode($manifest['default_config'] ?? []),
                ]);

                $this->log("Module discovered and registered: {$slug}");
            }
        }

        $this->loadModuleStatus();
        return $discovered;
    }

    /**
     * Muat semua modul yang aktif
     * Dipanggil di bootstrap (index.php)
     */
    public function bootModules(): void
    {
        foreach ($this->moduleStatus as $slug => $module) {
            if (!$module['is_enabled']) continue;

            $this->loadModule($slug);
        }
    }

    /**
     * Muat satu modul berdasarkan slug
     */
    public function loadModule(string $slug): bool
    {
        if (isset($this->loadedModules[$slug])) return true;

        $bootFile = MODULE_PATH . '/' . $slug . '/Module.php';

        if (!file_exists($bootFile)) {
            $this->log("Module boot file not found: {$slug}");
            return false;
        }

        require_once $bootFile;

        // Instansiasi class modul (konvensi: NamaModuleModule)
        $className = ucfirst($slug) . 'Module';

        if (class_exists($className)) {
            $instance = new $className();

            if (method_exists($instance, 'boot')) {
                $instance->boot();
            }

            $this->loadedModules[$slug] = $instance;
        }

        return true;
    }

    // ── Module Status ──────────────────────────────────────────

    /**
     * Aktifkan modul
     */
    public function enable(string $slug): bool
    {
        $module = $this->db->fetchOne("SELECT * FROM modules WHERE slug = ?", [$slug]);

        if (!$module) return false;

        $this->db->update('modules', ['is_enabled' => 1], 'slug = ?', [$slug]);
        $this->loadModuleStatus();
        $this->loadModule($slug);

        return true;
    }

    /**
     * Nonaktifkan modul (kecuali modul inti)
     */
    public function disable(string $slug): bool
    {
        $module = $this->db->fetchOne("SELECT * FROM modules WHERE slug = ?", [$slug]);

        if (!$module || $module['is_core']) return false;

        $this->db->update('modules', ['is_enabled' => 0], 'slug = ?', [$slug]);
        $this->loadModuleStatus();

        unset($this->loadedModules[$slug]);
        return true;
    }

    /**
     * Cek apakah modul aktif
     */
    public function isEnabled(string $slug): bool
    {
        return isset($this->moduleStatus[$slug]) && $this->moduleStatus[$slug]['is_enabled'];
    }

    /**
     * Dapatkan konfigurasi modul
     */
    public function getConfig(string $slug): array
    {
        if (!isset($this->moduleStatus[$slug])) return [];

        $config = $this->moduleStatus[$slug]['config'];
        return is_string($config) ? (json_decode($config, true) ?? []) : ($config ?? []);
    }

    /**
     * Update konfigurasi modul
     */
    public function updateConfig(string $slug, array $config): bool
    {
        return (bool) $this->db->update(
            'modules',
            ['config' => json_encode($config)],
            'slug = ?',
            [$slug]
        );
    }

    /**
     * Dapatkan daftar semua modul
     */
    public function getAllModules(): array
    {
        return $this->db->fetchAll("SELECT * FROM modules ORDER BY is_core DESC, name ASC");
    }

    /**
     * Dapatkan instance modul yang sudah dimuat
     */
    public function getModule(string $slug): ?object
    {
        return $this->loadedModules[$slug] ?? null;
    }

    // ── Hook System ────────────────────────────────────────────

    /** @var array Daftar hook yang terdaftar */
    private array $hooks = [];

    /**
     * Daftarkan hook (digunakan oleh modul)
     *
     * @param string   $hookName Nama hook (e.g., 'report.created')
     * @param callable $callback Fungsi callback
     * @param int      $priority Priority (lebih kecil = lebih dulu)
     */
    public function addHook(string $hookName, callable $callback, int $priority = 10): void
    {
        $this->hooks[$hookName][$priority][] = $callback;
    }

    /**
     * Trigger hook dan jalankan semua callback yang terdaftar
     *
     * @param string $hookName Nama hook
     * @param mixed  ...$args  Argumen yang diteruskan ke callback
     */
    public function triggerHook(string $hookName, mixed ...$args): void
    {
        if (empty($this->hooks[$hookName])) return;

        ksort($this->hooks[$hookName]); // Sort by priority

        foreach ($this->hooks[$hookName] as $callbacks) {
            foreach ($callbacks as $callback) {
                $callback(...$args);
            }
        }
    }

    // ── Internal ───────────────────────────────────────────────

    /**
     * Muat status modul dari database
     */
    private function loadModuleStatus(): void
    {
        $modules = $this->db->fetchAll("SELECT * FROM modules");
        $this->moduleStatus = [];
        foreach ($modules as $module) {
            $this->moduleStatus[$module['slug']] = $module;
        }
    }

    /**
     * Log pesan modul manager
     */
    private function log(string $message): void
    {
        $logFile = LOG_PATH . '/modules.log';
        $line    = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function __clone() {}
}
