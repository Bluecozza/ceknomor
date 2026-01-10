<?php
require_once __DIR__ . '/../core/admin_auth.php';
if (!admin_logged_in()) {
    header("Location: /admin/login.php");
    exit;
}
?><!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width">
<title>Admin – Analytics</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>

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

</body>
</html>
