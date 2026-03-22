<?php
/**
 * ./core/ReportService.php
 * Business logic untuk laporan — search, create, moderate, stats
 */

class ReportService
{
    /** @var Database */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Search ────────────────────────────────────────────────
    public function search(string $query, string $categorySlug = ''): array
    {
        $query = trim($query);
        if (empty($query)) return ['count' => 0, 'has_data' => false, 'risk' => null, 'reports' => []];

        $normalized = normalize_value($query, $categorySlug ?: $this->detectCategory($query));

        // Risk score
        $riskRow = $this->db->fetchOne(
            "SELECT rs.*, c.name AS category_name FROM risk_scores rs
             LEFT JOIN categories c ON c.id = rs.category_id
             WHERE rs.reported_value_normalized = ?",
            [$normalized]
        );

        // Reports
        $conds  = ["r.reported_value_normalized = ?", "r.status = 'approved'"];
        $params = [$normalized];
        if ($categorySlug) { $conds[] = "c.slug = ?"; $params[] = $categorySlug; }

        $reports = $this->db->fetchAll(
            "SELECT r.id, r.ulid, r.title, r.reported_value, r.bank_name, r.account_name,
                    r.incident_date, r.amount_lost, r.view_count, r.created_at,
                    c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    rt.name AS report_type_name, rt.severity,
                    CASE WHEN r.is_anonymous=1 THEN 'Anonim' ELSE rep.name END AS reporter_name
             FROM reports r
             JOIN categories c ON c.id = r.category_id
             JOIN report_types rt ON rt.id = r.report_type_id
             JOIN reporters rep ON rep.id = r.reporter_id
             WHERE " . implode(' AND ', $conds) . "
             ORDER BY rt.severity DESC, r.created_at DESC",
            $params
        );

        $count = count($reports);

        // Log
        $this->logSearch($query, $normalized, $categorySlug, $count);

        // Hook
        ModuleManager::getInstance()->triggerHook('search.performed', [
            'query'      => $query,
            'normalized' => $normalized,
            'category'   => $categorySlug,
            'has_result' => $count > 0,
            'count'      => $count,
            'ip'         => get_client_ip(),
        ]);

        return [
            'count'      => $count,
            'has_data'   => $count > 0,
            'query'      => $query,
            'normalized' => $normalized,
            'risk'       => $riskRow ? $this->formatRisk($riskRow) : null,
            'reports'    => array_map([$this, 'formatSummary'], $reports),
        ];
    }

    // ── Get by ULID ───────────────────────────────────────────
    public function getByUlid(string $ulid): ?array
    {
        $r = $this->db->fetchOne(
            "SELECT r.*,
                    c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                    rt.name AS report_type_name, rt.severity,
                    rs.risk_score, rs.risk_level,
                    CASE WHEN r.is_anonymous=1 THEN 'Anonim' ELSE rep.name END AS reporter_name
             FROM reports r
             JOIN categories c ON c.id = r.category_id
             JOIN report_types rt ON rt.id = r.report_type_id
             JOIN reporters rep ON rep.id = r.reporter_id
             LEFT JOIN risk_scores rs ON rs.reported_value_normalized = r.reported_value_normalized
             WHERE r.ulid = ? AND r.status = 'approved'",
            [$ulid]
        );
        if (!$r) return null;

        $this->db->update('reports', ['view_count' => $r['view_count'] + 1], 'id = ?', [$r['id']]);
        return $this->formatDetail($r);
    }

