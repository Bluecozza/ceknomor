<?php
/**
 * core/ReportService.php
 * ---------------------------------------------------------------
 * Service layer untuk operasi laporan
 * Berisi business logic, tidak langsung akses database
 * ---------------------------------------------------------------
 */

class ReportService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Search ─────────────────────────────────────────────────

    /**
     * Cari laporan berdasarkan query
     * Mengembalikan risk summary + daftar laporan
     *
     * @param string $query        Query pencarian
     * @param string $categorySlug Kategori (opsional)
     * @return array ['count', 'risk', 'reports']
     */
    public function search(string $query, string $categorySlug = ''): array
    {
        $query = trim($query);
        if (empty($query)) return ['count' => 0, 'risk' => null, 'reports' => []];

        // Normalisasi query untuk pencarian akurat
        $normalizedQuery = $this->normalizeQuery($query, $categorySlug);

        // Simpan log pencarian
        $this->logSearch($query);

        // Cari risk score untuk nilai yang dicari
        $riskData = $this->db->fetchOne(
            "SELECT rs.*, c.name as category_name, c.slug as category_slug
             FROM risk_scores rs
             LEFT JOIN categories c ON rs.category_id = c.id
             WHERE rs.reported_value_normalized = ?",
            [$normalizedQuery]
        );

        // Cari laporan yang disetujui
        $conditions = ["r.reported_value_normalized = ?", "r.status = 'approved'"];
        $params     = [$normalizedQuery];

        if ($categorySlug) {
            $conditions[] = "c.slug = ?";
            $params[]     = $categorySlug;
        }

        $where   = implode(' AND ', $conditions);
        $reports = $this->db->fetchAll(
            "SELECT r.id, r.ulid, r.title, r.reported_value, r.reported_value_normalized,
                    r.bank_name, r.account_name, r.incident_date, r.amount_lost,
                    r.is_anonymous, r.view_count, r.helpful_count, r.created_at,
                    c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                    rt.name as report_type_name, rt.severity,
                    CASE WHEN r.is_anonymous = 1 THEN 'Anonim' ELSE rep.name END as reporter_name
             FROM reports r
             JOIN categories c ON r.category_id = c.id
             JOIN report_types rt ON r.report_type_id = rt.id
             JOIN reporters rep ON r.reporter_id = rep.id
             WHERE {$where}
             ORDER BY rt.severity DESC, r.created_at DESC",
            $params
        );

        $count = count($reports);

        // Simpan log pencarian dengan info lengkap
        $this->logSearch($query, $normalizedQuery, $categorySlug, $count);

        $result = [
            'count'      => $count,
            'query'      => $query,
            'normalized' => $normalizedQuery,
            'has_data'   => $count > 0,
            'risk'       => $riskData ? $this->formatRiskData($riskData) : null,
            'reports'    => array_map([$this, 'formatReportSummary'], $reports),
        ];

        // Trigger hook analytics
        ModuleManager::getInstance()->triggerHook('search.performed', [
            'query'      => $query,
            'normalized' => $normalizedQuery,
            'category'   => $categorySlug,
            'has_result' => $count > 0,
            'count'      => $count,
            'ip'         => get_client_ip(),
        ]);

        return $result;
    }

    /**
     * Dapatkan detail laporan berdasarkan ULID
     */
    public function getByUlid(string $ulid): ?array
    {
        $report = $this->db->fetchOne(
            "SELECT r.*, 
                    c.name as category_name, c.slug as category_slug, c.icon as category_icon,
                    rt.name as report_type_name, rt.severity,
                    CASE WHEN r.is_anonymous = 1 THEN 'Anonim' ELSE rep.name END as reporter_name,
                    CASE WHEN r.is_anonymous = 1 THEN '' ELSE rep.contact END as reporter_contact,
                    CASE WHEN r.is_anonymous = 1 THEN '' ELSE rep.contact_type END as reporter_contact_type
             FROM reports r
             JOIN categories c ON r.category_id = c.id
             JOIN report_types rt ON r.report_type_id = rt.id
             JOIN reporters rep ON r.reporter_id = rep.id
             WHERE r.ulid = ? AND r.status = 'approved'",
            [$ulid]
        );

        if (!$report) return null;

        // Increment view count
        $this->db->update('reports', ['view_count' => $report['view_count'] + 1], 'id = ?', [$report['id']]);

        return $this->formatReportDetail($report);
    }

    // ── Create Report ──────────────────────────────────────────

    /**
     * Buat laporan baru
     *
     * @param array $data Data laporan dari form
     * @return array ['success', 'ulid', 'message']
     */
    public function create(array $data): array
    {
        // Validasi data
        $errors = validate($data, [
            'category_id'      => 'required|numeric',
            'report_type_id'   => 'required|numeric',
            'reported_value'   => 'required|min:3|max:255',
            'title'            => 'required|min:10|max:255',
            'description'      => 'required|min:20',
            'reporter_name'    => 'required|min:2|max:150',
            'reporter_contact' => 'required|min:5|max:200',
        ]);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Dapatkan slug kategori untuk normalisasi
        $category = $this->db->fetchOne(
            "SELECT * FROM categories WHERE id = ? AND is_active = 1",
            [(int)$data['category_id']]
        );

        if (!$category) {
            return ['success' => false, 'errors' => ['category_id' => 'Kategori tidak valid']];
        }

        // Normalisasi nilai yang dilaporkan
        $normalizedValue = normalize_reported_value($data['reported_value'], $category['slug']);

        return $this->db->transaction(function ($db) use ($data, $normalizedValue, $category) {
            // Simpan data pelapor
            $contactType = filter_var($data['reporter_contact'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            $reporterId  = $db->insert('reporters', [
                'name'         => $data['reporter_name'],
                'contact'      => $data['reporter_contact'],
                'contact_type' => $contactType,
                'ip_address'   => get_client_ip(),
                'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);

            // Simpan laporan
            $ulid         = generate_ulid();
            $autoApprove  = $this->getSetting('auto_approve', false);
            $status       = $autoApprove ? 'approved' : 'pending';

            $reportId = $db->insert('reports', [
                'ulid'                        => $ulid,
                'category_id'                 => (int)$data['category_id'],
                'report_type_id'              => (int)$data['report_type_id'],
                'reported_value'              => $data['reported_value'],
                'reported_value_normalized'   => $normalizedValue,
                'bank_name'                   => $data['bank_name'] ?? null,
                'account_name'                => $data['account_name'] ?? null,
                'title'                       => $data['title'],
                'description'                 => $data['description'],
                'evidence_urls'               => !empty($data['evidence_urls']) ? json_encode($data['evidence_urls']) : null,
                'reporter_id'                 => $reporterId,
                'incident_date'               => $data['incident_date'] ?? null,
                'amount_lost'                 => !empty($data['amount_lost']) ? (float)$data['amount_lost'] : null,
                'is_anonymous'                => !empty($data['is_anonymous']) ? 1 : 0,
                'status'                      => $status,
            ]);

            // Update risk score jika auto-approve
            if ($autoApprove) {
                $this->updateRiskScore($normalizedValue, (int)$data['category_id']);
            }

            // Trigger hook untuk modul lain (e.g., notifikasi, analytics)
            $moduleManager = ModuleManager::getInstance();
            $moduleManager->triggerHook('report.created', [
                'report_id'  => $reportId,
                'ulid'       => $ulid,
                'category'   => $category,
                'value'      => $normalizedValue,
                'status'     => $status,
            ]);

            return [
                'success' => true,
                'ulid'    => $ulid,
                'status'  => $status,
                'message' => $autoApprove
                    ? 'Laporan berhasil dikirim dan langsung dipublikasikan'
                    : 'Laporan berhasil dikirim dan sedang dalam review moderator',
            ];
        });
    }

    // ── Risk Score Management ──────────────────────────────────

    /**
     * Hitung ulang dan update risk score untuk suatu nilai
     */
    public function updateRiskScore(string $normalizedValue, int $categoryId): void
    {
        // Hitung dari database
        $stats = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
                AVG(rt.severity) as avg_severity,
                MAX(r.created_at) as last_reported,
                MIN(r.created_at) as first_reported
             FROM reports r
             JOIN report_types rt ON r.report_type_id = rt.id
             WHERE r.reported_value_normalized = ?",
            [$normalizedValue]
        );

        $approved   = (int)($stats['approved'] ?? 0);
        $severity   = (float)($stats['avg_severity'] ?? 3.0);
        $score      = calculate_risk_score($approved, $severity, (int)($stats['total'] ?? 0));
        $level      = get_risk_level($score, $approved);

        // Upsert risk_scores
        $existing = $this->db->fetchOne(
            "SELECT id FROM risk_scores WHERE reported_value_normalized = ?",
            [$normalizedValue]
        );

        if ($existing) {
            $this->db->update('risk_scores', [
                'total_reports'    => (int)$stats['total'],
                'approved_reports' => $approved,
                'risk_score'       => $score,
                'risk_level'       => $level,
                'last_reported_at' => $stats['last_reported'],
                'category_id'      => $categoryId,
            ], 'reported_value_normalized = ?', [$normalizedValue]);
        } else {
            $this->db->insert('risk_scores', [
                'reported_value_normalized' => $normalizedValue,
                'category_id'               => $categoryId,
                'total_reports'             => (int)$stats['total'],
                'approved_reports'          => $approved,
                'risk_score'                => $score,
                'risk_level'                => $level,
                'last_reported_at'          => $stats['last_reported'],
                'first_reported_at'         => $stats['first_reported'],
            ]);
        }
    }

    // ── Admin Methods ──────────────────────────────────────────

    /**
     * Ambil daftar laporan untuk panel admin
     */
    public function getAdminList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (!empty($filters['status'])) {
            $conditions[] = "r.status = ?";
            $params[]     = $filters['status'];
        }
        if (!empty($filters['category_id'])) {
            $conditions[] = "r.category_id = ?";
            $params[]     = (int)$filters['category_id'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = "(r.reported_value LIKE ? OR r.title LIKE ?)";
            $like         = '%' . $this->db->escapeLike($filters['search']) . '%';
            $params[]     = $like;
            $params[]     = $like;
        }

        $where  = implode(' AND ', $conditions);
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM reports r WHERE {$where}", $params
        );

        $reports = $this->db->fetchAll(
            "SELECT r.id, r.ulid, r.title, r.reported_value, r.status,
                    r.created_at, r.view_count,
                    c.name as category_name, rt.name as report_type_name, rt.severity,
                    rep.name as reporter_name
             FROM reports r
             JOIN categories c ON r.category_id = c.id
             JOIN report_types rt ON r.report_type_id = rt.id
             JOIN reporters rep ON r.reporter_id = rep.id
             WHERE {$where}
             ORDER BY r.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['total' => $total, 'data' => $reports, 'page' => $page, 'perPage' => $perPage];
    }

    /**
     * Moderasi laporan (approve/reject/flag)
     * @return array ['success', 'message', 'report']
     */
    public function moderate(int $id, string $action, int $adminId, string $note = ''): array
    {
        $report = $this->db->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);
        if (!$report) {
            return ['success' => false, 'message' => 'Laporan tidak ditemukan'];
        }

        $validActions = ['approve', 'reject', 'flag'];
        if (!in_array($action, $validActions, true)) {
            return ['success' => false, 'message' => "Action tidak valid: {$action}"];
        }

        $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'flag' => 'flagged'];
        $status    = $statusMap[$action];

        $this->db->update('reports', [
            'status'       => $status,
            'admin_note'   => $note,
            'moderated_by' => $adminId,
            'moderated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // Update risk score jika disetujui atau ditolak
        if (in_array($status, ['approved', 'rejected'])) {
            $this->updateRiskScore($report['reported_value_normalized'], $report['category_id']);
        }

        // Log aktivitas
        $this->db->insert('activity_logs', [
            'admin_id'    => $adminId,
            'action'      => "report.{$action}",
            'entity_type' => 'report',
            'entity_id'   => $id,
            'description' => $note ?: "Status diubah ke {$status}",
            'ip_address'  => get_client_ip(),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Ambil laporan terbaru untuk response
        $updated = $this->db->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);

        return [
            'success' => true,
            'message' => "Laporan berhasil di-{$action}",
            'report'  => $updated,
        ];
    }

    // ── Statistics ─────────────────────────────────────────────

    /**
     * Statistik dashboard
     */
    public function getStats(): array
    {
        return [
            'total_reports'    => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM reports"),
            'pending_reports'  => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='pending'"),
            'approved_reports' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='approved'"),
            'total_searched'   => (int)$this->db->fetchColumn("SELECT COUNT(DISTINCT query) FROM search_logs"),
            'high_risk_count'  => (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM risk_scores WHERE risk_level IN ('high','critical')"
            ),
            'recent_reports'   => $this->db->fetchAll(
                "SELECT r.ulid, r.title, r.reported_value, r.status, r.created_at,
                        c.name as category_name
                 FROM reports r JOIN categories c ON r.category_id = c.id
                 ORDER BY r.created_at DESC LIMIT 5"
            ),
            'top_categories'   => $this->db->fetchAll(
                "SELECT c.name, COUNT(*) as count
                 FROM reports r JOIN categories c ON r.category_id = c.id
                 WHERE r.status = 'approved'
                 GROUP BY c.id ORDER BY count DESC LIMIT 5"
            ),
        ];
    }

    // ── Formatter Helpers ──────────────────────────────────────

    private function formatRiskData(array $data): array
    {
        $badge = get_risk_badge($data['risk_level']);
        return [
            'risk_level'       => $data['risk_level'],
            'risk_score'       => (float)$data['risk_score'],
            'total_reports'    => (int)$data['total_reports'],
            'approved_reports' => (int)$data['approved_reports'],
            'label'            => $badge['label'],
            'color'            => $badge['color'],
            'icon'             => $badge['icon'],
            'first_reported'   => $data['first_reported_at'],
            'last_reported'    => $data['last_reported_at'],
            'category_name'    => $data['category_name'] ?? '',
        ];
    }

    private function formatReportSummary(array $report): array
    {
        return [
            'ulid'             => $report['ulid'],
            'title'            => $report['title'],
            'reported_value'   => $report['reported_value'],
            'category_name'    => $report['category_name'],
            'category_slug'    => $report['category_slug'],
            'category_icon'    => $report['category_icon'],
            'report_type_name' => $report['report_type_name'],
            'severity'         => (int)$report['severity'],
            'reporter_name'    => $report['reporter_name'],
            'bank_name'        => $report['bank_name'],
            'account_name'     => $report['account_name'],
            'incident_date'    => $report['incident_date'],
            'amount_lost'      => $report['amount_lost'] ? (float)$report['amount_lost'] : null,
            'view_count'       => (int)$report['view_count'],
            'created_at'       => $report['created_at'],
            'time_ago'         => time_ago($report['created_at']),
        ];
    }

    private function formatReportDetail(array $report): array
    {
        $summary                   = $this->formatReportSummary($report);
        $summary['description']    = $report['description'];
        $summary['evidence_urls']  = json_decode($report['evidence_urls'] ?? '[]', true) ?? [];
        $summary['helpful_count']  = (int)$report['helpful_count'];
        $summary['reporter_contact_type'] = $report['reporter_contact_type'] ?? '';
        $summary['is_anonymous']   = (bool)$report['is_anonymous'];
        return $summary;
    }

    // ── Private Helpers ────────────────────────────────────────

    private function normalizeQuery(string $query, string $categorySlug = ''): string
    {
        if ($categorySlug) {
            return normalize_reported_value($query, $categorySlug);
        }

        // Auto-detect: coba deteksi jenis data dari query
        if (preg_match('/^\+?62|^08/', $query)) {
            return normalize_phone($query);
        }
        if (filter_var($query, FILTER_VALIDATE_EMAIL)) {
            return normalize_email($query);
        }

        // Default: hapus spasi dan lowercase
        return strtolower(preg_replace('/\s+/', '', $query));
    }

    private function logSearch(string $query, string $normalized = '', string $category = '', int $count = 0): void
    {
        try {
            $this->db->insert('search_logs', [
                'query'            => $query,
                'query_normalized' => $normalized ?: $query,
                'category'         => $category ?: null,
                'results_count'    => $count,
                'has_result'       => $count > 0 ? 1 : 0,
                'ip_address'       => get_client_ip(),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Log gagal tidak boleh hentikan aplikasi
        }
    }

    private function getSetting(string $key, $default = null)
    {
        $row = $this->db->fetchOne("SELECT value, type FROM settings WHERE `key` = ?", [$key]);
        if (!$row) return $default;

        switch ($row['type']) {
            case 'boolean': return (bool)(int)$row['value'];
            case 'integer': return (int)$row['value'];
            case 'json':    return json_decode($row['value'], true);
            default:        return $row['value'];
        }
    }
}
