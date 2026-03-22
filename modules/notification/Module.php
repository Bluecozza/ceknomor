<?php
/**
 * Modul Notification
 * Mengirim email notifikasi ke admin dan/atau pelapor
 * pada berbagai event penting (laporan baru, disetujui, ditolak).
 *
 * Mendukung:
 *  - PHP mail() bawaan (tanpa konfigurasi tambahan)
 *  - SMTP via socket (tanpa dependensi library eksternal)
 */

namespace Modules\Notification;

use Core\Database;
use Core\ModuleManager;

class NotificationModule
{
    private array $config;
    private Database $db;

    public function boot(array $config): void
    {
        $this->config = $config;
        $this->db     = Database::getInstance();

        $manager = ModuleManager::getInstance();

        // Hook saat laporan baru dibuat
        $manager->addHook('report.created', [$this, 'onReportCreated'], 20);

        // Hook saat laporan disetujui (dari moderate())
        $manager->addHook('report.approved', [$this, 'onReportApproved'], 20);

        // Hook saat laporan ditolak
        $manager->addHook('report.rejected', [$this, 'onReportRejected'], 20);
    }

    // ──────────────────────────────────────────────────────────
    // EVENT HANDLERS
    // ──────────────────────────────────────────────────────────

    /**
     * Dipanggil saat laporan baru masuk (status: pending).
     * Kirim notifikasi ke admin.
     */
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

        $subject = "[Laporan Baru] {$report['title']}";
        $body    = $this->buildNewReportEmail($report);

