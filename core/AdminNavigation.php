<?php
/**
 * ./core/AdminNavigation.php
 * Build admin navigation from plugins
 */

class AdminNavigation
{
    private static $instance = null;
    private $items = [];
    private $pluginManager;
    private $hooks;
    private $initialized = false;

    private function __construct()
    {
        // Don't initialize here - do it lazy
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize (call this after plugins are loaded)
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->pluginManager = PluginManager::getInstance();
        $this->hooks = HookManager::getInstance();
        $this->initialized = true;
    }

    /**
     * Build navigation menu
     */
    public function build(): array
    {
        $this->initialize();

        $this->items = [
            [
                'label' => 'Dashboard',
                'icon' => 'fa-chart-line',
                'url' => '/admin',
                'permission' => ['superadmin', 'admin', 'moderator'],
                'position' => 0
            ],
            [
                'label' => 'Reports',
                'icon' => 'fa-file-alt',
                'url' => '/admin?page=reports',
                'permission' => ['superadmin', 'admin', 'moderator'],
                'position' => 1
            ],
            [
                'label' => 'Risk Scores',
                'icon' => 'fa-exclamation-triangle',
                'url' => '/admin?page=risk-scores',
                'permission' => ['superadmin', 'admin'],
                'position' => 2
            ],
            [
                'label' => 'Users',
                'icon' => 'fa-users',
                'url' => '/admin?page=users',
                'permission' => ['superadmin'],
                'position' => 3
            ],
            [
                'label' => 'Settings',
                'icon' => 'fa-cog',
                'url' => '/admin?page=settings',
                'permission' => ['superadmin'],
                'position' => 4
            ],
        ];

        // Trigger hook
        $this->hooks->trigger('admin.menu.build', $this->items);

        // Add plugin menus
        $this->addPluginMenus();

        // Sort by position
        usort($this->items, fn($a, $b) => ($a['position'] ?? 99) <=> ($b['position'] ?? 99));

        return $this->items;
    }

    /**
     * Add plugin menu items
     */
    private function addPluginMenus(): void
    {
        $loadedPlugins = $this->pluginManager->getLoadedPlugins();

        foreach ($loadedPlugins as $slug => $plugin) {
            if (method_exists($plugin, 'getAdminMenu')) {
                $menus = $plugin->getAdminMenu();
                if (is_array($menus)) {
                    foreach ($menus as $menu) {
                        $menu['plugin'] = $slug;
                        $this->items[] = $menu;
                    }
                }
            }
        }
    }

    /**
     * Get navigation items
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Add item manually
     */
    public function addItem(array $item): void
    {
        $this->items[] = $item;
    }
}