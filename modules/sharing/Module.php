<?php
/**
 * ./modules/sharing/Module.php
 * Modul Sharing — OG tags dan tombol share sosmed
 */

class SharingModule
{
    /** @var array */
    private $config;

    public function boot(array $config): void
    {
        $this->config = $config;
    }

    public function getOgTags(array $context = []): string
    {
        $siteUrl  = defined('APP_URL') ? APP_URL : '';
        $siteName = defined('APP_NAME') ? APP_NAME : 'cek.resource.my.id';
        $ogImage  = !empty($this->config['og_image_url']) ? $this->config['og_image_url'] : $siteUrl . '/public/assets/img/og-default.svg';
        $title    = "Cek Data Penipuan | {$siteName}";
        $desc     = "Cek nomor telepon, rekening, atau akun dompet digital yang pernah dilaporkan bermasalah.";
        $url      = $siteUrl;

        if (!empty($context['report'])) {
            $r     = $context['report'];
            $title = "Laporan: " . mask_value($r['reported_value']??'') . " | {$siteName}";
            $desc  = ucfirst($r['risk_level']??'unknown') . " — " . truncate($r['title']??'', 150);
            $url   = $siteUrl . '/laporan/' . ($r['ulid']??'');
        }

        return '<meta property="og:title" content="' . e($title) . '">' . "\n"
             . '<meta property="og:description" content="' . e($desc) . '">' . "\n"
             . '<meta property="og:url" content="' . e($url) . '">' . "\n"
             . '<meta property="og:image" content="' . e($ogImage) . '">' . "\n"
             . '<meta name="twitter:card" content="summary_large_image">';
    }
}
