<!-- 
    EEMS - Common Head Include
    ==========================================
    Include this in all dashboard pages for consistency
    Usage: <?php require_once __DIR__ . '/includes/head.php'; ?>
-->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<!-- SEO Meta Tags -->
<meta name="description" content="External Exam Management System - Streamline exam scheduling, faculty assignment, and inter-college examination coordination">
<meta name="keywords" content="exam management, faculty scheduling, external examiners, education system">
<meta name="author" content="EEMS">

<!-- Open Graph / Social Media Meta Tags -->
<meta property="og:title" content="<?php echo $pageTitle ?? 'EEMS - Exam Management System'; ?>">
<meta property="og:description" content="Comprehensive exam management and faculty coordination platform">
<meta property="og:type" content="website">

<!-- Favicon -->
<link rel="icon" type="image/png" href="<?php echo $base_url ?? ''; ?>/public/images/favicon.png">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" 
      integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<!-- Main CSS - Centralized Styles -->
<link rel="stylesheet" href="<?php echo $base_url ?? ''; ?>/public/css/main.css?v=<?php echo time(); ?>">

<!-- Page-specific CSS (optional) -->
<?php if (isset($additionalCSS)): ?>
    <?php foreach ($additionalCSS as $css): ?>
        <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
<?php endif; ?>

<title><?php echo $pageTitle ?? 'EEMS - Exam Management System'; ?></title>
