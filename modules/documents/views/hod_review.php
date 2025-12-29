<?php
// HOD Review Documents
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-person-badge"></i> HOD Review</h4>
  <span class="text-muted">Academic Year: <?= htmlspecialchars($year) ?></span>
</div>
<?php if (!empty($list)): ?>
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Type</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($list as $row): ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars($row['doc_title'] ?? $row['file_name']) ?></td>
            <td><?= htmlspecialchars($row['doc_type'] ?? '-') ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="/documents/download/<?= (int)$row['id'] ?>"><i class="bi bi-download"></i></a>
              <button class="btn btn-sm btn-success" onclick="hodAction(<?= (int)$row['id'] ?>,'approve')">Approve</button>
              <button class="btn btn-sm btn-danger" onclick="hodAction(<?= (int)$row['id'] ?>,'reject')">Reject</button>
              <button class="btn btn-sm btn-warning" onclick="hodAction(<?= (int)$row['id'] ?>,'request_changes')">Request Changes</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="alert alert-info">No documents to review.</div>
<?php endif; ?>
<script>
function hodAction(id, action) {
  const comments = prompt('Comments (optional):');
  if (action && id) {
    fetch('/documents/hod/action', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id, action, comments })
    }).then(r => r.json()).then(d => {
      if (d.success) location.reload(); else alert(d.message || 'Failed');
    });
  }
}
</script>
