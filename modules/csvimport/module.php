<?php
/**
 * modules/csvimport/Module.php
 * CSV Import Module — manage CSV upload, validation, preview, dan batch import
 */

// FIX: Require ImportService sebelum digunakan
require_once __DIR__ . '/ImportService.php';

class CsvimportModule
{
    private $config = [];
    private $importService = null;
    private $hookManager = null;

    /**
     * Boot method - support both HookManager parameter
     * (HookManager bisa null jika belum di-initialize)
     */
    public function boot(array $config, $hookManager = null): void
    {
        $this->config = $config;
        $this->hookManager = $hookManager;
        
        // Initialize import service dengan config dari database
        $this->importService = new ImportService($config);

        // Subscribe ke hooks jika HookManager ada
        if ($this->hookManager) {
            $this->hookManager->subscribe('import.started', [$this, 'onImportStarted'], 10);
            $this->hookManager->subscribe('import.completed', [$this, 'onImportCompleted'], 10);
            $this->hookManager->subscribe('import.failed', [$this, 'onImportFailed'], 10);
        }
    }

    public function getImportService()
    {
        return $this->importService;
    }

    public function onImportStarted($importId): void
    {
        error_log("CSV Import: Import started - {$importId}");
    }

    public function onImportCompleted($result): void
    {
        error_log("CSV Import: Import completed - " . json_encode($result));
    }

    public function onImportFailed($error): void
    {
        error_log("CSV Import: Import failed - {$error}");
    }
}