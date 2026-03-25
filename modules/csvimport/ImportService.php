<?php
/**
 * modules/csvimport/ImportService.php
 * Handle CSV parsing, validation, normalization
 */

class ImportService
{
    private $config;
    private $tempPath;
    private $db;

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
        $this->config = array_merge([
            'max_file_size' => 5242880,
            'max_records_per_import' => 1000,
            'temp_storage_path' => 'storage/imports'
        ], $config);

        $this->tempPath = ROOT_PATH . '/' . trim($this->config['temp_storage_path'], '/');
        $this->db = Database::getInstance();

        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
    }

    /**
     * Parse CSV file
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

                if ($headers === null) {
                    $headers = array_map('trim', $row);
                    foreach ($headers as $idx => $col) {
                        $headerMapping[$idx] = self::COLUMN_MAP[$col] ?? $col;
                    }
                    continue;
                }

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
                        'error' => $e->getMessage()
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

        if (empty($record['parsed_data']['title']) && empty($record['parsed_data']['suspect_name'])) {
            $record['error'] = 'Title atau Nama Pelaku tidak boleh kosong';
            $record['status'] = 'error';
        }

        return $record;
    }

    private function parsePhones(string $phonesStr): array
    {
        if (empty($phonesStr)) return [];

        $phones = preg_split('/[\s,]+/', $phonesStr, -1, PREG_SPLIT_NO_EMPTY);
        $normalized = [];
        
        foreach ($phones as $phone) {
            $norm = $this->normalizePhone($phone);
            if ($norm) $normalized[] = $norm;
        }

                return array_values(array_unique($normalized));
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone) || strlen($phone) < 10 || strlen($phone) > 13) {
            return null;
        }

        if (substr($phone, 0, 1) === '0') {
            $phone = '62' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    private function parseLinks(string $linksStr): array
    {
        if (empty($linksStr)) return [];

        $links = array_map('trim', explode(',', $linksStr));
        return array_values(array_filter($links, fn($l) => filter_var($l, FILTER_VALIDATE_URL)));
    }

    private function parseKeywords(string $keywordsStr): array
    {
        if (empty($keywordsStr)) return [];

        $keywords = array_map('trim', explode(',', $keywordsStr));
        return array_values(array_filter($keywords));
    }

    private function parseModus(string $modusStr): array
    {
        if (empty($modusStr)) return [];

        $modi = array_map('trim', explode(',', $modusStr));
        return array_values(array_filter($modi));
    }

    private function validateRecords(array $records): array
    {
        $warnings = [];
        $seenPhones = [];

        foreach ($records as $record) {
            foreach ($record['parsed_data']['phones'] ?? [] as $phone) {
                if (isset($seenPhones[$phone])) {
                    $warnings[] = [
                        'line' => $record['line_no'],
                        'warning' => "Duplikasi nomor {$phone} dari baris {$seenPhones[$phone]}"
                    ];
                } else {
                    $seenPhones[$phone] = $record['line_no'];
                }
            }

            if (empty($record['parsed_data']['description']) && empty($record['parsed_data']['modus'])) {
                $warnings[] = [
                    'line' => $record['line_no'],
                    'warning' => "Deskripsi atau Modus sebaiknya tidak kosong"
                ];
            }
        }

        return $warnings;
    }

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

    public function getImportSession(string $sessionId): ?array
    {
        $sessionFile = $this->tempPath . '/' . $sessionId . '.json';
        if (!file_exists($sessionFile)) return null;

        $data = json_decode(file_get_contents($sessionFile), true);
        
        if (strtotime($data['expires_at']) < time()) {
            unlink($sessionFile);
            return null;
        }

        return $data;
    }

    public function updateRecordStatus(string $sessionId, int $lineNo, string $status, ?string $note = null): bool
    {
        $session = $this->getImportSession($sessionId);
        if (!$session) return false;

        foreach ($session['data']['records'] as &$record) {
            if ($record['line_no'] === $lineNo) {
                $record['status'] = $status;
                if ($note) $record['note'] = $note;
                break;
            }
        }

        file_put_contents($this->tempPath . '/' . $sessionId . '.json', json_encode($session, JSON_PRETTY_PRINT));
        return true;
    }

    public function bulkUpdateStatus(string $sessionId, string $action): bool
    {
        $session = $this->getImportSession($sessionId);
        if (!$session) return false;

        foreach ($session['data']['records'] as &$record) {
            switch ($action) {
                case 'approve_all':
                    $record['status'] = 'approved';
                    break;
                case 'approve_valid':
                    if (($record['status'] ?? 'pending') !== 'error') {
                        $record['status'] = 'approved';
                    }
                    break;
                case 'reject_all':
                    $record['status'] = 'rejected';
                    break;
            }
        }

        file_put_contents($this->tempPath . '/' . $sessionId . '.json', json_encode($session, JSON_PRETTY_PRINT));
        return true;
    }

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

        foreach ($session['data']['records'] as $record) {
            if ($record['status'] !== 'approved') continue;

            $result['total_submitted']++;

            try {
                $reportData = $this->normalizeToReport($record['parsed_data']);
                $reportData['created_by_import'] = 1;
                $reportData['import_session_id'] = $session['session_id'];

                $reportId = $this->db->insert('reports', $reportData);
                if ($reportId) {
                    $result['successful']++;
                    $result['created_report_ids'][] = $reportId;

                    // Trigger hook
                    __HOOKS->trigger('import.report_created', $reportId, $reportData);
                }
            } catch (Exception $e) {
                $result['failed']++;
                $result['errors'][] = [
                    'line' => $record['line_no'],
                    'error' => $e->getMessage()
                ];
            }
        }

        // Cleanup session
        $sessionFile = $this->tempPath . '/' . $sessionId . '.json';
        if (file_exists($sessionFile)) unlink($sessionFile);

        return $result;
    }

    private function normalizeToReport(array $data): array
    {
        return [
            'title' => $data['title'] ?? 'Laporan dari CSV Import',
            'description' => $data['description'] ?? '',
            'account_name' => $data['suspect_name'] ?? '',
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
}