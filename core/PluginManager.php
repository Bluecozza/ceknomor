<?php
/**
 * ./core/PluginManager.php
 * True Plugin System - FIXED VERSION
 */

class PluginManager
{
    private static $instance = null;
    private $plugins = [];
    private $loadedPlugins = [];
    private $hooks;
    private $db;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->hooks = HookManager::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Discover plugins
     */
    public function discover(): array
    {
        $discovered = [];
        
        if (!is_dir(MODULE_PATH)) {
            return $discovered;
        }

        $dirs = array_filter(glob(MODULE_PATH . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $manifestFile = $dir . '/module.json';

            if (!file_exists($manifestFile)) {
                continue;
            }

            $manifest = json_decode(file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                error_log("Invalid manifest for plugin: {$slug}");
                continue;
            }

            $manifest['slug'] = $slug;
            $manifest['path'] = $dir;
            $manifest['url'] = BASE_URL . '/modules/' . $slug;

            error_log("PluginManager: Discovered plugin {$slug}");

            // Register di database
            $this->registerPlugin($slug, $manifest);

            $discovered[$slug] = $manifest;
        }

        return $discovered;
    }

    /**
     * Register plugin ke database
     */
    private function registerPlugin(string $slug, array $manifest): void
    {
        try {
            $existing = $this->db->fetchOne("SELECT id, is_active FROM plugins WHERE slug = ?", [$slug]);
            
            if (!$existing) {
                // Insert baru
                $this->db->insert('plugins', [
                    'name' => $manifest['name'] ?? $slug,
                    'slug' => $slug,
                    'description' => $manifest['description'] ?? '',
                    'version' => $manifest['version'] ?? '1.0.0',
                    'author' => $manifest['author'] ?? '',
                    'path' => $manifest['path'],
                    'is_active' => $manifest['auto_enable'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                // Update existing (untuk reflect perubahan di module.json)
                $updateData = [
                    'name' => $manifest['name'] ?? $slug,
                    'description' => $manifest['description'] ?? '',
                    'version' => $manifest['version'] ?? '1.0.0',
                    'author' => $manifest['author'] ?? '',
                    'path' => $manifest['path'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                // Force activate jika auto_enable true dan saat ini belum aktif
                if (!empty($manifest['auto_enable']) && !$existing['is_active']) {
                    $updateData['is_active'] = 1;
                }

                $this->db->update('plugins', $updateData, 'slug = ?', [$slug]);
            }
        } catch (Exception $e) {
            error_log("Plugin registration failed for {$slug}: " . $e->getMessage());
        }
    }

/**
 * Load all plugins
 */
public function loadAll(): void
{
    // Auto-migrate table if missing
    if (!$this->db->tableExists('plugins')) {
        $this->db->query("CREATE TABLE plugins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description LONGTEXT,
            version VARCHAR(50),
            author VARCHAR(255),
            path VARCHAR(255),
            config JSON,
            is_active TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(slug),
            INDEX(is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Migrate from old 'modules' table if exists
        if ($this->db->tableExists('modules')) {
            try {
                $oldModules = $this->db->fetchAll("SELECT * FROM modules");
                foreach ($oldModules as $old) {
                    $this->db->insert('plugins', [
                        'name' => $old['name'],
                        'slug' => $old['slug'],
                        'description' => $old['description'] ?? '',
                        'version' => $old['version'] ?? '1.0.0',
                        'author' => $old['author'] ?? '',
                        'path' => $old['path'] ?? (MODULE_PATH . '/' . $old['slug']),
                        'is_active' => $old['is_enabled'] ?? 1,
                    ]);
                }
            } catch (Exception $e) {
                error_log("Plugin migration from modules table failed: " . $e->getMessage());
            }
        }
    }

    // First, discover all plugins
    $discovered = $this->discover();
    
    // Merge dengan plugins dari database
    try {
        $dbPlugins = $this->db->fetchAll("SELECT * FROM plugins");
        
        foreach ($dbPlugins as $dbPlugin) {
            if (!isset($discovered[$dbPlugin['slug']])) {
                // Plugin di DB tapi tidak di folder
                $discovered[$dbPlugin['slug']] = [
                    'slug' => $dbPlugin['slug'],
                    'name' => $dbPlugin['name'],
                    'description' => $dbPlugin['description'],
                    'version' => $dbPlugin['version'],
                    'is_active' => $dbPlugin['is_active'],
                    'path' => $dbPlugin['path'],
                ];
            } else {
                // Update is_active dari DB
                $discovered[$dbPlugin['slug']]['is_active'] = $dbPlugin['is_active'];
            }
        }
    } catch (Exception $e) {
        error_log("Error loading plugins from DB: " . $e->getMessage());
    }

    // Set plugins
    $this->plugins = $discovered;

    // Load active plugins
    foreach ($this->plugins as $slug => $plugin) {
        if ($plugin['is_active'] ?? 0) {
            $this->loadPlugin($slug);
        }
    }
}

    /**
     * Load single plugin
     */
    public function loadPlugin(string $slug): bool
    {
        if (isset($this->loadedPlugins[$slug])) {
            return true;
        }

        $plugin = $this->plugins[$slug] ?? null;
        if (!$plugin) {
            return false;
        }

        if (!$plugin['is_active']) {
            return false;
        }

        try {
            // 1. Load main plugin class
            $mainFile = $plugin['path'] . '/Module.php';
            if (!file_exists($mainFile)) {
                throw new Exception("Plugin main file not found: {$mainFile}");
            }

            require_once $mainFile;

            $className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug))) . 'Plugin';
            if (!class_exists($className)) {
                throw new Exception("Plugin class not found: {$className}");
            }

            $pluginInstance = new $className($slug, $plugin);

            // 2. Call plugin init
            if (method_exists($pluginInstance, 'init')) {
                $pluginInstance->init();
            }

            // 3. Load migrations
            $this->runMigrations($slug, $plugin);

            $this->loadedPlugins[$slug] = $pluginInstance;

            error_log("PluginManager: Successfully loaded plugin {$slug}");

            // Trigger hook
            $this->hooks->trigger('plugin.loaded', $slug, $pluginInstance);

            return true;

        } catch (Throwable $e) {
            error_log("Plugin load failed for [{$slug}]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return false;
        }
    }

    /**
     * Run migrations
     */
    private function runMigrations(string $slug, array $plugin): void
    {
        $migrationsDir = $plugin['path'] . '/migrations';
        
        if (!is_dir($migrationsDir)) {
            return;
        }

        $migrations = glob($migrationsDir . '/*.sql');
        if (!$migrations) return;
        
        sort($migrations);

        foreach ($migrations as $migrationFile) {
            $migrationName = basename($migrationFile, '.sql');
            
            // Check if already run
            try {
                $exists = $this->db->fetchOne(
                    "SELECT id FROM plugin_migrations WHERE plugin = ? AND migration = ?",
                    [$slug, $migrationName]
                );

                if ($exists) {
                    continue;
                }

                $sql = file_get_contents($migrationFile);
                
                // Execute SQL
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => !empty($s)
                );

                foreach ($statements as $statement) {
                    $this->db->query($statement);
                }

                // Record migration
                $this->db->insert('plugin_migrations', [
                    'plugin' => $slug,
                    'migration' => $migrationName,
                    'executed_at' => date('Y-m-d H:i:s')
                ]);

            } catch (Exception $e) {
                error_log("Migration failed for {$slug}/{$migrationName}: " . $e->getMessage());
            }
        }
    }

    /**
     * Activate plugin
     */
    public function activate(string $slug): bool
    {
        try {
            $this->db->update('plugins', ['is_active' => 1], 'slug = ?', [$slug]);
            $this->plugins[$slug]['is_active'] = 1;
            $this->loadPlugin($slug);
            
            $this->hooks->trigger('plugin.activated', $slug);
            
            return true;
        } catch (Exception $e) {
            error_log("Plugin activation failed for {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deactivate plugin
     */
    public function deactivate(string $slug): bool
    {
        try {
            $this->db->update('plugins', ['is_active' => 0], 'slug = ?', [$slug]);
            $this->plugins[$slug]['is_active'] = 0;
            unset($this->loadedPlugins[$slug]);
            
            $this->hooks->trigger('plugin.deactivated', $slug);
            
            return true;
        } catch (Exception $e) {
            error_log("Plugin deactivation failed for {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update plugin config
     */
    public function updateConfig(string $slug, array $config): bool
    {
        try {
            $this->db->update('plugins', [
                'config' => json_encode($config),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'slug = ?', [$slug]);
            return true;
        } catch (Exception $e) {
            error_log("Config update failed for {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get plugin config
     */
    public function getConfig(string $slug): array
    {
        try {
            $plugin = $this->db->fetchOne("SELECT config FROM plugins WHERE slug = ?", [$slug]);
            if (!$plugin || empty($plugin['config'])) {
                return [];
            }
            $config = json_decode($plugin['config'], true);
            return is_array($config) ? $config : [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get plugin
     */
    public function getPlugin(string $slug): ?object
    {
        return $this->loadedPlugins[$slug] ?? null;
    }

    /**
     * Get all plugins
     */
    public function getAllPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Get loaded plugins
     */
    public function getLoadedPlugins(): array
    {
        return $this->loadedPlugins;
    }
}