        $this->send($adminEmail, $subject, $body);
    }

    /**
     * Dipanggil saat laporan disetujui.
     * Kirim konfirmasi ke pelapor (opsional).
     */
    public function onReportApproved(array $data): void
    {
        if (!($this->config['notify_reporter_on_approved'] ?? false)) return;

        $this->notifyReporter($data['report_id'] ?? 0, 'approved');
    }

    /**
     * Dipanggil saat laporan ditolak.
     */
    public function onReportRejected(array $data): void
    {
        if (!($this->config['notify_reporter_on_approved'] ?? false)) return;

        $this->notifyReporter($data['report_id'] ?? 0, 'rejected');
    }

    // ──────────────────────────────────────────────────────────
    // EMAIL BUILDERS
    // ──────────────────────────────────────────────────────────

    private function buildNewReportEmail(array $report): string
    {
        $siteUrl   = defined('APP_URL') ? APP_URL : 'https://cek.resource.my.id';
        $adminUrl  = $siteUrl . '/admin';
        $masked    = $this->maskValue($report['reported_value'] ?? '', $report['category_id'] ?? 0);
        $catName   = $report['cat_name']  ?? 'Tidak diketahui';
        $typeName  = $report['type_name'] ?? 'Tidak diketahui';
        $created   = date('d M Y H:i', strtotime($report['created_at']));

        return $this->wrapHtml(
            "Laporan Baru Masuk",
            "
            <p>Ada laporan baru yang menunggu moderasi di platform <strong>cek.resource.my.id</strong>.</p>

            <table style='width:100%;border-collapse:collapse;margin:1rem 0'>
                <tr style='border-bottom:1px solid #334155'>
                    <td style='padding:.5rem;color:#94a3b8;width:35%'>Judul</td>
                    <td style='padding:.5rem'>" . e($report['title']) . "</td>
                </tr>
                <tr style='border-bottom:1px solid #334155'>
                    <td style='padding:.5rem;color:#94a3b8'>Data Dilaporkan</td>
                    <td style='padding:.5rem;font-weight:bold'>{$masked}</td>
                </tr>
                <tr style='border-bottom:1px solid #334155'>
                    <td style='padding:.5rem;color:#94a3b8'>Kategori</td>
                    <td style='padding:.5rem'>{$catName}</td>
                </tr>
                <tr style='border-bottom:1px solid #334155'>
                    <td style='padding:.5rem;color:#94a3b8'>Jenis Laporan</td>
                    <td style='padding:.5rem'>{$typeName}</td>
                </tr>
                <tr>
                    <td style='padding:.5rem;color:#94a3b8'>Diterima</td>
                    <td style='padding:.5rem'>{$created} WIB</td>
                </tr>
            </table>

            <p style='margin-top:1.5rem'>
                <a href='{$adminUrl}' style='background:#e63946;color:#fff;padding:.6rem 1.5rem;
                   border-radius:6px;text-decoration:none;font-weight:600'>
                    Buka Panel Admin
                </a>
            </p>
            "
        );
    }

    private function buildReporterEmail(array $report, string $status): string
    {
        $siteUrl  = defined('APP_URL') ? APP_URL : 'https://cek.resource.my.id';
        $detailUrl = $siteUrl . '/laporan/' . $report['ulid'];

        if ($status === 'approved') {
            $heading = "Laporan Anda Telah Disetujui ✅";
            $message = "Laporan yang Anda kirimkan telah diverifikasi dan dipublikasikan. 
                        Terima kasih telah membantu melindungi orang lain dari penipuan.";
            $cta     = "<a href='{$detailUrl}' style='background:#4ade80;color:#0f172a;padding:.6rem 1.5rem;
                        border-radius:6px;text-decoration:none;font-weight:600'>Lihat Laporan</a>";
        } else {
            $heading = "Update Status Laporan Anda";
            $message = "Laporan yang Anda kirimkan tidak dapat dipublikasikan saat ini. 
                        Hal ini dapat terjadi jika data tidak cukup lengkap atau tidak sesuai 
                        dengan panduan platform kami.";
            $cta     = "<a href='{$siteUrl}/report' style='background:#3b82f6;color:#fff;padding:.6rem 1.5rem;
                        border-radius:6px;text-decoration:none;font-weight:600'>Kirim Laporan Baru</a>";
        }

        $note = !empty($report['admin_note'])
            ? "<p><strong>Catatan Admin:</strong> " . e($report['admin_note']) . "</p>"
            : '';

        return $this->wrapHtml($heading, "
            <p>{$message}</p>
            {$note}
            <p style='margin-top:1.5rem'>{$cta}</p>
        ");
    }

    private function wrapHtml(string $heading, string $content): string
    {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0f172a;font-family:Inter,Arial,sans-serif;color:#e2e8f0">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:2rem 1rem">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#1e293b;border-radius:12px;overflow:hidden;border:1px solid #334155">
        <!-- Header -->
        <tr>
          <td style="background:#0f172a;padding:1.25rem 2rem;border-bottom:1px solid #334155">
            <span style="font-size:1.1rem;font-weight:700;color:#fff">
              <span style="color:#e63946">cek</span>.resource.my.id
            </span>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:2rem">
            <h2 style="margin:0 0 1rem;font-size:1.25rem;color:#f1f5f9">{$heading}</h2>
            {$content}
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding:1rem 2rem;border-top:1px solid #334155;font-size:.8rem;color:#64748b">
            © {$year} cek.resource.my.id &mdash; Platform Laporan Penipuan Indonesia.<br>
            Email ini dikirim otomatis, mohon tidak membalas pesan ini.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    // ──────────────────────────────────────────────────────────
    // SEND ENGINE
    // ──────────────────────────────────────────────────────────

    /**
     * Kirim email. Menggunakan SMTP jika dikonfigurasi, fallback ke mail().
     *
     * @param string $to      Alamat email tujuan
     * @param string $subject Subjek email
     * @param string $body    Body HTML
     */
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
        } catch (\Throwable $e) {
            error_log("[NotificationModule] Gagal kirim email ke {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kirim via PHP mail() bawaan.
     */
    private function sendMail(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$fromName} <{$from}>\r\n";
        $headers .= "X-Mailer: cek.resource.my.id\r\n";

        return mail($to, $subject, $body, $headers);
    }

    /**
     * Kirim via SMTP (raw socket, tanpa library eksternal).
     * Mendukung STARTTLS (port 587) dan SSL (port 465).
     */
    private function sendSmtp(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $host       = $this->config['smtp_host']       ?? '';
        $port       = (int) ($this->config['smtp_port']       ?? 587);
        $user       = $this->config['smtp_user']       ?? '';
        $pass       = $this->config['smtp_pass']       ?? '';
        $encryption = strtolower($this->config['smtp_encryption'] ?? 'tls');

        // Buat koneksi socket
        $addr   = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
        $socket = fsockopen($addr, $port, $errno, $errstr, 15);

        if (!$socket) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }

        $recv = function () use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if ($line[3] === ' ') break; // akhir response multi-line
            }
            return $response;
        };

        $send = function (string $cmd) use ($socket, $recv): string {
            fwrite($socket, $cmd . "\r\n");
            return $recv();
        };

        // Handshake
        $recv(); // 220 greeting
        $send("EHLO " . gethostname());

        // STARTTLS untuk port 587
        if ($encryption === 'tls') {
            $send("STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . gethostname());
        }

        // Auth
        if ($user && $pass) {
            $send("AUTH LOGIN");
            $send(base64_encode($user));
            $send(base64_encode($pass));
        }

        // Envelope
        $send("MAIL FROM:<{$from}>");
        $send("RCPT TO:<{$to}>");
        $send("DATA");

        // Build message
        $boundary = md5(uniqid());
        $headers  = implode("\r\n", [
            "From: {$fromName} <{$from}>",
            "To: {$to}",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "X-Mailer: cek.resource.my.id",
        ]);

        $encodedBody = chunk_split(base64_encode($body));
        fwrite($socket, $headers . "\r\n\r\n" . $encodedBody . "\r\n.\r\n");
        $recv();

        $send("QUIT");
        fclose($socket);

        return true;
    }

    // ──────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────

    private function notifyReporter(int $reportId, string $status): void
    {
        $report = $this->db->fetchOne(
            "SELECT r.*, rp.contact, rp.contact_type
             FROM reports r
             LEFT JOIN reporters rp ON rp.id = r.reporter_id
             WHERE r.id = ? AND r.is_anonymous = 0",
            [$reportId]
        );
        if (!$report) return;

        // Hanya kirim ke email
        if (($report['contact_type'] ?? '') !== 'email') return;
        $email = $report['contact'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;

        $statusLabel = $status === 'approved' ? 'Disetujui' : 'Tidak Dapat Dipublikasikan';
        $subject     = "[cek.resource.my.id] Laporan Anda {$statusLabel}";
        $body        = $this->buildReporterEmail($report, $status);

        $this->send($email, $subject, $body);
    }

    private function getAdminEmail(): string
    {
        // Prioritas: config modul → settings tabel → kosong
        if (!empty($this->config['admin_email'])) {
            return $this->config['admin_email'];
        }

        $row = $this->db->fetchOne(
            "SELECT value FROM settings WHERE `key` = 'site_email'"
        );
        return $row['value'] ?? '';
    }

    private function maskValue(string $value, int $categoryId): string
    {
        if (strlen($value) <= 4) return $value;
        return substr($value, 0, 3) . str_repeat('*', max(3, strlen($value) - 5)) . substr($value, -2);
    }
}
