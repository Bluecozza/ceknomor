<?php
/**
 * Modul Analytics
 * Melacak page views, pencarian, dan statistik penggunaan platform.
 */

class AnalyticsModule
{
    /** @var Database */
    private $db;

    /** @var array */
    private $config;

    public function boot(array $config): void
    {
        $this->db     = Database::getInstance();
        $this->config = $config;

        $manager = ModuleManager::getInstance();
        $manager->addHook('search.performed', [$this, 'onSearchPerformed'], 10);
        $manager->addHook('report.created',   [$this, 'onReportCreated'],   10);
        $manager->addHook('report.viewed',    [$this, 'onReportViewed'],    10);
        $manager->addHook('page.view',        [$this, 'onPageView'],        10);
    }

    public function onSearchPerformed(array $data): void
    {
        if (!($this->config['track_ip'] ?? true)) {
            $data['ip'] = null;
        }

        try {
            $this->db->query(
                "INSERT INTO analytics_search_daily
                    (date, query_normalized, category, search_count, result_count, last_searched_at)
                 VALUES (CURDATE(), ?, ?, 1, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    search_count     = search_count + 1,
                    result_count     = result_count + VALUES(result_count),
                    last_searched_at = NOW()",
                [
                    $data['normalized'] ?? $data['query'],
                    $data['category']   ?? 'all',
                    $data['has_result'] ? 1 : 0,
                ]
            );
        } catch (Exception $e) {
            // Jangan hentikan aplikasi jika analytics gagal
        }
    }

    public function onReportCreated(array $data): void
    {
        try {
            $this->db->query(
                "INSERT INTO analytics_reports_daily (date, category_id, report_count)
                 VALUES (CURDATE(), ?, 1)
                 ON DUPLICATE KEY UPDATE report_count = report_count + 1",
                [$data['category_id'] ?? 0]
            );
        } catch (Exception $e) {
            // Silent fail
        }
    }

    public function onReportViewed(array $data): void
    {
        // View count ditangani di ReportService::getByUlid()
    }

    public function onPageView(array $data): void
    {
        if (!($this->config['track_ip'] ?? true)) {
            $data['ip'] = null;
        }

        $ua = ($this->config['track_user_agent'] ?? false)
            ? ($data['user_agent'] ?? null)
            : null;

        try {
            $this->db->insert('analytics_page_views', [
                'page'       => substr($data['path'] ?? '/', 0, 255),
                'ip_address' => $data['ip'] ?? null,
                'user_agent' => $ua,
                'referrer'   => isset($data['referrer']) ? substr($data['referrer'], 0, 500) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // Silent fail — analytics tidak boleh ganggu request utama
        }
    }

    public function getSummary(int $days = 30): array
    {
        $since = date('Y-m-d', strtotime("-{$days} days"));
        return [
            'total_searches'   => (int) $this->db->fetchColumn(
                "SELECT COALESCE(SUM(search_count),0) FROM analytics_search_daily WHERE date >= ?", [$since]
            ),
            'total_page_views' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM analytics_page_views WHERE DATE(created_at) >= ?", [$since]
            ),
            'total_reports'    => (int) $this->db->fetchColumn(
                "SELECT COALESCE(SUM(report_count),0) FROM analytics_reports_daily WHERE date >= ?", [$since]
            ),
        ];
    }

    public function cleanup(): void
    {
        $days   = (int) ($this->config['retention_days'] ?? 90);
        $cutoff = date('Y-m-d', strtotime("-{$days} days"));
        $this->db->query("DELETE FROM analytics_page_views WHERE DATE(created_at) < ?",  [$cutoff]);
        $this->db->query("DELETE FROM analytics_search_daily WHERE date < ?",             [$cutoff]);
        $this->db->query("DELETE FROM analytics_reports_daily WHERE date < ?",            [$cutoff]);
    }
}
