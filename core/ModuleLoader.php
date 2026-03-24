<?php
/**
 * ./core/ModuleLoader.php
 * Module auto-discovery dan loading engine
 */

class ModuleLoader
{
    private $registry;
    private $hooks;

    public function __construct(ModuleRegistry $registry, HookManager $hooks)
    {
        $this->registry = $registry;
        $this->hooks = $hooks;
    }

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
                error_log("Invalid manifest for module: {$slug}");
                continue;
            }

            $manifest['slug'] = $slug;
            $manifest['path'] = $dir;

            $this->registry->register($slug, $manifest);
            $discovered[$slug] = $manifest;
        }

        $this->registry->syncWithDatabase();
        return $discovered;
    }

    public function load(string $slug): bool
    {
        if ($this->registry->isLoaded($slug)) {
            return true;
        }

        $metadata = $this->registry->get($slug);
        if (!$metadata) {
            error_log("Module not found in registry: {$slug}");
            return false;
        }

        if (!($metadata['is_enabled'] ?? false)) {
            return false;
        }

        $classFile = $metadata['path'] . '/Module.php';
        if (!file_exists($classFile)) {
            error_log("Module class file not found: {$classFile}");
            return false;
        }

        require_once $classFile;

        $className = ucfirst($slug) . 'Module';
        if (!class_exists($className)) {
            error_log("Module class not found: {$className}");
            return false;
        }

        try {
            $instance = new $className();
            $config = $metadata['config'] ?? [];

            if (method_exists($instance, 'boot')) {
                $instance->boot($config, $this->hooks);
            }

            $this->registry->setInstance($slug, $instance);
            return true;
        } catch (Exception $e) {
            error_log("Module loading failed for {$slug}: " . $e->getMessage());
            return false;
        }
    }

    public function loadAll(): void
    {
        $allModules = $this->registry->all();
        foreach ($allModules as $slug => $metadata) {
            if ($metadata['is_enabled'] ?? false) {
                try {
                    $this->load($slug);
                } catch (Throwable $e) {
                    error_log("Error loading module {$slug}: " . $e->getMessage());
                }
            }
        }
    }

    public function getRegistry(): ModuleRegistry
    {
        return $this->registry;
    }

    public function getHooks(): HookManager
    {
        return $this->hooks;
    }
}