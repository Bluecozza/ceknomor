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
        <option value="fitnah">Fitnah</option>
        <option value="rasis">Rasis</option>
        <option value="pencemaran">Pencemaran Nama Baik</option>
        <option value="kekerasan">Kejahatan Kekerasan</option>
        <option value="perdagangan">Perdagangan Palsu</option>
        <option value="narkoba">Narkotika</option>
        <option value="pencurian">Pencurian</option>
        <option value="perampokan">Perampokan/Begal dengan senjata</option>
        <option value="spam">Spam / Iklan</option>
        <option value="bully">Bullying / Perundungan</option>
        <option value="unknown">Lainnya</option>
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
