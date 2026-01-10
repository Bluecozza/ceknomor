<?php require_once __DIR__ . '/layout/header.php'; ?>
<?php require_once __DIR__ . '/layout/sidebar.php'; ?>

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
<script src="plugins/datatables/jquery.dataTables.min.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>

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
    processing:true,
    serverSide:true,
    pageLength:10,
    ajax:{
      url:'/api/admin.report.datatable.php',
      type:'GET',
      data:d=>{
        d.status = $('#filter').val();
      }
    },
    columns:[
      { data:'number' },
      { 
        data:'category',
        render:c=>{
          const map={
            penipuan:'danger',
            spam:'warning',
            safe:'success'
          };
          return `<span class="badge badge-${map[c]||'secondary'}">${c}</span>`;
        }
      },
      { data:'description' },
      { data:'created_at' },
      {
        data:'id',
        orderable:false,
        searchable:false,
        render:id=>`
          <button class="btn btn-sm btn-success"
            onclick="setStatus(${id},'approved')">✔</button>
          <button class="btn btn-sm btn-danger"
            onclick="setStatus(${id},'rejected')">✖</button>
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

$('#filter').on('change',()=>table.ajax.reload());
initTable('pending');
</script>


    </div>
  </div>
</section>

</div>


<?php require_once __DIR__ . '/layout/footer.php'; ?>
