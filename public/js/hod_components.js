// HOD components JS (namespaced HOD_) - handle availability & nominations
document.addEventListener('DOMContentLoaded', function () {
  const HOD = window.HOD_ = window.HOD_ || {};

  HOD.postJson = async function(url, data){
    const headers = {'Content-Type':'application/json'};
    if (window.CSRF_TOKEN) headers['X-CSRF-Token'] = window.CSRF_TOKEN;
    const res = await fetch(url, {method:'POST', headers: headers, credentials:'same-origin', body: JSON.stringify(Object.assign({}, data, {csrf_token: window.CSRF_TOKEN}))});
    return res.ok ? await res.json() : null;
  };

  // Mark unavailable
  const markBtn = document.getElementById('hod-mark-unavailable');
  if (markBtn) {
    markBtn.addEventListener('click', async function (e){
      e.preventDefault();
      const fid = document.getElementById('hod_faculty_id').value;
      const date = document.getElementById('hod_unavailable_date').value;
      const resultDiv = document.getElementById('hod-availability-result');
      resultDiv.textContent = '';
      if (!fid || !date) { resultDiv.textContent = 'Please select a faculty and date.'; return; }
      const res = await HOD.postJson('/api/hod_availability.php', {faculty_id: parseInt(fid,10), date});
      if (res && res.success) {
        resultDiv.textContent = 'Marked unavailable.';
      } else {
        resultDiv.textContent = res && res.error ? res.error : 'Failed to mark unavailable.';
      }
    });
  }

  // Submit nomination
  const nomBtn = document.getElementById('hod-submit-nom');
  if (nomBtn) {
    nomBtn.addEventListener('click', async function (e){
      e.preventDefault();
      const name = document.getElementById('nom_examiner_name').value.trim();
      const role = document.getElementById('nom_role').value.trim();
      if (!name) { alert('Please enter examiner name'); return; }
      const res = await HOD.postJson('/api/hod_nominations.php', {name, role});
      if (res && res.success) { alert('Nomination submitted'); location.reload(); } else { alert(res && res.error ? res.error : 'Submission failed'); }
    });
  }

  // Load department examiner overview into container
  (async function loadOverview(){
    const container = document.getElementById('hod-examiner-overview');
    if (!container) return;
    try {
  const dept = window.HOD_DEPARTMENT || '';
  const res = await fetch('/api/vp_examiners.php?dept=' + encodeURIComponent(dept), {credentials:'same-origin'});
      if (!res.ok) { container.textContent = 'Failed to load overview.'; return; }
      const payload = await res.json();
      const rows = payload.data || [];
      // Filter by dept server-side is possible; since we want department-specific view, request may be extended.
      if (rows.length === 0) { container.textContent = 'No examiners assigned for your department.'; return; }
      const list = document.createElement('ul');
      list.className = 'space-y-2';
      rows.forEach(r => {
        const li = document.createElement('li');
        li.className = 'p-2 bg-gray-50 rounded flex justify-between items-center';
        li.innerHTML = '<div><div class="font-semibold">'+escapeHtml(r.name)+'</div><div class="text-xs text-gray-500">'+escapeHtml(r.expertise)+'</div></div><div class="text-sm text-gray-700">'+escapeHtml(r.availability)+'</div>';
        list.appendChild(li);
      });
      container.innerHTML = '';
      container.appendChild(list);
    } catch (e) {
      container.textContent = 'Error loading overview.';
    }
  })();

  function escapeHtml(s){ return String(s||'').replace(/[&<>"'\\]/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;","\\":"\\\\"}[m]; }); }

});
