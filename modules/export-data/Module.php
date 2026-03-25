<?php
/**
 * modules/export-data/Module.php
 */

class ExportDataPlugin
{
    private $slug;
    private $manifest;
    private $db;
    private $hooks;

    public function __construct(string $slug, array $manifest)
    {
        $this->slug = $slug;
        $this->manifest = $manifest;
        $this->db = Database::getInstance();
        $this->hooks = HookManager::getInstance();
    }

    public function init(): void
    {
        // No manual route registration needed, api.php handles file-based routing
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
                'label' => $menu['title'] ?? 'Export Data',
                'icon' => $menu['icon'] ?? 'bi-download',
                'url' => '/admin?plugin=' . $this->slug . '&page=export',
                'permission' => ['superadmin', 'admin'],
                'position' => $menu['position'] ?? 51
            ]
        ];
    }
}

