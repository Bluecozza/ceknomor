<?php
/**
 * ./modules/notification/Module.php
 * Modul Notifikasi — email ke admin saat laporan baru
 */

class NotificationModule
{
    /** @var array */
    private $config;

    /** @var Database */
    private $db;

    public function boot(array $config): void
    {
        $this->config = $config;
        $this->db     = Database::getInstance();
        ModuleManager::getInstance()->addHook('report.created', [$this, 'onReportCreated'], 20);
    }

    public function onReportCreated(array $data): void
    {
        if (empty($this->config['notify_admin_on_new'])) return;
        $email = $this->getAdminEmail();
        if (!$email) return;

        try {
            $report = $this->db->fetchOne(
                "SELECT r.title, r.reported_value, c.name AS cat_name, rt.name AS type_name
                 FROM reports r LEFT JOIN categories c ON c.id=r.category_id LEFT JOIN report_types rt ON rt.id=r.report_type_id
                 WHERE r.id=?", [$data['report_id']??0]
            );
            if (!$report) return;

            $subject = "[Laporan Baru] " . ($report['title'] ?? '');
            $body    = "Ada laporan baru masuk:\n\nData: " . mask_value($report['reported_value']??'')
                     . "\nKategori: " . ($report['cat_name']??'')
                     . "\nJenis: " . ($report['type_name']??'')
                     . "\n\nBuka admin panel: " . (defined('APP_URL') ? APP_URL : '') . "/admin";

            $headers = "From: " . ($this->config['from_name']??'cek.resource.my.id') . " <" . ($this->config['from_email']??'noreply@cek.resource.my.id') . ">\r\n";
            @mail($email, $subject, $body, $headers);
        } catch (Exception $e) {
            error_log('NotificationModule error: ' . $e->getMessage());
        }
    }

    private function getAdminEmail(): string
    {
        if (!empty($this->config['admin_email'])) return $this->config['admin_email'];
        try {
            $row = $this->db->fetchOne("SELECT value FROM settings WHERE `key`='site_email'");
            return $row['value'] ?? '';
        } catch (Exception $e) { return ''; }
    }
}
