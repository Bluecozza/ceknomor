<?php
/**
 * modules/export-data/routes/api.php
 */

return [
    [
        'method'  => 'GET',
        'path'    => '/generate',
        'handler' => 'ExportRoutes@handleGenerate'
    ]
];
