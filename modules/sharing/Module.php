<?php
/**
 * Modul Sharing
 * Menyediakan OG tags dan tombol share media sosial.
 */

class SharingModule
{
    /** @var array */
    private $config;

    public function boot(array $config): void
    {
        $this->config = $config;

        $manager = ModuleManager::getInstance();
        $manager->addHook('page.render', [$this, 'injectOgTags'], 10);
    }

    public function injectOgTags(array $context): string
    {
        $siteUrl  = defined('APP_URL') ? APP_URL : 'https://cek.resource.my.id';
        $siteName = defined('APP_NAME') ? APP_NAME : 'cek.resource.my.id';
        $ogImage  = !empty($this->config['og_image_url'])
            ? $this->config['og_image_url']
            : $siteUrl . '/public/assets/img/og-default.svg';

        if (!empty($context['data']) && $context['type'] === 'report') {
            $report  = $context['data'];
            $masked  = mask_value($report['reported_value'] ?? '');
            $risk    = $report['risk_level'] ?? 'unknown';
            $title   = "Laporan: {$masked} | {$siteName}";
            $desc    = ucfirst($risk) . " — " . truncate($report['title'] ?? '', 150);
            $url     = $siteUrl . '/laporan/' . ($report['ulid'] ?? '');
        } else {
            $title = "Cek Data Penipuan | {$siteName}";
            $desc  = "Cek nomor telepon, rekening, atau akun dompet digital yang pernah dilaporkan bermasalah.";
            $url   = $siteUrl;
        }

        return implode("\n", [
            '<meta property="og:type"         content="website">',
            '<meta property="og:site_name"    content="' . e($siteName) . '">',
            '<meta property="og:title"        content="' . e($title) . '">',
            '<meta property="og:description"  content="' . e($desc)  . '">',
            '<meta property="og:url"          content="' . e($url)   . '">',
            '<meta property="og:image"        content="' . e($ogImage) . '">',
            '<meta name="twitter:card"        content="summary_large_image">',
            '<meta name="twitter:title"       content="' . e($title) . '">',
            '<meta name="twitter:description" content="' . e($desc)  . '">',
            '<meta name="twitter:image"       content="' . e($ogImage) . '">',
        ]);
    }

    public function renderShareButtons(string $url, string $text = '', string $classes = ''): string
    {
        $encodedUrl  = urlencode($url);
        $encodedText = urlencode($text ?: "Cek laporan di cek.resource.my.id: $url");
        $buttons     = [];

        if ($this->config['enable_whatsapp'] ?? true) {
            $buttons[] = '<a href="https://wa.me/?text=' . $encodedText . '" target="_blank" rel="noopener" '
                . 'class="btn btn-success btn-sm ' . e($classes) . '"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>';
        }
        if ($this->config['enable_telegram'] ?? true) {
            $buttons[] = '<a href="https://t.me/share/url?url=' . $encodedUrl . '&text=' . $encodedText . '" target="_blank" rel="noopener" '
                . 'class="btn btn-info btn-sm text-white ' . e($classes) . '"><i class="bi bi-telegram me-1"></i>Telegram</a>';
        }
        if ($this->config['enable_twitter'] ?? true) {
            $buttons[] = '<a href="https://twitter.com/intent/tweet?text=' . $encodedText . '" target="_blank" rel="noopener" '
                . 'class="btn btn-dark btn-sm ' . e($classes) . '"><i class="bi bi-twitter-x me-1"></i>Twitter</a>';
        }

        $buttons[] = '<button type="button" onclick="navigator.clipboard.writeText(\'' . e($url) . '\')" '
            . 'class="btn btn-outline-secondary btn-sm ' . e($classes) . '"><i class="bi bi-clipboard me-1"></i>Salin</button>';

        return '<div class="d-flex flex-wrap gap-2">' . implode('', $buttons) . '</div>';
    }
}
