<?php require_once __DIR__ . '/_header.php'; ?>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="content-wrapper p-3">

<h2>Analytics & Insight</h2>

<div id="overview"></div>
<canvas id="trend" height="120"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script>
$.get('/api/admin.analytics.overview.php', res => {
  $('#overview').html(`
    <p>Total laporan: <b>${res.stats.total_reports}</b></p>
    <p>Pending: <b>${res.stats.pending_reports}</b></p>
    <p>Reputasi:
      Safe: ${res.stats.reputation_dist.safe||0},
      Suspicious: ${res.stats.reputation_dist.suspicious||0},
      High risk: ${res.stats.reputation_dist.high_risk||0}
    </p>
  `);
});

$.get('/api/admin.analytics.trend.php', res => {
  new Chart(document.getElementById('trend'),{
    type:'line',
    data:{
      labels:res.data.map(x=>x.d),
      datasets:[{
        label:'Laporan per hari',
        data:res.data.map(x=>x.c),
        borderColor:'#2563eb',
        fill:false
      }]
    }
  });
});
</script>

</div>
<?php require_once __DIR__ . '/_footer.php'; ?>
