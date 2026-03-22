<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Halaman Tidak Ditemukan | cek.resource.my.id</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <style>
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
        }
        .brand span { color: #e63946; }
        .code-404 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 8rem;
            font-weight: 700;
            line-height: 1;
            background: linear-gradient(135deg, #e63946, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .btn-home {
            background: #e63946;
            border: none;
            color: #fff;
            padding: .65rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .15s;
        }
        .btn-home:hover { opacity: .85; color: #fff; }
        .btn-back {
            background: rgba(255,255,255,.07);
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: .65rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-back:hover { background: rgba(255,255,255,.12); color: #fff; }
    </style>
</head>
<body>
    <div class="text-center px-3">
        <!-- Brand -->
        <div class="brand mb-4">
            <a href="/" style="text-decoration:none;color:#e2e8f0">
                <span>cek</span>.resource.my.id
            </a>
        </div>

        <!-- 404 -->
        <div class="code-404">404</div>

        <h2 class="mt-2 mb-2" style="font-family:'Space Grotesk',sans-serif;font-weight:600">
            Halaman Tidak Ditemukan
        </h2>
        <p class="text-muted mb-4" style="max-width:400px;margin:0 auto">
            Halaman yang Anda cari tidak ada, mungkin sudah dipindahkan atau URL yang dimasukkan salah.
        </p>

        <!-- Actions -->
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/" class="btn-home">
                🔍 Cek Data
            </a>
            <a href="javascript:history.back()" class="btn-back">
                ← Kembali
            </a>
        </div>

        <!-- Divider -->
        <div class="mt-5 pt-3" style="border-top:1px solid #334155;color:#475569;font-size:.85rem">
            Atau coba akses:
            <a href="/report" class="text-muted mx-2">Buat Laporan</a>·
            <a href="/docs"   class="text-muted mx-2">Dokumentasi</a>·
            <a href="/admin"  class="text-muted mx-2">Admin</a>
        </div>
    </div>
</body>
</html>
