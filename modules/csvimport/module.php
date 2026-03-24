<?php
/**
 * modules/csvimport/Module.php
 * CSV Import Plugin - Main class
 * Auto-loads everything: routes, admin pages, hooks, assets
 */

class CsvimportPlugin
{
    private $slug;
    private $manifest;
    private $config;
    private $importService;

    public function __construct(string $slug, array $manifest)
    {
        $this->slug = $slug;
        $this->manifest = $manifest;
        $this->config = $manifest;
    }

    /**
     * Plugin initialization
     * Called automatically by PluginManager
     */
    public function init(): void
    {
        // Load services
        require_once $this->manifest['path'] . '/ImportService.php';
        $this->importService = new ImportService($this->config['config']);

        // Register hooks untuk admin navigation
        $hooks = HookManager::getInstance();
        $hooks->subscribe('admin.navigation.build', [$this, 'addAdminMenu'], 10);
        $hooks->subscribe('admin.assets.enqueue', [$this, 'enqueueAssets'], 10);
    }

    /**
     * Add menu item to admin navigation
     */
    public function addAdminMenu($navItems): array
    {
        if (empty($this->manifest['admin_menu'])) {
            return $navItems;
        }

        $menu = $this->manifest['admin_menu'];
        
        $navItems[] = [
            'title' => $menu['title'] ?? 'CSV Import',
            'icon' => $menu['icon'] ?? 'fa-puzzle-piece',
            'url' => '/admin?plugin=csvimport&page=import-csv',
            'plugin' => $this->slug,
            'permission' => ['superadmin', 'admin']
        ];

        return $navItems;
    }

    /**
     * Enqueue CSS/JS assets
     */
    public function enqueueAssets(): void
    {
        // Register CSS
        wp_enqueue_style(
            'csvimport-main',
            $this->manifest['url'] . '/assets/css/import.css',
            [],
            $this->manifest['version']
        );

        // Register JS
        wp_enqueue_script(
            'csvimport-main',
            $this->manifest['url'] . '/assets/js/import.js',
            ['jquery'],
            $this->manifest['version'],
            true
        );
    }

    /**
     * Get import service instance
     */
    public function getImportService(): ImportService
    {
        return $this->importService;
    }

    /**
     * Get plugin slug
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get plugin config
     */
    public function getConfig(): array
    {
        return $this->config['config'] ?? [];
    }
}