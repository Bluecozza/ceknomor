<?php
/**
 * Modul Analytics
 * Melacak page views, pencarian, dan statistik penggunaan platform.
 * Mendaftarkan hook ke sistem untuk menangkap berbagai event.
 */

namespace Modules\Analytics;

use Core\Database;
use Core\ModuleManager;

class AnalyticsModule
{
    /** @var Database */
    private Database $db;

    /** @var array Konfigurasi modul dari database */
    private array $config;

    /**
     * Dipanggil oleh ModuleManager saat sistem boot.
     * Daftarkan semua hook di sini.
     */
    public function boot(array $config): void
    {
        $this->db     = Database::getInstance();
        $this->config = $config;

        $manager = ModuleManager::getInstance();

        // Hook: setiap kali ada pencarian dilakukan
        $manager->addHook('search.performed', [$this, 'onSearchPerformed'], 10);

        // Hook: setiap kali laporan baru dibuat
        $manager->addHook('report.created', [$this, 'onReportCreated'], 10);

        // Hook: setiap kali detail laporan dilihat
        $manager->addHook('report.viewed', [$this, 'onReportViewed'], 10);

        // Hook: page view umum
        $manager->addHook('page.view', [$this, 'onPageView'], 10);
    }

    // ---------------------------------------------------------------
    // EVENT HANDLERS
    // ---------------------------------------------------------------

    /**
     * Dipanggil saat pencarian dilakukan.
     * Data sudah dicatat di search_logs oleh ReportService,
     * modul ini melakukan agregasi tambahan.
     *
     * @param array $data ['query', 'normalized', 'category', 'has_result', 'count', 'ip']
     */
    public function onSearchPerformed(array $data): void
    {
        if (!($this->config['track_ip'] ?? true)) {
            $data['ip'] = null;
        }

        // Update atau insert ke tabel agregasi harian
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
    }

    /**
     * Dipanggil saat laporan baru berhasil dibuat.
     *
     * @param array $data ['report_id', 'category_id', 'report_type_id', 'normalized_value']
     */
    public function onReportCreated(array $data): void
    {
        // Update hitungan laporan harian per kategori
        $this->db->query(
            "INSERT INTO analytics_reports_daily
                (date, category_id, report_count)
             VALUES (CURDATE(), ?, 1)
             ON DUPLICATE KEY UPDATE
                report_count = report_count + 1",
            [$data['category_id'] ?? 0]
        );
    }

    /**
     * Dipanggil saat laporan dilihat (detail view).
     *
     * @param array $data ['report_id', 'ulid', 'ip']
     */
    public function onReportViewed(array $data): void
    {
        // View count sudah ditangani di ReportService::getByUlid()
        // Di sini kita catat unique daily views jika perlu
    }

    /**
     * Dipanggil untuk setiap page view website.
     *
     * @param array $data ['path', 'ip', 'user_agent', 'referrer']
     */
    public function onPageView(array $data): void
    {
        if (!($this->config['track_ip'] ?? true)) {
            $data['ip'] = null;
        }

        $ua = ($this->config['track_user_agent'] ?? false)
            ? ($data['user_agent'] ?? null)
            : null;

        // Simpan ke tabel page views
        try {
            $this->db->insert('analytics_page_views', [
                'page'       => substr($data['path'] ?? '/', 0, 255),
                'ip_address' => $data['ip'] ?? null,
                'user_agent' => $ua,
                'referrer'   => isset($data['referrer']) ? substr($data['referrer'], 0, 500) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Gagal diam-diam — analitik tidak boleh mengganggu request utama
        }
    }

    // ---------------------------------------------------------------
    // DATA ACCESS — Digunakan oleh API/Admin
    // ---------------------------------------------------------------

    /**
     * Ringkasan statistik untuk periode tertentu.
     */
    public function getSummary(int $days = 30): array
    {
        $since = date('Y-m-d', strtotime("-{$days} days"));

        return [
            'total_searches'  => (int) $this->db->fetchColumn(
                "SELECT SUM(search_count) FROM analytics_search_daily WHERE date >= ?",
                [$since]
            ),
            'total_page_views' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM analytics_page_views WHERE DATE(created_at) >= ?",
                [$since]
            ),
            'total_reports'   => (int) $this->db->fetchColumn(
                "SELECT SUM(report_count) FROM analytics_reports_daily WHERE date >= ?",
                [$since]
            ),
            'unique_ips'      => (int) $this->db->fetchColumn(
                "SELECT COUNT(DISTINCT ip_address) FROM analytics_page_views WHERE DATE(created_at) >= ?",
                [$since]
            ),
        ];
    }

    /**
     * Tren pencarian harian.
     */
    public function getSearchTrend(int $days = 30): array
    {
        $since = date('Y-m-d', strtotime("-{$days} days"));

        return $this->db->fetchAll(
            "SELECT date, SUM(search_count) as searches
             FROM analytics_search_daily
             WHERE date >= ?
             GROUP BY date
             ORDER BY date ASC",
            [$since]
        );
    }

    /**
     * Kueri pencarian terpopuler.
     */
    public function getTopQueries(int $limit = 20, int $days = 30): array
    {
        $since = date('Y-m-d', strtotime("-{$days} days"));

        return $this->db->fetchAll(
            "SELECT query_normalized, SUM(search_count) as total,
                    AVG(result_count) as avg_results
             FROM analytics_search_daily
             WHERE date >= ?
             GROUP BY query_normalized
             ORDER BY total DESC
             LIMIT ?",
            [$since, $limit]
        );
    }

    /**
     * Bersihkan data lama sesuai konfigurasi retention_days.
     * Bisa dipanggil via cron job atau scheduled task.
     */
    public function cleanup(): void
    {
        $retentionDays = (int) ($this->config['retention_days'] ?? 90);
        $cutoff        = date('Y-m-d', strtotime("-{$retentionDays} days"));

        $this->db->query(
            "DELETE FROM analytics_page_views WHERE DATE(created_at) < ?",
            [$cutoff]
        );
        $this->db->query(
            "DELETE FROM analytics_search_daily WHERE date < ?",
            [$cutoff]
        );
        $this->db->query(
            "DELETE FROM analytics_reports_daily WHERE date < ?",
            [$cutoff]
        );
    }
}
