<?php
// exam_management.php - Exam Management Table, Filters, and Stats (no form)
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<div class="container my-4">
  <!-- Filter/Search Bar -->
  <div class="card mb-3">
    <div class="card-body">
      <form id="filterForm" class="row g-2">
        <div class="col-md-10">
          <input type="text" class="form-control" name="search" id="search" placeholder="Search exams by name, college, course...">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Statistics Bar (example) -->
  <div class="row mb-3">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6 class="card-title">Total Exams</h6>
          <div id="totalExams" class="display-6">--</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6 class="card-title">Pending</h6>
          <div id="pendingExams" class="display-6">--</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6 class="card-title">Approved</h6>
          <div id="approvedExams" class="display-6">--</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h6 class="card-title">Closed</h6>
          <div id="closedExams" class="display-6">--</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Exams Table -->
  <div class="card">
    <div class="card-header bg-light">Exams List</div>
    <div class="card-body p-0">
      <div id="examsTableContainer"></div>
    </div>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="exam_dashboard.js"></script>
