<?php require_once __DIR__ . '/_header.php'; ?>
<?php require_once __DIR__ . '/_sidebar.php'; ?>

<div class="content-wrapper p-3">

<section class="content-header">
  <h1>Moderasi Laporan</h1>
</section>

<section class="content">
  <div class="card">
    <div class="card-body">

      <select id="filter" class="form-control mb-3">
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>

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
<!-- DataTables -->
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
        <button class="btn btn-danger btn-sm" onclick="setStatus(${id},'rejected')">Reject</button>
      `
    }
  ]
});

}

function setStatus(id,status){
  fetch('/api/admin.report.update.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id,status})
  }).then(()=>table.ajax.reload(null,false));
}

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


<?php require_once __DIR__ . '/_footer.php'; ?>
