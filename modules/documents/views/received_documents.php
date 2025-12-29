<?php
// Received Documents
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-inbox"></i> Documents Received</h4>
  <span class="text-muted">Academic Year: <?= htmlspecialchars($year) ?></span>
</div>
<?php include __DIR__ . '/components/document_table.php'; ?>