    // ── Create ────────────────────────────────────────────────
    public function create(array $data): array
    {
        $errors = validate($data, [
            'category_id'      => 'required|numeric',
            'report_type_id'   => 'required|numeric',
            'reported_value'   => 'required|min:3|max:255',
            'title'            => 'required|min:10|max:255',
            'description'      => 'required|min:20',
            'reporter_name'    => 'required|min:2|max:150',
            'reporter_contact' => 'required|min:5|max:200',
        ]);
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        $cat = $this->db->fetchOne("SELECT * FROM categories WHERE id = ? AND is_active = 1", [(int)$data['category_id']]);
        if (!$cat) return ['success' => false, 'errors' => ['category_id' => 'Kategori tidak valid']];

        $normalized = normalize_value($data['reported_value'], $cat['slug']);

        return $this->db->transaction(function($db) use ($data, $normalized, $cat) {
            $contactType = filter_var($data['reporter_contact'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
            $reporterId  = $db->insert('reporters', [
                'name'         => e($data['reporter_name']),
                'contact'      => $data['reporter_contact'],
                'contact_type' => $contactType,
                'ip_address'   => get_client_ip(),
                'user_agent'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);

            $ulid   = generate_ulid();
            $autoOk = $this->getSetting('auto_approve', false);
            $status = $autoOk ? 'approved' : 'pending';

            $reportId = $db->insert('reports', [
                'ulid'                      => $ulid,
                'category_id'               => (int)$data['category_id'],
                'report_type_id'            => (int)$data['report_type_id'],
                'reported_value'            => $data['reported_value'],
                'reported_value_normalized' => $normalized,
                'bank_name'                 => $data['bank_name']    ?? null,
                'account_name'              => $data['account_name'] ?? null,
                'title'                     => e($data['title']),
                'description'               => $data['description'],
                'evidence_urls'             => !empty($data['evidence_urls']) ? json_encode($data['evidence_urls']) : null,
                'reporter_id'               => $reporterId,
                'incident_date'             => $data['incident_date'] ?? null,
                'amount_lost'               => !empty($data['amount_lost']) ? (float)$data['amount_lost'] : null,
                'is_anonymous'              => !empty($data['is_anonymous']) ? 1 : 0,
                'status'                    => $status,
            ]);

            if ($autoOk) $this->updateRiskScore($normalized, (int)$data['category_id']);

            ModuleManager::getInstance()->triggerHook('report.created', [
                'report_id'   => $reportId,
                'ulid'        => $ulid,
                'category_id' => (int)$data['category_id'],
                'status'      => $status,
            ]);

            return [
                'success' => true,
                'ulid'    => $ulid,
                'status'  => $status,
                'message' => $autoOk
                    ? 'Laporan berhasil dikirim dan dipublikasikan'
                    : 'Laporan berhasil dikirim, menunggu review moderator',
            ];
        });
    }

    // ── Moderate ──────────────────────────────────────────────
    public function moderate(int $id, string $action, int $adminId, string $note = ''): array
    {
        try {
            $report = $this->db->fetchOne("SELECT * FROM reports WHERE id = ?", [$id]);
            if (!$report) return ['success' => false, 'message' => 'Laporan tidak ditemukan'];

            if (!in_array($action, ['approve','reject','flag'], true))
                return ['success' => false, 'message' => "Action tidak valid: {$action}"];

            $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'flag' => 'flagged'];
            $status    = $statusMap[$action];

            $this->db->update('reports', [
                'status'       => $status,
                'admin_note'   => $note,
                'moderated_by' => $adminId,
                'moderated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);

            if (in_array($status, ['approved','rejected'])) {
                try { $this->updateRiskScore($report['reported_value_normalized'], (int)$report['category_id']); }
                catch (Exception $e) { error_log('updateRiskScore: ' . $e->getMessage()); }
            }

            try {
                $this->db->insert('activity_logs', [
                    'admin_id'    => $adminId,
                    'action'      => "report.{$action}",
                    'entity_type' => 'report',
                    'entity_id'   => $id,
                    'description' => $note ?: "Status → {$status}",
                    'ip_address'  => get_client_ip(),
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            } catch (Exception $e) {}

            return ['success' => true, 'message' => "Laporan berhasil di-{$action}"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Admin List ────────────────────────────────────────────
    public function getAdminList(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $conds  = ['1=1'];
        $params = [];

        if (!empty($filters['status']))      { $conds[] = "r.status = ?";    $params[] = $filters['status']; }
        if (!empty($filters['category']))    { $conds[] = "c.slug = ?";      $params[] = $filters['category']; }
        if (!empty($filters['category_id'])) { $conds[] = "r.category_id=?"; $params[] = (int)$filters['category_id']; }
        if (!empty($filters['report_type'])) { $conds[] = "rt.slug = ?";     $params[] = $filters['report_type']; }
        if (!empty($filters['search'])) {
            $like = '%' . $this->db->escapeLike($filters['search']) . '%';
            $conds[] = "(r.reported_value LIKE ? OR r.title LIKE ?)";
            $params[] = $like; $params[] = $like;
        }
        if (!empty($filters['date_from'])) { $conds[] = "DATE(r.created_at) >= ?"; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $conds[] = "DATE(r.created_at) <= ?"; $params[] = $filters['date_to']; }

        $where  = implode(' AND ', $conds);
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM reports r
             JOIN categories c ON c.id=r.category_id
             JOIN report_types rt ON rt.id=r.report_type_id
             WHERE {$where}", $params
        );

        $rows = $this->db->fetchAll(
            "SELECT r.id, r.ulid, r.title, r.reported_value, r.status,
                    r.created_at, r.view_count,
                    c.name AS category_name, c.slug AS category_slug,
                    rt.name AS report_type_name, rt.severity,
                    CASE WHEN r.is_anonymous=1 THEN 'Anonim' ELSE rep.name END AS reporter_name
             FROM reports r
             JOIN categories c ON c.id=r.category_id
             JOIN report_types rt ON rt.id=r.report_type_id
             JOIN reporters rep ON rep.id=r.reporter_id
             WHERE {$where}
             ORDER BY r.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}", $params
        );

        return ['total' => $total, 'data' => $rows];
    }

    // ── Stats ─────────────────────────────────────────────────
    public function getStats(): array
    {
        return [
            'total_reports'    => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM reports"),
            'approved_reports' => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM reports WHERE status='approved'"),
            'high_risk_count'  => (int)$this->db->fetchColumn("SELECT COUNT(*) FROM risk_scores WHERE risk_level IN ('high','critical')"),
            'recent_reports'   => $this->db->fetchAll(
                "SELECT r.ulid, r.reported_value, r.status, r.created_at, c.name AS category_name
                 FROM reports r JOIN categories c ON c.id=r.category_id
                 ORDER BY r.created_at DESC LIMIT 5"
            ),
        ];
    }

    // ── Risk Score ────────────────────────────────────────────
    public function updateRiskScore(string $normalized, int $categoryId): void
    {
        $stats = $this->db->fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN r.status='approved' THEN 1 ELSE 0 END) AS approved,
                    AVG(rt.severity) AS avg_severity,
                    MAX(r.created_at) AS last_reported,
                    MIN(r.created_at) AS first_reported
             FROM reports r JOIN report_types rt ON rt.id=r.report_type_id
             WHERE r.reported_value_normalized = ?",
            [$normalized]
        );

        $approved = (int)($stats['approved']     ?? 0);
        $severity = (float)($stats['avg_severity'] ?? 3.0);
        $score    = calculate_risk_score($approved, $severity, (int)($stats['total'] ?? 0));
        $level    = get_risk_level($score, $approved);

        $existing = $this->db->fetchOne("SELECT id FROM risk_scores WHERE reported_value_normalized = ?", [$normalized]);
        if ($existing) {
            $this->db->update('risk_scores', [
                'total_reports'    => (int)$stats['total'],
                'approved_reports' => $approved,
                'risk_score'       => $score,
                'risk_level'       => $level,
                'last_reported_at' => $stats['last_reported'],
                'category_id'      => $categoryId,
            ], 'reported_value_normalized = ?', [$normalized]);
        } else {
            $this->db->insert('risk_scores', [
                'reported_value_normalized' => $normalized,
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

    // ── Formatters ────────────────────────────────────────────
    private function formatRisk(array $r): array
    {
        $badge = get_risk_badge($r['risk_level']);
        return [
            'risk_level'       => $r['risk_level'],
            'risk_score'       => (float)$r['risk_score'],
            'approved_reports' => (int)$r['approved_reports'],
            'total_reports'    => (int)$r['total_reports'],
            'label'            => $badge['label'],
            'color'            => $badge['color'],
            'icon'             => $badge['icon'],
            'first_reported'   => $r['first_reported_at'] ?? null,
            'last_reported'    => $r['last_reported_at']  ?? null,
            'category_name'    => $r['category_name'] ?? '',
        ];
    }

    private function formatSummary(array $r): array
    {
        return [
            'ulid'             => $r['ulid'],
            'title'            => $r['title'],
            'reported_value'   => $r['reported_value'],
            'category_name'    => $r['category_name'],
            'category_slug'    => $r['category_slug'],
            'category_icon'    => $r['category_icon'],
            'report_type_name' => $r['report_type_name'],
            'severity'         => (int)$r['severity'],
            'reporter_name'    => $r['reporter_name'],
            'bank_name'        => $r['bank_name']    ?? null,
            'account_name'     => $r['account_name'] ?? null,
            'incident_date'    => $r['incident_date'] ?? null,
            'amount_lost'      => $r['amount_lost'] ? (float)$r['amount_lost'] : null,
            'view_count'       => (int)$r['view_count'],
            'created_at'       => $r['created_at'],
            'time_ago'         => time_ago($r['created_at']),
        ];
    }

    private function formatDetail(array $r): array
    {
        $s = $this->formatSummary($r);
        $s['description']   = $r['description'];
        $s['evidence_urls'] = json_decode($r['evidence_urls'] ?? '[]', true) ?? [];
        $s['helpful_count'] = (int)$r['helpful_count'];
        $s['risk_score']    = $r['risk_score']  ?? null;
        $s['risk_level']    = $r['risk_level']  ?? 'unknown';
        $s['reported_value_masked'] = mask_value($r['reported_value']);
        return $s;
    }

    // ── Private Helpers ───────────────────────────────────────
    private function detectCategory(string $query): string
    {
        $q = preg_replace('/[\s\-\(\)\.]+/', '', $query);
        if (preg_match('/^(\+62|62|08)\d{7,13}$/', $q)) return 'phone';
        if (filter_var($query, FILTER_VALIDATE_EMAIL))    return 'email';
        if (preg_match('/^\d{8,20}$/', $q))               return 'bank_account';
        return '';
    }

    private function logSearch(string $query, string $normalized, string $cat, int $count): void
    {
        try {
            $this->db->insert('search_logs', [
                'query'            => $query,
                'query_normalized' => $normalized,
                'category'         => $cat ?: null,
                'results_count'    => $count,
                'has_result'       => $count > 0 ? 1 : 0,
                'ip_address'       => get_client_ip(),
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {}
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
