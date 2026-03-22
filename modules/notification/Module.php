<?php
/**
 * Modul Notification
 * Kirim email notifikasi ke admin dan pelapor.
 * Mendukung PHP mail() dan SMTP raw socket.
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

        $manager = ModuleManager::getInstance();
        $manager->addHook('report.created',  [$this, 'onReportCreated'],  20);
        $manager->addHook('report.approved', [$this, 'onReportApproved'], 20);
        $manager->addHook('report.rejected', [$this, 'onReportRejected'], 20);
    }

    public function onReportCreated(array $data): void
    {
        if (!($this->config['notify_admin_on_new'] ?? true)) return;

        $adminEmail = $this->getAdminEmail();
        if (!$adminEmail) return;

        $report = $this->db->fetchOne(
            "SELECT r.*, c.name AS cat_name, rt.name AS type_name
             FROM reports r
             LEFT JOIN categories   c  ON c.id  = r.category_id
             LEFT JOIN report_types rt ON rt.id = r.report_type_id
             WHERE r.id = ?",
            [$data['report_id'] ?? 0]
        );
        if (!$report) return;

        $this->send($adminEmail, "[Laporan Baru] " . ($report['title'] ?? ''), $this->buildNewReportEmail($report));
    }

    public function onReportApproved(array $data): void
    {
        if (!($this->config['notify_reporter_on_approved'] ?? false)) return;
        $this->notifyReporter($data['report_id'] ?? 0, 'approved');
    }

    public function onReportRejected(array $data): void
    {
        if (!($this->config['notify_reporter_on_approved'] ?? false)) return;
        $this->notifyReporter($data['report_id'] ?? 0, 'rejected');
    }

    private function buildNewReportEmail(array $report): string
    {
        $siteUrl  = defined('APP_URL') ? APP_URL : 'https://cek.resource.my.id';
        $masked   = mask_value($report['reported_value'] ?? '');
        $created  = date('d M Y H:i', strtotime($report['created_at'] ?? 'now'));

        return $this->wrapHtml("Laporan Baru Masuk", "
            <p>Ada laporan baru yang menunggu moderasi.</p>
            <table style='width:100%;border-collapse:collapse'>
                <tr><td style='padding:.5rem;color:#94a3b8;width:35%'>Data Dilaporkan</td><td style='padding:.5rem;font-weight:bold'>{$masked}</td></tr>
                <tr><td style='padding:.5rem;color:#94a3b8'>Kategori</td><td style='padding:.5rem'>" . e($report['cat_name'] ?? '—') . "</td></tr>
                <tr><td style='padding:.5rem;color:#94a3b8'>Jenis</td><td style='padding:.5rem'>" . e($report['type_name'] ?? '—') . "</td></tr>
                <tr><td style='padding:.5rem;color:#94a3b8'>Waktu</td><td style='padding:.5rem'>{$created} WIB</td></tr>
            </table>
            <p style='margin-top:1.5rem'>
                <a href='{$siteUrl}/admin' style='background:#e63946;color:#fff;padding:.6rem 1.5rem;border-radius:6px;text-decoration:none;font-weight:600'>Buka Panel Admin</a>
            </p>
        ");
    }

    private function wrapHtml(string $heading, string $content): string
    {
        $year = date('Y');
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>"
            . "<body style='margin:0;padding:2rem;background:#0f172a;font-family:Arial,sans-serif;color:#e2e8f0'>"
            . "<div style='max-width:600px;margin:0 auto;background:#1e293b;border-radius:12px;overflow:hidden'>"
            . "<div style='background:#0f172a;padding:1.25rem 2rem;border-bottom:1px solid #334155'>"
            . "<span style='font-size:1.1rem;font-weight:700;color:#fff'><span style='color:#e63946'>cek</span>.resource.my.id</span></div>"
            . "<div style='padding:2rem'><h2 style='margin:0 0 1rem;color:#f1f5f9'>{$heading}</h2>{$content}</div>"
            . "<div style='padding:1rem 2rem;border-top:1px solid #334155;font-size:.8rem;color:#64748b'>"
            . "&copy; {$year} cek.resource.my.id &mdash; Email otomatis, jangan dibalas.</div>"
            . "</div></body></html>";
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $from     = $this->config['from_email'] ?? 'noreply@cek.resource.my.id';
        $fromName = $this->config['from_name']  ?? 'cek.resource.my.id';
        $smtpHost = $this->config['smtp_host']  ?? '';

        try {
            if ($smtpHost) {
                return $this->sendSmtp($to, $subject, $body, $from, $fromName);
            }
            return $this->sendMail($to, $subject, $body, $from, $fromName);
        } catch (Exception $e) {
            error_log("[NotificationModule] Gagal kirim email: " . $e->getMessage());
            return false;
        }
    }

    private function sendMail(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$from}>\r\n";
        return mail($to, $subject, $body, $headers);
    }

    private function sendSmtp(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $host       = $this->config['smtp_host']       ?? '';
        $port       = (int) ($this->config['smtp_port']       ?? 587);
        $user       = $this->config['smtp_user']       ?? '';
        $pass       = $this->config['smtp_pass']       ?? '';
        $encryption = strtolower($this->config['smtp_encryption'] ?? 'tls');

        $addr   = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = fsockopen($addr, $port, $errno, $errstr, 15);
        if (!$socket) throw new Exception("SMTP connect failed: {$errstr}");

        $recv = function() use ($socket) {
            $r = '';
            while ($line = fgets($socket, 515)) {
                $r .= $line;
                if ($line[3] === ' ') break;
            }
            return $r;
        };
        $send = function(string $cmd) use ($socket, $recv) {
            fwrite($socket, $cmd . "\r\n");
            return $recv();
        };

        $recv();
        $send("EHLO " . gethostname());
        if ($encryption === 'tls') {
            $send("STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . gethostname());
        }
        if ($user && $pass) {
            $send("AUTH LOGIN");
            $send(base64_encode($user));
            $send(base64_encode($pass));
        }
        $send("MAIL FROM:<{$from}>");
        $send("RCPT TO:<{$to}>");
        $send("DATA");

        $headers = implode("\r\n", [
            "From: {$fromName} <{$from}>",
            "To: {$to}",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
        ]);
        fwrite($socket, $headers . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.\r\n");
        $recv();
        $send("QUIT");
        fclose($socket);
        return true;
    }

    private function notifyReporter(int $reportId, string $status): void
    {
        $report = $this->db->fetchOne(
            "SELECT r.*, rp.contact, rp.contact_type
             FROM reports r LEFT JOIN reporters rp ON rp.id = r.reporter_id
             WHERE r.id = ? AND r.is_anonymous = 0",
            [$reportId]
        );
        if (!$report || ($report['contact_type'] ?? '') !== 'email') return;
        if (!filter_var($report['contact'] ?? '', FILTER_VALIDATE_EMAIL)) return;

        $label   = $status === 'approved' ? 'Disetujui' : 'Tidak Dapat Dipublikasikan';
        $subject = "[cek.resource.my.id] Laporan Anda {$label}";
        $body    = $this->wrapHtml(
            "Update Status Laporan Anda",
            $status === 'approved'
                ? "<p>Laporan Anda telah diverifikasi dan dipublikasikan. Terima kasih!</p>"
                : "<p>Laporan Anda tidak dapat dipublikasikan saat ini. Pastikan data lengkap dan sesuai panduan.</p>"
        );
        $this->send($report['contact'], $subject, $body);
    }

    private function getAdminEmail(): string
    {
        if (!empty($this->config['admin_email'])) return $this->config['admin_email'];
        $row = $this->db->fetchOne("SELECT value FROM settings WHERE `key` = 'site_email'");
        return $row['value'] ?? '';
    }
}
