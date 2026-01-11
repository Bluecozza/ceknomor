function addPhone(){
  const div = document.createElement('div');
  div.innerHTML = `
    <input type="text" name="phones[]" class="form-control mb-2" placeholder="62812xxxx" required>
  `;
  document.getElementById('phoneList').appendChild(div);
}

document.getElementById('reportForm').addEventListener('submit', async e => {
  e.preventDefault();

  const form = e.target;
  const fd = new FormData(form);

  const res = await fetch('/api/report.create.php', {
    method: 'POST',
    body: fd
  });

  const json = await res.json();
  if(json.status === 'ok'){
    alert('Laporan berhasil dikirim');
    location.reload();
  } else {
    alert(json.message || 'Gagal mengirim laporan');
  }
});
