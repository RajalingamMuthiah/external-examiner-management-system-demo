<?php
require_once 'includes/exam_functions.php';
require_once 'config/db.php';
$exams = get_exams($pdo, $_GET);
?>
<table class="table table-striped table-hover mb-0">
  <thead>
    <tr>
      <th>Exam Name</th>
      <th>College</th>
      <th>Course</th>
      <th>Date</th>
      <th>Time</th>
      <th>Max/Pass Marks</th>
      <th>Type</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($exams as $exam): ?>
    <tr>
      <td><?= htmlspecialchars($exam['subject_name']) ?></td>
      <td><?= htmlspecialchars($exam['college_name']) ?></td>
      <td><?= htmlspecialchars($exam['course']) ?></td>
      <td><?= htmlspecialchars($exam['exam_date']) ?></td>
      <td><?= htmlspecialchars($exam['start_time']) ?> - <?= htmlspecialchars($exam['end_time']) ?></td>
      <td><?= htmlspecialchars($exam['max_marks']) ?>/<?= htmlspecialchars($exam['pass_marks']) ?></td>
      <td><?= htmlspecialchars($exam['type']) ?></td>
      <td><?= htmlspecialchars($exam['status']) ?></td>
      <td>
          <a class="btn btn-sm btn-info" href="/external/eems/admin/exam_view.php?id=<?= (int)$exam['id'] ?>">View</a>
          <a class="btn btn-sm btn-warning" href="/external/eems/admin/exam_edit.php?id=<?= (int)$exam['id'] ?>">Edit</a>
          <a class="btn btn-sm btn-danger" href="/external/eems/admin/exam_delete.php?id=<?= (int)$exam['id'] ?>" onclick="return confirm('Are you sure you want to delete this exam?');">Delete</a>
          <?php if ($exam['status'] == 'pending'): ?>
          <a class="btn btn-sm btn-success" href="/external/eems/admin/exam_approve.php?id=<?= (int)$exam['id'] ?>">Approve</a>
          <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
