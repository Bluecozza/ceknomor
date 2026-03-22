<?php
/**
 * Modul Sharing
 * Menyediakan helper untuk tombol share ke media sosial
 * dan menghasilkan Open Graph meta tags yang sesuai.
 */

namespace Modules\Sharing;

use Core\ModuleManager;

class SharingModule
{
    /** @var array Konfigurasi modul */
    private array $config;

    /**
     * Dipanggil oleh ModuleManager saat sistem boot.
     */
    public function boot(array $config): void
    {
        $this->config = $config;

        $manager = ModuleManager::getInstance();

        // Hook saat halaman akan dirender — inject OG tags
        $manager->addHook('page.render', [$this, 'injectOgTags'], 10);
    }

    // ---------------------------------------------------------------
    // HOOK HANDLERS
    // ---------------------------------------------------------------

    /**
     * Inject Open Graph tags ke <head> HTML berdasarkan konteks halaman.
     *
     * @param array $context ['type' => 'report'|'home', 'data' => [...]]
     * @return string HTML meta tags yang akan di-inject
     */
    public function injectOgTags(array $context): string
    {
        $siteUrl  = rtrim(defined('APP_URL') ? APP_URL : 'https://cek.resource.my.id', '/');
        $siteName = defined('APP_NAME') ? APP_NAME : 'cek.resource.my.id';
        $ogImage  = $this->config['og_image_url'] ?: $siteUrl . '/public/assets/img/og-default.png';

        if ($context['type'] === 'report' && !empty($context['data'])) {
            $report  = $context['data'];
            $masked  = mask_value($report['reported_value'] ?? '', $report['category_slug'] ?? '');
            $risk    = $report['risk_level'] ?? 'unknown';
            $title   = "Laporan: {$masked} | {$siteName}";
            $desc    = ucfirst($risk) . " — {$report['title']}. " . truncate($report['description'] ?? '', 150);
            $url     = $siteUrl . '/laporan/' . $report['ulid'];
        } else {
            $title = "Cek Data Penipuan & Laporan Scam | {$siteName}";
            $desc  = "Cek apakah nomor telepon, rekening, atau akun dompet digital pernah dilaporkan bermasalah.";
            $url   = $siteUrl;
        }

        return implode("\n", [
            '<meta property="og:type"        content="website">',
            '<meta property="og:site_name"   content="' . e($siteName) . '">',
            '<meta property="og:title"       content="' . e($title) . '">',
            '<meta property="og:description" content="' . e($desc)  . '">',
            '<meta property="og:url"         content="' . e($url)   . '">',
            '<meta property="og:image"       content="' . e($ogImage) . '">',
            '<meta name="twitter:card"       content="summary_large_image">',
            '<meta name="twitter:title"      content="' . e($title) . '">',
            '<meta name="twitter:description" content="' . e($desc) . '">',
            '<meta name="twitter:image"      content="' . e($ogImage) . '">',
        ]);
    }

    // ---------------------------------------------------------------
    // PUBLIC HELPERS — Dipanggil dari view/template
    // ---------------------------------------------------------------

    /**
     * Generate HTML tombol-tombol share.
     *
     * @param string $url     URL yang akan dibagikan
     * @param string $text    Teks pesan default
     * @param string $classes CSS classes tambahan untuk button
     */
    public function renderShareButtons(string $url, string $text = '', string $classes = ''): string
    {
        $encodedUrl  = urlencode($url);
        $encodedText = urlencode($text ?: "Cek laporan di cek.resource.my.id: $url");
        $buttons     = [];

        if ($this->config['enable_whatsapp'] ?? true) {
            $waUrl     = "https://wa.me/?text={$encodedText}";
            $buttons[] = '<a href="' . $waUrl . '" target="_blank" rel="noopener" '
                . 'class="btn btn-success btn-sm ' . e($classes) . '">'
                . '<i class="bi bi-whatsapp me-1"></i>WhatsApp</a>';
        }

        if ($this->config['enable_telegram'] ?? true) {
            $tgUrl     = "https://t.me/share/url?url={$encodedUrl}&text={$encodedText}";
            $buttons[] = '<a href="' . $tgUrl . '" target="_blank" rel="noopener" '
                . 'class="btn btn-info btn-sm text-white ' . e($classes) . '">'
                . '<i class="bi bi-telegram me-1"></i>Telegram</a>';
        }

        if ($this->config['enable_twitter'] ?? true) {
            $twUrl     = "https://twitter.com/intent/tweet?text={$encodedText}";
            $buttons[] = '<a href="' . $twUrl . '" target="_blank" rel="noopener" '
                . 'class="btn btn-dark btn-sm ' . e($classes) . '">'
                . '<i class="bi bi-twitter-x me-1"></i>Twitter/X</a>';
        }

        if ($this->config['enable_facebook'] ?? true) {
            $fbUrl     = "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}";
            $buttons[] = '<a href="' . $fbUrl . '" target="_blank" rel="noopener" '
                . 'class="btn btn-primary btn-sm ' . e($classes) . '">'
                . '<i class="bi bi-facebook me-1"></i>Facebook</a>';
        }

        // Tombol salin link
        $buttons[] = '<button type="button" onclick="copyToClipboard(\'' . e($url) . '\')" '
            . 'class="btn btn-outline-secondary btn-sm ' . e($classes) . '">'
            . '<i class="bi bi-clipboard me-1"></i>Salin Link</button>';

        return '<div class="d-flex flex-wrap gap-2">' . implode('', $buttons) . '</div>';
    }

    /**
     * Buat URL share langsung dari template konfigurasi.
     */
    public function buildShareMessage(string $url, string $value = ''): string
    {
        $template = $this->config['share_message_template']
            ?? 'Cek data ini di cek.resource.my.id: {url}';

        return str_replace(
            ['{url}', '{value}'],
            [$url, $value],
            $template
        );
    }
}
