<?php
/**
 * modules/csvimport/Module.php
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
     */
    public function init(): void
    {
        // Load services
        require_once $this->manifest['path'] . '/ImportService.php';
        $this->importService = new ImportService($this->config['config']);

        // Register hooks
        $hooks = HookManager::getInstance();
        $hooks->subscribe('admin.menu.build', [$this, 'onMenuBuild'], 10);
    }

    /**
     * Get admin menu items
     */
    public function getAdminMenu(): array
    {
        if (empty($this->manifest['admin_menu'])) {
            return [];
        }

        $menu = $this->manifest['admin_menu'];

        return [
            [
                'label' => $menu['title'] ?? 'CSV Import',
                'icon' => $menu['icon'] ?? 'fa-puzzle-piece',
                'url' => '/admin?plugin=' . $this->slug . '&page=import-csv',
                'permission' => ['superadmin', 'admin'],
                'position' => $menu['position'] ?? 50
            ]
        ];
    }

    /**
     * Hook callback for admin menu build
     */
    public function onMenuBuild(&$items): void
    {
        $menus = $this->getAdminMenu();
        foreach ($menus as $menu) {
            $items[] = $menu;
        }
    }

    /**
     * Get import service
     */
    public function getImportService(): ImportService
    {
        return $this->importService;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getConfig(): array
    {
        return $this->config['config'] ?? [];
    }
}