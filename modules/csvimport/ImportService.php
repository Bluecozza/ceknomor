<?php
/**
 * modules/csvimport/ImportService.php
 * Handle CSV parsing, validation, dan data normalization
 */

class ImportService
{
    private $config;
    private $tempPath;
    private $db;

    // CSV Column mapping
    const COLUMN_MAP = [
        'Title' => 'title',
        'Phone' => 'phones',
        'Rekening' => 'bank_account',
        'Nama Pelaku' => 'suspect_name',
        'Links' => 'links',
        'Modus' => 'modus',
        'Keywords' => 'keywords',
        'Description' => 'description',
        'URL' => 'source_url',
        'Image' => 'image_url',
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->tempPath = ROOT_PATH . '/' . trim($config['temp_storage_path'], '/');
        $this->db = Database::getInstance();
        
        // Ensure temp directory exists
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Parse CSV file dan return array of records
     */
    public function parseCSV(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['error' => 'File tidak ditemukan'];
        }

        $records = [];
        $errors = [];
        $lineNo = 0;

        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = null;
            $headerMapping = [];

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $lineNo++;

                // First row = header
                if ($headers === null) {
                    $headers = array_map('trim', $row);
                    // Build mapping dari CSV header ke internal field
                    foreach ($headers as $idx => $col) {
                        $headerMapping[$idx] = self::COLUMN_MAP[$col] ?? $col;
                    }
                    continue;
                }

                // Parse row
                try {
                    $record = $this->parseRow($row, $headerMapping, $lineNo);
                    if (isset($record['error'])) {
                        $errors[] = $record;
                    } else {
                        $records[] = $record;
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'line' => $lineNo,
                        'error' => $e->getMessage(),
                        'raw_data' => $row
                    ];
                }
            }
            fclose($handle);
        }

        return [
            'success' => count($errors) === 0,
            'total_records' => count($records),
            'total_errors' => count($errors),
            'records' => array_slice($records, 0, $this->config['max_records_per_import']),
            'errors' => $errors,
            'warnings' => $this->validateRecords($records)
        ];
    }

    /**
     * Parse single row dari CSV
     */
    private function parseRow(array $row, array $headerMapping, int $lineNo): array
    {
        $record = [
            'line_no' => $lineNo,
            'status' => 'pending',
            'raw_data' => $row,
            'parsed_data' => []
        ];

        foreach ($headerMapping as $idx => $fieldName) {
            $value = trim($row[$idx] ?? '');
            
            // Special handling untuk fields tertentu
            switch ($fieldName) {
                case 'phones':
                    $record['parsed_data'][$fieldName] = $this->parsePhones($value);
                    break;
                case 'links':
                    $record['parsed_data'][$fieldName] = $this->parseLinks($value);
                    break;
                case 'keywords':
                    $record['parsed_data'][$fieldName] = $this->parseKeywords($value);
                    break;
                case 'modus':
                    $record['parsed_data'][$fieldName] = $this->parseModus($value);
                    break;
                default:
                    $record['parsed_data'][$fieldName] = $value;
            }
        }

        // Validation minimal
        if (empty($record['parsed_data']['title']) && empty($record['parsed_data']['suspect_name'])) {
            $record['error'] = 'Title atau Nama Pelaku tidak boleh kosong';
            $record['status'] = 'error';
        }

        return $record;
    }

    /**
     * Parse phone numbers — bisa multiple, separated by comma
     */
    private function parsePhones(string $phonesStr): array
    {
        if (empty($phonesStr)) {
            return [];
        }

        $phones = array_map('trim', explode(',', $phonesStr));
        $phones = array_filter($phones); // Remove empty values
        
        // Normalize phone numbers
        $normalized = [];
        foreach ($phones as $phone) {
            $normalized[] = $this->normalizePhone($phone);
        }

        return array_filter($normalized); // Remove failed normalization
    }

    /**
     * Normalize nomor telepon Indonesia
     */
    private function normalizePhone(string $phone): ?string
    {
        // Remove non-digits
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone)) return null;

        // Must be 10-13 digits
        if (strlen($phone) < 10 || strlen($phone) > 13) {
            return null;
        }

        // If starts with 0, replace with 62
        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        // If doesn't start with 62, assume it's 62
        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Parse links — bisa multiple, separated by comma
     */
    private function parseLinks(string $linksStr): array
    {
        if (empty($linksStr)) {
            return [];
        }

        $links = array_map('trim', explode(',', $linksStr));
        $links = array_filter($links, function($link) {
            return !empty($link) && filter_var($link, FILTER_VALIDATE_URL);
        });

        return array_values($links);
    }

    /**
     * Parse keywords — split by comma
     */
    private function parseKeywords(string $keywordsStr): array
    {
        if (empty($keywordsStr)) {
            return [];
        }

        $keywords = array_map('trim', explode(',', $keywordsStr));
        $keywords = array_filter($keywords);
        return array_values($keywords);
    }

    /**
     * Parse modus — split by comma dan normalize
     */
    private function parseModus(string $modusStr): array
    {
        if (empty($modusStr)) {
            return [];
        }

        $modi = array_map('trim', explode(',', $modusStr));
        $modi = array_filter($modi);
        
        // Map ke modus yang sudah ada di database
        $mapped = [];
        foreach ($modi as $m) {
            $mapped[] = $m; // Bisa di-extend dengan mapping logic
        }

        return array_values($mapped);
    }

    /**
     * Validate semua records — cek duplikasi, missing data, etc
     */
    private function validateRecords(array $records): array
    {
        $warnings = [];
        $seenPhones = [];

        foreach ($records as $idx => $record) {
            // Check duplikasi dalam CSV
            foreach ($record['parsed_data']['phones'] ?? [] as $phone) {
                if (isset($seenPhones[$phone])) {
                    $warnings[] = [
                        'line' => $record['line_no'],
                        'warning' => "Nomor {$phone} sudah ada di baris {$seenPhones[$phone]}"
                    ];
                } else {
                    $seenPhones[$phone] = $record['line_no'];
                }
            }

            // Check duplikasi dengan database
            $existingPhones = $this->findExistingPhones($record['parsed_data']['phones'] ?? []);
            if (!empty($existingPhones)) {
                $warnings[] = [
                    'line' => $record['line_no'],
                    'warning' => "Nomor sudah ada di database: " . implode(', ', $existingPhones)
                ];
            }

            // Check missing critical data
            if (empty($record['parsed_data']['description']) && 
                empty($record['parsed_data']['modus'])) {
                $warnings[] = [
                    'line' => $record['line_no'],
                    'warning' => "Deskripsi atau Modus sebaiknya tidak kosong"
                ];
            }
        }

        return $warnings;
    }

    /**
     * Find existing phones in database
     */
    private function findExistingPhones(array $phones): array
    {
        if (empty($phones)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $existing = $this->db->fetchAll(
            "SELECT DISTINCT reported_value_normalized FROM risk_scores 
             WHERE reported_value_normalized IN ({$placeholders})",
            $phones
        );

        return array_column($existing, 'reported_value_normalized');
    }

    /**
     * Save import session ke database untuk preview
     */
    public function saveImportSession(array $parseResult): string
    {
        $sessionId = uniqid('imp_', true);
        $sessionFile = $this->tempPath . '/' . $sessionId . '.json';

        file_put_contents($sessionFile, json_encode([
            'session_id' => $sessionId,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'data' => $parseResult
        ], JSON_PRETTY_PRINT));

        return $sessionId;
    }

    /**
     * Get import session
     */
    public function getImportSession(string $sessionId): ?array
    {
        $sessionFile = $this->tempPath . '/' . $sessionId . '.json';
        if (!file_exists($sessionFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($sessionFile), true);
        
        // Check if expired
        if (strtotime($data['expires_at']) < time()) {
            unlink($sessionFile);
            return null;
        }

        return $data;
    }

    /**
     * Update record status dalam session
     */
    public function updateRecordStatus(string $sessionId, int $lineNo, string $status, ?string $note = null): bool
    {
        $session = $this->getImportSession($sessionId);
        if (!$session) {
            return false;
        }

        // Find dan update record
        foreach ($session['data']['records'] as &$record) {
            if ($record['line_no'] === $lineNo) {
                $record['status'] = $status;
                if ($note) {
                    $record['note'] = $note;
                }
                break;
            }
        }

        // Save kembali
        $sessionFile = $this->tempPath . '/' . $sessionId . '.json';
        file_put_contents($sessionFile, json_encode($session, JSON_PRETTY_PRINT));

        return true;
    }

    /**
     * Submit import — create reports dari approved records
     */
    public function submitImport(string $sessionId, int $adminId): array
    {
        $session = $this->getImportSession($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Session tidak ditemukan'];
        }

        $result = [
            'success' => true,
            'total_submitted' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
            'created_report_ids' => []
        ];

        try {
            $this->db->transaction(function($db) use (&$result, $session, $adminId) {
                foreach ($session['data']['records'] as $record) {
                    // Skip yang tidak approved
                    if ($record['status'] !== 'approved') {
                        continue;
                    }

                    $result['total_submitted']++;

                    try {
                        // Create report
                        $reportData = $this->normalizeToReport($record['parsed_data']);
                        $reportData['created_by_import'] = 1;
                        $reportData['import_session_id'] = $session['session_id'];

                        $reportId = $this->createReport($reportData, $adminId);
                        if ($reportId) {
                            $result['successful']++;
                            $result['created_report_ids'][] = $reportId;
                        }
                    } catch (Exception $e) {
                        $result['failed']++;
                        $result['errors'][] = [
                            'line' => $record['line_no'],
                            'error' => $e->getMessage()
                        ];
                    }
                }
            });

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        // Clean up session file
        $sessionFile = $this->tempPath . '/' . $sessionId . '.json';
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }

        return $result;
    }

    /**
     * Normalize CSV data ke report format
     */
    private function normalizeToReport(array $data): array
    {
        return [
            'title' => $data['title'] ?? 'Laporan dari CSV Import',
            'description' => $data['description'] ?? '',
            'suspect_name' => $data['suspect_name'] ?? '',
            'phones' => !empty($data['phones']) ? json_encode($data['phones']) : null,
            'bank_account' => $data['bank_account'] ?? null,
            'links' => !empty($data['links']) ? json_encode($data['links']) : null,
            'modus' => !empty($data['modus']) ? json_encode($data['modus']) : null,
            'keywords' => !empty($data['keywords']) ? json_encode($data['keywords']) : null,
            'source_url' => $data['source_url'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create report di database
     */
    private function createReport(array $data, int $adminId): ?int
    {
        try {
            return $this->db->insert('reports', $data);
        } catch (Exception $e) {
            throw new Exception("Gagal create report: " . $e->getMessage());
        }
    }

    /**
     * Cleanup old import sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $count = 0;
        $files = glob($this->tempPath . '/*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && strtotime($data['expires_at']) < time()) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}