<?php
// VP/Principal college-wide documents
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-building"></i> College Documents</h4>
  <span class="text-muted">Academic Year: <?= htmlspecialchars($year) ?></span>
</div>
<form method="get" class="row g-2 mb-3">
  <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>"/>
  <div class="col-md-3">
    <label class="form-label">Department</label>
    <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($filters['department'] ?? '') ?>"/>
  </div>
  <div class="col-md-3">
    <label class="form-label">Teacher ID</label>
    <input type="number" name="teacher" class="form-control" value="<?= htmlspecialchars($filters['teacher'] ?? '') ?>"/>
  </div>
  <div class="col-md-3">
    <label class="form-label">Document Type</label>
    <input type="text" name="doc_type" class="form-control" value="<?= htmlspecialchars($filters['doc_type'] ?? '') ?>"/>
  </div>
  <div class="col-md-3 align-self-end">
    <button class="btn btn-primary" type="submit"><i class="bi bi-filter"></i> Apply Filters</button>
  </div>
</form>
<?php include __DIR__ . '/components/document_table.php'; ?>
