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
                'icon' => $menu['icon'] ?? 'fa-file-csv',
                'url' => '/admin?plugin=' . $this->slug . '&page=import-csv',
                'permission' => ['superadmin', 'admin'],
                'position' => $menu['position'] ?? 50
            ]
        ];
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