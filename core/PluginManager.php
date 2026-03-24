<?php
/**
 * ./core/PluginManager.php
 * True Plugin System seperti WordPress
 * Auto-load routes, admin pages, hooks, migrations, assets
 */

class PluginManager
{
    private static $instance = null;
    private $plugins = [];
    private $loadedPlugins = [];
    private $hooks;
    private $db;
    private $router;

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
     * Discover plugins dari directory /modules
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
            $existing = $this->db->fetchOne("SELECT id FROM plugins WHERE slug = ?", [$slug]);
            
            if (!$existing) {
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
            }
        } catch (Exception $e) {
            error_log("Plugin registration failed for {$slug}: " . $e->getMessage());
        }
    }

    /**
     * Load plugin dan setup semuanya
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

            $className = ucfirst($slug) . 'Plugin';
            if (!class_exists($className)) {
                throw new Exception("Plugin class not found: {$className}");
            }

            $pluginInstance = new $className($slug, $plugin);

            // 2. Call plugin init/boot
            if (method_exists($pluginInstance, 'init')) {
                $pluginInstance->init();
            }

            // 3. Load routes
            $this->loadPluginRoutes($slug, $plugin);

            // 4. Load admin pages
            $this->registerAdminPages($slug, $plugin);

            // 5. Load hooks
            $this->registerPluginHooks($slug, $plugin);

            // 6. Load migrations
            $this->runMigrations($slug, $plugin);

            // 7. Enqueue assets
            $this->enqueueAssets($slug, $plugin);

            $this->loadedPlugins[$slug] = $pluginInstance;

            // Trigger plugin_loaded hook
            $this->hooks->trigger('plugin.loaded', $slug, $pluginInstance);

            return true;

        } catch (Exception $e) {
            error_log("Plugin load failed for {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load all active plugins
     */
    public function loadAll(): void
    {
        // First, discover all plugins
        $discovered = $this->discover();
        $this->plugins = $discovered;

        // Get active plugins from database
        try {
            $activePlugins = $this->db->fetchAll(
                "SELECT * FROM plugins WHERE is_active = 1"
            );
            
            foreach ($activePlugins as $plugin) {
                if (isset($this->plugins[$plugin['slug']])) {
                    $this->plugins[$plugin['slug']]['is_active'] = true;
                    $this->loadPlugin($plugin['slug']);
                }
            }
        } catch (Exception $e) {
            error_log("Error loading plugins: " . $e->getMessage());
        }
    }

    /**
     * Load plugin API routes
     */
    private function loadPluginRoutes(string $slug, array $plugin): void
    {
        $routesFile = $plugin['path'] . '/routes/api.php';
        
        if (!file_exists($routesFile)) {
            return;
        }

        $routes = include $routesFile;
        if (!is_array($routes)) {
            return;
        }

        // Register routes dengan prefix /api/v1/plugins/{slug}
        foreach ($routes as $route) {
            $route['path'] = '/plugins/' . $slug . $route['path'];
            $route['plugin'] = $slug;
            
            $this->hooks->trigger('route.register', $route);
        }
    }

    /**
     * Register admin pages untuk plugin
     */
    private function registerAdminPages(string $slug, array $plugin): void
    {
        $pagesDir = $plugin['path'] . '/admin';
        
        if (!is_dir($pagesDir)) {
            return;
        }

        $pages = glob($pagesDir . '/*.php');
        
        foreach ($pages as $pageFile) {
            $pageName = basename($pageFile, '.php');
            
            $this->hooks->trigger('admin.page.register', [
                'slug' => $slug,
                'page' => $pageName,
                'file' => $pageFile,
                'title' => $this->getTitleFromFile($pageFile),
                'icon' => 'fa-puzzle-piece' // Default icon
            ]);
        }
    }

    /**
     * Register plugin hooks
     */
    private function registerPluginHooks(string $slug, array $plugin): void
    {
        $hooksFile = $plugin['path'] . '/hooks/navigation.php';
        
        if (!file_exists($hooksFile)) {
            return;
        }

        // Load hooks - file ini akan call $hooks->subscribe()
        include $hooksFile;
    }

    /**
     * Run plugin migrations
     */
    private function runMigrations(string $slug, array $plugin): void
    {
        $migrationsDir = $plugin['path'] . '/migrations';
        
        if (!is_dir($migrationsDir)) {
            return;
        }

        $migrations = glob($migrationsDir . '/*.sql');
        sort($migrations);

        foreach ($migrations as $migrationFile) {
            $migrationName = basename($migrationFile, '.sql');
            
            // Check if already run
            $exists = $this->db->fetchOne(
                "SELECT id FROM plugin_migrations WHERE plugin = ? AND migration = ?",
                [$slug, $migrationName]
            );

            if ($exists) {
                continue;
            }

            try {
                $sql = file_get_contents($migrationFile);
                
                // Execute SQL (split by ;)
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
     * Enqueue plugin assets (CSS/JS)
     */
    private function enqueueAssets(string $slug, array $plugin): void
    {
        $assetsPath = $plugin['path'] . '/assets';
        
        if (!is_dir($assetsPath)) {
            return;
        }

        // Register CSS files
        $cssDir = $assetsPath . '/css';
        if (is_dir($cssDir)) {
            $cssFiles = glob($cssDir . '/*.css');
            foreach ($cssFiles as $cssFile) {
                $this->hooks->trigger('style.enqueue', [
                    'handle' => $slug . '-' . basename($cssFile, '.css'),
                    'src' => $plugin['url'] . '/assets/css/' . basename($cssFile),
                    'plugin' => $slug
                ]);
            }
        }

        // Register JS files
        $jsDir = $assetsPath . '/js';
        if (is_dir($jsDir)) {
            $jsFiles = glob($jsDir . '/*.js');
            foreach ($jsFiles as $jsFile) {
                $this->hooks->trigger('script.enqueue', [
                    'handle' => $slug . '-' . basename($jsFile, '.js'),
                    'src' => $plugin['url'] . '/assets/js/' . basename($jsFile),
                    'plugin' => $slug
                ]);
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
            unset($this->loadedPlugins[$slug]);
            
            $this->hooks->trigger('plugin.deactivated', $slug);
            
            return true;
        } catch (Exception $e) {
            error_log("Plugin deactivation failed for {$slug}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get loaded plugin instance
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

    /**
     * Helper: Extract title from PHP file
     */
    private function getTitleFromFile(string $file): string
    {
        $content = file_get_contents($file);
        if (preg_match('/page_title\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }
        return ucfirst(basename($file, '.php'));
    }
}