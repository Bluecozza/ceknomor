<?php
/**
 * modules/export-data/ExportService.php
 */

class ExportService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Handle export request via API
     */
    public function handleExportRequest(): void
    {
        // Simple authentication check
        $token = get_bearer_token();
        if (!$token) {
            $token = $_GET['token'] ?? null;
        }

        if (!$token) {
            Response::error('Authentication required', 401);
        }

        $payload = verify_jwt($token);
        if (!$payload) {
            Response::error('Invalid or expired token', 401);
        }

        if (!in_array($payload['role'], ['superadmin', 'admin'], true)) {
            Response::error('Access denied', 403);
        }

        $type   = $_GET['type'] ?? 'reports'; // reports, search_logs
        $format = $_GET['format'] ?? 'csv';   // csv, xml
        $status = $_GET['status'] ?? null;
        
        $data = [];
        $filename = "export_{$type}_" . date('Ymd_His');

        if ($type === 'reports') {
            $sql = "SELECT r.id, r.ulid, r.title, r.reported_value, r.status, r.created_at, r.view_count,
                           c.name as category_name, rt.name as report_type_name 
                    FROM reports r
                    LEFT JOIN categories c ON r.category_id = c.id
                    LEFT JOIN report_types rt ON r.report_type_id = rt.id";
            $params = [];
            if ($status) {
                $sql .= " WHERE r.status = ?";
                $params[] = $status;
            }
            $sql .= " ORDER BY r.created_at DESC";
            $data = $this->db->fetchAll($sql, $params);
        } elseif ($type === 'search_logs') {
            $sql = "SELECT * FROM search_logs ORDER BY created_at DESC LIMIT 5000";
            $data = $this->db->fetchAll($sql);
        } else {
            Response::error('Invalid export type', 400);
        }

        if (empty($data)) {
            Response::error('No data found to export', 404);
        }

        if ($format === 'csv') {
            $this->exportToCsv($data, $filename);
        } elseif ($format === 'xml') {
            $this->exportToXml($data, $type, $filename);
        } else {
            Response::error('Invalid format', 400);
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCsv(array $data, string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array_keys($data[0]));

        // Rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export to XML
     */
    private function exportToXml(array $data, string $rootName, string $filename): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xml"');

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $rootName . '/>');

        foreach ($data as $row) {
            $item = $xml->addChild('item');
            foreach ($row as $key => $value) {
                // Ensure key is valid XML element name
                $cleanKey = preg_replace('/[^a-z0-9_]/i', '_', $key);
                $item->addChild($cleanKey, htmlspecialchars((string)$value));
            }
        }

        echo $xml->asXML();
        exit;
    }
}

