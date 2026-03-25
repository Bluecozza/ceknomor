<?php
/**
 * modules/export-data/ExportRoutes.php
 */

class ExportRoutes
{
    /**
     * Handle the generation request
     */
    public static function handleGenerate(): void
    {
        require_once __DIR__ . '/ExportService.php';
        $svc = new ExportService();
        $svc->handleExportRequest();
    }
}

