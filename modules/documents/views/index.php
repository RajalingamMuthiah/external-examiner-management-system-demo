<?php
// Documents Dashboard (role-aware)
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0"><i class="bi bi-folder2"></i> Documents Dashboard</h3>
  <!-- Year filter is already in header -->
</div>
<?php if (($role ?? '') === 'teacher'): ?>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-upload"></i> Upload Document</h5>
          <p class="text-muted">Send documents to external examiners by exam and year.</p>
          <a href="/documents/upload?year=<?= urlencode($year) ?>" class="btn btn-primary">Go to Upload</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-file-earmark"></i> My Uploaded Documents</h5>
          <p class="text-muted">View and track your sent documents.</p>
          <a href="/documents/my-uploads?year=<?= urlencode($year) ?>" class="btn btn-outline-primary">View List</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5 class="card-title"><i class="bi bi-inbox"></i> Documents Received</h5>
          <p class="text-muted">Documents received from host colleges.</p>
          <a href="/documents/received?year=<?= urlencode($year) ?>" class="btn btn-outline-secondary">View Inbox</a>
        </div>
      </div>
    </div>
  </div>
  <hr/>
  <h5 class="mt-3">Recent</h5>
  <?php $list = ($received ?? []); include __DIR__ . '/components/document_table.php'; ?>
<?php else: ?>
  <div class="alert alert-info">Select the appropriate section from the top navigation or use role-specific routes.</div>
<?php endif; ?>
