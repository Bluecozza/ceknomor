<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="./assets/style.css">
</head>

<body>

<div class="center">
  <h2>Database Reputasi Nomor Telepon</h2>

  <form id="searchForm">
    <input id="query" placeholder="Masukkan nomor / teks apapun" />
    <button>Cari Data</button>
  </form>

  <div id="result"></div>
  
  <div id="modal" class="modal hidden">
  <div class="modal-bg"></div>

  <div class="modal-box">

    <h3>Buat Laporan Nomor</h3>
    <p id="modalNumber" class="sub"></p>

    <form id="reportForm">

      <label>Kategori</label>
      <select id="category">
        <option value="penipuan">Penipuan</option>
        <option value="spam">Spam / Iklan</option>
        <option value="safe">Aman / Valid</option>
        <option value="unknown">Tidak yakin</option>
      </select>

      <label>Deskripsi Laporan</label>
      <textarea id="description" rows="4" placeholder="Tuliskan kronologi singkat..."></textarea>

      <div class="modal-actions">
        <button type="button" id="cancelModal" class="btn-outline">Batal</button>
        <button type="submit" class="btn-primary">Kirim Laporan</button>
      </div>

    </form>

  </div>
</div>

  
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="./assets/app.js"></script>

</body>
</html>
