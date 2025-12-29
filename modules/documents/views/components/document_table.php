<?php
/**
 * Simple document table component
 * Expects $list (array of rows)
 */
?>
<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Title</th>
        <th>Type</th>
        <th>Year</th>
        <th>Status</th>
        <th>Uploaded On</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($list)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox"></i> No documents</td></tr>
      <?php else: ?>
        <?php foreach ($list as $row): ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td><?= htmlspecialchars($row['doc_title'] ?? $row['file_name']) ?></td>
            <td><?= htmlspecialchars($row['doc_type'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['academic_year']) ?></td>
            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><?= htmlspecialchars($row['uploaded_on']) ?></td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="/documents/download/<?= (int)$row['id'] ?>"><i class="bi bi-download"></i> Download</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
