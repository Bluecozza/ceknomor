<?php 
require_once __DIR__ . '/header.php'; 
require_once __DIR__ . '/core/db.php';
$raw = $_REQUEST['in'] ?? '';

?>
            <!-- general form elements disabled -->
            <div class="card ">
              <div class="card-header">
                <h3 class="card-title">Buat Laporan Baru</h3>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <form id="reportForm" enctype="multipart/form-data">
                  <div class="row">
					<!-- ===================== -->
					<!-- NAMA  -->
					<!-- ===================== -->
                    <div class="col-sm-6">
                      <!-- text input -->
                      <div class="form-group">
                        <label>Nama Pemilik Nomor</label>
                        <input type="text" name="title" class="form-control " placeholder="Nama dari pemilik nomor yang anda tahu..">
                      </div>
                    </div>
					
					<!-- ===================== -->
					<!-- NOMOR TELEPON -->
					<!-- ===================== -->
                    <div class="col-sm-6">
                      <div class="form-group" id="phoneList">
                        <label>Nomor Telepon</label>
                        <input type="text" name="phones[]" class="form-control mb-2" value="<?php echo $raw;?>" placeholder="62812xxxx" required>
                      </div>
					  <button type="button" class="btn btn-info" onclick="addPhone()">+ Tambah Nomor</button>
                    </div>
                  </div>
<hr>				  
					<!-- ===================== -->
					<!-- INFORMASI UTAMA -->
					<!-- ===================== -->
                  <div class="row">
                    <div class="col-sm-6">
                      <!-- textarea -->
                      <div class="form-group">
                        <label>Keterangan Singkat</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Keterangan Singkat"></textarea>
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <div class="form-group">
                        <label>Keterangan Lengkap</label>
                        <textarea name="full_description" class="form-control" rows="5" placeholder="Keterangan Selengkapnya..."></textarea>
                      </div>
                    </div>
                  </div>
					<!-- ===================== -->
					<!-- KATEGORI -->
					<!-- ===================== -->
                      <div class="form-group"><label>Kategori Laporan</label>
					<?php
					$db = db();
					$cats = $db->query("SELECT id,name FROM categories ORDER BY id")->fetchAll();
					foreach ($cats as $c) {
					  echo "<div class='form-check'>
					  <label class='form-check-label'>
						<input class='form-check-input' type='checkbox' name='categories[]' value='{$c['id']}' /> {$c['name']}
					  </label>
					  </div>";
					}
					?>              
                      </div>
<hr>
					<!-- ===================== -->
					<!-- REKENING -->
					<!-- ===================== -->
                  <div class="row">
                    <div class="col-sm-6">
                      <!-- text input -->
                      <div class="form-group">
					  <label>Jenis Rekening</label>
							<select id="bank_type" name="bank_type"  class="form-control">
							<?php
							$types = $db->query("SELECT DISTINCT bank_type FROM report_bank_accounts ORDER BY bank_type")->fetchAll();
							foreach ($types as $c) {
							  echo "<option value='{$c['bank_type']}'>{$c['bank_type']}</option>";
							}
							?>
							</select>
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <!-- text input -->
                      <div class="form-group">
                        <label>Nama di Rekening</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="Nama pemilik Rekening">
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <!-- text input -->
                      <div class="form-group">
                        <label>Nomor Rekening</label>
                        <input type="text" name="account_number" class="form-control" placeholder="Nomor Rekening">
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <!-- text input -->
                      <div class="form-group">
                        <label>Nomor Rekening</label>
                        <input type="text" name="account_number" class="form-control" placeholder="Nomor Rekening">
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <!-- text input -->
                      <div class="form-group">
                        <label>Kerugian (Rp)</label>
                        <input type="number" name="loss_amount" class="form-control">
                      </div>
                    </div>
                  </div>					
<hr>
					<!-- ===================== -->
					<!-- INFORMASI TAMBAHAN -->
					<!-- ===================== -->
                  <div class="form-group">
                    <label class="col-form-label" for="inputSuccess"><i class="fas fa-check"></i> Link Kasus</label>
                    <input type="url" name="chronology_link" class="form-control is-valid" id="inputSuccess" placeholder="Enter ...">
                  </div>
<hr>
					<!-- ===================== -->
					<!-- PELAPOR -->
					<!-- ===================== -->
                  <div class="row">
                    <div class="col-sm-6">
                      <!-- text input -->
                      <div class="form-group">
                        <label>Nama Pelapor</label>
                        <input type="text" name="reporter_name" class="form-control " placeholder="Nama Anda">
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <div class="form-group" id="phoneList">
                        <label>Kontak Pelapor</label>
                        <input type="text" name="reporter_contact" class="form-control mb-2" placeholder="Nomor Anda" required>
                      </div>
                    </div>
                  </div>
<hr>
					<!-- ===================== -->
					<!-- ATTACHMENT -->
					<!-- ===================== -->
                  <div class="form-group">
                    <label for="datafile">Bukti (Foto, max 1MB)</label>
                    <div class="input-group">
                      <div class="custom-file">
                        <input type="file" class="custom-file-input" name="attachments[]" id="datafile" accept="image/*" multiple>
                        <label class="custom-file-label" for="datafile">Choose file</label>
                      </div>
                      <div class="input-group-append">
                        <span class="input-group-text">Upload</span>
                      </div>
                    </div>
                  </div>
                <div class="card-footer">
                  <button type="submit" class="btn btn-primary">Kirim Laporan</button>
                </div>
                </form>
              </div>
            </div>

<script src="/assets/report.js"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
