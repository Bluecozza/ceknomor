<?php 
require_once __DIR__ . '/header.php'; 
require_once __DIR__ . '/core/db.php';
$raw = $_REQUEST['in'] ?? '';

?>
<div class="page">
<h2>Buat Laporan Baru</h2>

<form id="reportForm" enctype="multipart/form-data">

<!-- ===================== -->
<!-- INFORMASI UTAMA -->
<!-- ===================== -->

<label>Judul Laporan</label>
<input type="text" name="title" class="form-control" required maxlength="150">

<label>Deskripsi Singkat</label>
<textarea name="description" class="form-control" required></textarea>

<label>Keterangan Lengkap</label>
<textarea name="full_description" class="form-control" rows="6"></textarea>

<hr>

<!-- ===================== -->
<!-- NOMOR TELEPON -->
<!-- ===================== -->

<label>Nomor Telepon</label>
<div id="phoneList">
  <input type="text" name="phones[]" class="form-control mb-2" value="<?php echo $raw;?>" placeholder="62812xxxx" required>
</div>
<button type="button" onclick="addPhone()">+ Tambah Nomor</button>

<hr>

<!-- ===================== -->
<!-- KATEGORI -->
<!-- ===================== -->

<label>Kategori Laporan</label>
<div id="categories">
<?php
$db = db();
$cats = $db->query("SELECT id,name FROM categories ORDER BY id")->fetchAll();
foreach ($cats as $c) {
  echo "<label style='display:block'>
    <input type='checkbox' name='categories[]' value='{$c['id']}'> {$c['name']}
  </label>";
}
?>
</div>

<hr>

<!-- ===================== -->
<!-- REKENING -->
<!-- ===================== -->

<label>Rekening (Opsional)</label>
  <select id="bank_type" name="bank_type"  class="form-control">
 
<?php
$types = $db->query("SELECT DISTINCT bank_type FROM report_bank_accounts ORDER BY bank_type")->fetchAll();
foreach ($types as $c) {
  echo "<option value='{$c['bank_type']}'>{$c['bank_type']}</option>";
}
?>
</select>
<input type="text" name="bank_name" placeholder="Nama Bank" class="form-control mb-2">
<input type="text" name="account_number" placeholder="No Rekening" class="form-control">

<hr>

<!-- ===================== -->
<!-- INFORMASI TAMBAHAN -->
<!-- ===================== -->

<label>Link Kronologi</label>
<input type="url" name="chronology_link" class="form-control">

<label>Kerugian (Rp)</label>
<input type="number" name="loss_amount" class="form-control">

<hr>

<!-- ===================== -->
<!-- PELAPOR -->
<!-- ===================== -->

<label>Nama Pelapor</label>
<input type="text" name="reporter_name" class="form-control">

<label>Kontak Pelapor</label>
<input type="text" name="reporter_contact" class="form-control">

<hr>

<!-- ===================== -->
<!-- ATTACHMENT -->
<!-- ===================== -->

<label>Bukti (Foto, max 1MB)</label>
<input type="file" name="attachments[]" accept="image/*" multiple>

<hr>

<button type="submit">Kirim Laporan</button>

</form>
</div>

<script src="/assets/report.js"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
