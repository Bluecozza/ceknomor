<?php require_once __DIR__ . '/layout/header.php'; ?>
<?php require_once __DIR__ . '/layout/sidebar.php'; ?>
<div class="content-wrapper p-3">

<section class="content-header">
  <h1>Moderasi Laporan</h1>
</section>

<section class="content">
<table>
<thead>
<tr>
  <th>Nomor</th>
  <th>Status</th>
  <th>Score</th>
  <th>Confidence</th>
  <th>Laporan</th>
  <th>Aksi</th>
</tr>
</thead>
<tbody id="list"></tbody>
</table>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function load(){
  $.get('/api/admin.number.list.php', res => {
    let html = '';
    res.numbers.forEach(n => {
      html += `
        <tr>
          <td>${n.phone_number}</td>
          <td><span class="badge ${n.label}">${n.label}</span></td>
          <td>${n.score}</td>
          <td>${n.confidence}%</td>
          <td>${n.report_count}</td>
          <td>
            <button onclick="recalc('${n.phone_number}')">
              Recalculate
            </button>
          </td>
        </tr>
      `;
    });
    $('#list').html(html);
  });
}

function recalc(number){
  fetch('/api/admin.number.recalc.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({number})
  }).then(()=>load());
}

load();
</script>

</section>

</div>
<?php require_once __DIR__ . '/layout/footer.php'; ?>