<?php
// Academic Year filter component (Top-right)
$years = $years ?? (function(){
    $nowY = (int)date('Y');
    $list = [];
    for ($i = 0; $i < 4; $i++) {
        $start = $nowY - 1 + $i; // produce rolling years
        $endShort = ($start + 1) % 100;
        $list[] = sprintf('%d-%02d', $start, $endShort);
    }
    return $list;
})();
$currentYear = $year ?? ($years[0] ?? '');
$actionUrl = $_SERVER['PHP_SELF'] ?? '/documents';
?>
<form method="get" action="<?= htmlspecialchars($actionUrl) ?>" class="d-flex gap-2 align-items-center">
  <label class="form-label mb-0"><i class="bi bi-calendar2-week"></i> Academic Year</label>
  <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
    <?php foreach ($years as $y): ?>
      <option value="<?= htmlspecialchars($y) ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
    <?php endforeach; ?>
  </select>
</form>
