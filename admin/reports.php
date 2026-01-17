<?php require_once __DIR__ . '/_header.php'; ?>
<?php require_once __DIR__ . '/_sidebar.php'; ?>

<div class="content-wrapper p-3">

<section class="content-header">
  <h1>Moderasi Laporan</h1>
</section>

<section class="content">
  <div class="card">
    <div class="card-body">


<div class="mb-3">
  <a href="/api/admin.report.export.php" class="btn btn-info">
    Export CSV
  </a>

  <input type="file" id="importFile" accept=".csv" hidden>
  <button class="btn btn-success" onclick="$('#importFile').click()">
    Import CSV
  </button>
        <select id="filter">
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
</div>

		<table id="reportsTable" class="table table-bordered table-striped">
		  <thead>
			<tr>
			  <th>Nomor</th>
			  <th>Kategori</th>
			  <th>Deskripsi</th>
			  <th>Tanggal</th>
			  <th>Aksi</th>
			</tr>
		  </thead>
		  <tbody></tbody>
		</table>




<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../assets/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>


<script>
let table;

function initTable(status){
	if(typeof $.fn.DataTable === 'undefined'){
    console.error('DataTables not loaded');
    return;
  }
  if(table){
    table.ajax.reload();
    return;
  }

  table = $('#reportsTable').DataTable({
  processing: true,
  serverSide: true,
  ajax: {
    url: '/api/admin.report.datatable.php',
    data: function (d) {
      d.status = $('#filter').val();
    }
  },
  columns: [
    {
      data: 'numbers',
      render: function (data) {
        return data
          .split(',')
          .map(n => `<span class="badge badge-primary mr-1">${n}</span>`)
          .join(' ');
      }
    },
    {
      data: 'category',
      render: data => `<span class="badge badge-danger">${data}</span>`
    },
    { data: 'description' },
    { data: 'created_at' },
    {
      data: 'id',
      orderable: false,
      searchable: false,
      render: id => `
        <button class="btn btn-success btn-sm" onclick="setStatus(${id},'approved')">Approve</button>
		<button class="btn btn-warning btn-sm" onclick="editReport(${id})">Edit</button>
		<button class="btn btn-danger btn-sm" onclick="deleteReport(${id})">Delete</button>
      `
    }
  ]
});

}

function editReport(id){
  fetch('/api/admin.report.get.php?id='+id)
    .then(r=>r.json())
    .then(res=>{
      if(res.status!=='ok') return alert('Gagal load');

      const r = res.report;
      $('#edit_id').val(r.id);
      $('#edit_title').val(r.title);
      $('#edit_desc').val(r.description);
      $('#edit_phones').val(r.phones.join(', '));

      $('#edit_categories option').prop('selected',false);
      r.categories.forEach(c=>{
        $('#edit_categories option[value="'+c.id+'"]').prop('selected',true);
      });

      $('#editModal').modal('show');
    });
}

function saveEdit(){
  const data = {
    id: $('#edit_id').val(),
    title: $('#edit_title').val(),
    description: $('#edit_desc').val(),
    phones: $('#edit_phones').val().split(',').map(s=>s.trim()),
    categories: $('#edit_categories').val() || []
  };

  fetch('/api/admin.report.edit.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(data)
  })
  .then(r=>r.json())
  .then(res=>{
    if(res.status==='ok'){
      $('#editModal').modal('hide');
      table.ajax.reload(null,false);
    } else {
      alert('Gagal menyimpan');
    }
  });
}


function setStatus(id,status){
  fetch('/api/admin.report.update.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id,status})
  }).then(()=>table.ajax.reload(null,false));
}

function deleteReport(id){
  if(!confirm('Hapus laporan ini?')) return;

  fetch('/api/admin.report.delete.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id})
  }).then(r=>r.json())
    .then(()=>table.ajax.reload(null,false));
}

$('#importFile').on('change', function(){
  const file = this.files[0];
  if(!file) return;

  const fd = new FormData();
  fd.append('file', file);

  fetch('/api/admin.report.import.php',{
    method:'POST',
    body:fd
  })
  .then(r=>r.json())
  .then(res=>{
    if(res.status==='ok'){
      alert('Import berhasil');
      table.ajax.reload();
    } else {
      alert('Import gagal');
    }
  });
});


$('#filter').on('change', function () {
  if (table) {
    table.ajax.reload();
  }
});
initTable('pending');
</script>


    </div>
  </div>
</section>

</div>
<div class="modal fade" id="editModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5>Edit Laporan</h5>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="edit_id">

        <div class="form-group">
          <label>Judul</label>
          <input type="text" id="edit_title" class="form-control">
        </div>

        <div class="form-group">
          <label>Deskripsi</label>
          <textarea id="edit_desc" class="form-control" rows="4"></textarea>
        </div>

        <div class="form-group">
          <label>Nomor (pisahkan dengan koma)</label>
          <input type="text" id="edit_phones" class="form-control">
        </div>

        <div class="form-group">
          <label>Kategori</label>
          <select id="edit_categories" class="form-control" multiple>
            <?php
              $cats = db()->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();
              foreach($cats as $c){
                echo "<option value='{$c['id']}'>{$c['name']}</option>";
              }
            ?>
          </select>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button class="btn btn-primary" onclick="saveEdit()">Simpan</button>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
