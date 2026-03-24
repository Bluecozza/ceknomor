<?php
/**
 * modules/csvimport/hooks/navigation.php
 * Register plugin hooks dan actions
 */

$hooks = HookManager::getInstance();

// Subscribe to admin navigation build
$hooks->subscribe('admin.navigation.build', function(&$items) {
    $items[] = [
        'label' => 'Import CSV',
        'icon' => 'fa-file-csv',
        'url' => '?plugin=csvimport&page=import-csv',
        'permission' => ['superadmin', 'admin'],
        'position' => 5
    ];
}, 10);

// Subscribe to report created hook (from API)
$hooks->subscribe('import.report_created', function($reportId, $data) {
    // Can do additional processing here
    error_log("Report {$reportId} created from CSV import");
}, 10);