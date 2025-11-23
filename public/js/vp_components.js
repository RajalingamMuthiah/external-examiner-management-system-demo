// VP components JS (namespaced to VP_) - minimal and non-conflicting
document.addEventListener('DOMContentLoaded', function () {
  const VP = window.VP_ = window.VP_ || {};

  VP.fetchExaminers = async function (q = '', dept = '') {
    const url = '/api/vp_examiners.php?q=' + encodeURIComponent(q) + '&dept=' + encodeURIComponent(dept);
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) return [];
    const payload = await res.json();
    return payload.data || [];
  };

  VP.renderClientTable = function (containerId, rows) {
    const cont = document.getElementById(containerId);
    if (!cont) return;
    cont.innerHTML = '';
    if (!rows || rows.length === 0) {
      cont.innerHTML = '<div class="text-sm text-gray-500">No examiners found.</div>';
      return;
    }
    const table = document.createElement('table');
    table.className = 'min-w-full divide-y bg-white';
    table.innerHTML = `
      <thead class="bg-gray-50">
        <tr>
          <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Name</th>
          <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Expertise</th>
          <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Dept</th>
          <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Availability</th>
          <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">Status</th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y"></tbody>
    `;
    const tbody = table.querySelector('tbody');
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td class="px-3 py-2 text-sm text-gray-700">' + escapeHtml(r.name) + '</td>'
        + '<td class="px-3 py-2 text-sm text-gray-700">' + escapeHtml(r.expertise) + '</td>'
        + '<td class="px-3 py-2 text-sm text-gray-700">' + escapeHtml(r.dept) + '</td>'
        + '<td class="px-3 py-2 text-sm text-gray-700">' + escapeHtml(r.availability) + '</td>'
        + '<td class="px-3 py-2 text-sm text-gray-700">' + escapeHtml(r.status) + '</td>';
      tbody.appendChild(tr);
    });
    cont.appendChild(table);
  };

  VP.init = function () {
    const openBtn = document.getElementById('vp-open-examiner-search');
    if (openBtn) {
      openBtn.addEventListener('click', async function (e) {
        e.preventDefault();
        const rows = await VP.fetchExaminers();
        VP.renderClientTable('vp-examiners-client', rows);
        const el = document.getElementById('vp-examiners-client');
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth' });
      });
    }

    // Approve / Reject handlers (delegated)
    document.body.addEventListener('click', async function (ev) {
      const t = ev.target;
      if (t.matches('.vp-approve-request') || t.matches('.vp-reject-request')) {
        const id = t.dataset.id;
        const action = t.matches('.vp-approve-request') ? 'approve' : 'reject';
        if (!confirm('Proceed with ' + action + '?')) return;
        const r = await fetch('/api/vp_requests.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id: parseInt(id, 10), action })
        });
        const payload = await r.json();
        if (payload && payload.success) {
          alert('Request ' + payload.status);
          location.reload();
        } else {
          alert('Action failed');
        }
      }
    });
  };

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"'\\]/g, function (m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;","\\":"\\\\"}[m];
    });
  }

  VP.init();
});
