$('#searchForm').on('submit', function(e){
  e.preventDefault();

  const q = $('#query').val().trim();
  if(!q) return;

  $('#result').html("Memproses...");

  $.get('/api.php', { action:'search', query:q }, function(res){
    renderResult(res);
  }).fail(function(){
    $('#result').html("Terjadi kesalahan. Coba lagi.");
  });
});

  ////////////
  function renderResult(res) {
  const box = document.getElementById("result");

  if (!res.exists) {
    box.innerHTML = `
      <p>Belum ada data untuk nomor <b>${res.number}</b>.</p>
      <button onclick="openReportModal('${res.number}')">Laporkan nomor ini</button>
    `;
    return;
  }

  if (res.total === 0) {
    box.innerHTML = `
      <p>Nomor <b>${res.number}</b> sudah ada dalam database kami.</p>
      <p>Saat ini belum ada laporan positif maupun negatif.</p>
      <button onclick="openReportModal('${res.number}')">Buat Laporan Baru</button>
    `;
    return;
  }

  let html = `<p><b>${res.total}</b> total laporan ditemukan:</p><ul>`;

  if (res.summary.approved > 0)
    html += `<li>${res.summary.approved} Laporan yang <b>Terbukti</b></li>`;

  if (res.summary.pending > 0)
    html += `<li>${res.summary.pending} Laporan dalam <b>Proses Pemeriksaan</b></li>`;

  if (res.summary.rejected > 0)
    html += `<li>${res.summary.rejected} Laporan dengan <b>Bukti yang sangat lemah</b></li>`;

  html += `</ul>
    <a href="/list.php?number=${res.number}">
      Lihat detail seluruh laporan
    </a>
	<p><button onclick="openReportModal('${res.number}')">Buat Laporan Baru</button></p>
  `;

  box.innerHTML = html;
}


  ////////////







function openReportModal(number){
  $('#modalNumber').text(number);
  $('#description').val('');
  $('#category').val('penipuan');
  $('#modal').removeClass('hidden');

  // simpan nomor ke state sementara
  $('#modal').data('number', number);
}

$('#cancelModal, .modal-bg').on('click', function(){
  $('#modal').addClass('hidden');
});


$('#reportForm').on('submit', function(e){
  e.preventDefault();

  const number = $('#modal').data('number');
  const category = $('#category').val();
  const description = $('#description').val().trim();

  if(description.length < 10){
    alert("Deskripsi terlalu singkat. Mohon Sertakan kronologi / konteks.");
    return;
  }

  $.ajax({
    url:'/api.php?action=report.add',
    method:'POST',
    data: JSON.stringify({
      number,
      category,
      description
    }),
    contentType:'application/json',
    success: function(){
      $('#modal').addClass('hidden');
      $('#result').html(`
        <b>Terima kasih!</b><br>
        Laporan anda berhasil dikirim. System kami akan menjalankan proses verifikasi dan menetapkan hasil akhir penilaian dalam beberapa waktu kedepan.
      `);
    },
    error: function(){
      alert("Gagal mengirim laporan. Coba beberapa saat lagi.");
    }
  });
});
