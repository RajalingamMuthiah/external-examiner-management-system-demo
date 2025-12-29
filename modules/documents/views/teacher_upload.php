<?php
// Teacher Upload View
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-upload"></i> Upload Document</strong>
    <span class="text-muted">Academic Year: <?= htmlspecialchars($year) ?></span>
  </div>
  <div class="card-body">
    <form method="post" action="/documents/upload" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="academic_year" value="<?= htmlspecialchars($year) ?>" />
      <div class="col-md-6">
        <label class="form-label">Exam ID</label>
        <input type="number" name="exam_id" class="form-control" required />
      </div>
      <div class="col-md-6">
        <label class="form-label">Document Type</label>
        <input type="text" name="doc_type" class="form-control" placeholder="e.g., Appointment Letter" />
      </div>
      <div class="col-md-12">
        <label class="form-label">Title</label>
        <input type="text" name="doc_title" class="form-control" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Send To Teacher (optional)</label>
        <input type="number" name="uploaded_for_teacher_id" class="form-control" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Send To College (optional)</label>
        <input type="number" name="uploaded_for_college_id" class="form-control" />
      </div>
      <div class="col-md-12">
        <label class="form-label">File (PDF/DOC/DOCX)</label>
        <input type="file" name="file" accept=".pdf,.doc,.docx" class="form-control" required />
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-upload"></i> Upload</button>
        <a href="/documents" class="btn btn-link">Back</a>
      </div>
    </form>
  </div>
</div>
