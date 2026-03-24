<?php
/**
 * modules/csvimport/routes/api.php
 * Auto-registered API routes untuk plugin
 * Routes akan di-prefix dengan /api/v1/plugins/csvimport
 */

return [
    // POST /api/v1/plugins/csvimport/upload
    [
        'method'  => 'POST',
        'path'    => '/upload',
        'handler' => 'CsvimportRoutes@handleUpload'
    ],

    // GET /api/v1/plugins/csvimport/preview/:session_id
    [
        'method'  => 'GET',
        'path'    => '/preview/([a-z0-9_\.]+)',
        'pattern' => true,
        'handler' => 'CsvimportRoutes@handlePreview'
    ],

    // PUT /api/v1/plugins/csvimport/record/:session_id/:line_no
    [
        'method'  => 'PUT',
        'path'    => '/record/([a-z0-9_\.]+)/(\d+)',
        'pattern' => true,
        'handler' => 'CsvimportRoutes@handleRecordUpdate'
    ],

    // POST /api/v1/plugins/csvimport/bulk-action/:session_id
    [
        'method'  => 'POST',
        'path'    => '/bulk-action/([a-z0-9_\.]+)',
        'pattern' => true,
        'handler' => 'CsvimportRoutes@handleBulkAction'
    ],

    // POST /api/v1/plugins/csvimport/submit/:session_id
    [
        'method'  => 'POST',
        'path'    => '/submit/([a-z0-9_\.]+)',
        'pattern' => true,
        'handler' => 'CsvimportRoutes@handleSubmit'
    ],

    // GET /api/v1/plugins/csvimport/status/:session_id
    [
        'method'  => 'GET',
        'path'    => '/status/([a-z0-9_\.]+)',
        'pattern' => true,
        'handler' => 'CsvimportRoutes@handleStatus'
    ]
];