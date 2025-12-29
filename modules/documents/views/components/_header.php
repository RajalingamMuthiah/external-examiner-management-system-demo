<?php
// Basic header for Documents module
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Documents - EEMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/modules/documents/assets/documents.css">
</head>
<body>
<nav class="navbar navbar-light bg-light px-3">
  <span class="navbar-brand mb-0 h1"><i class="bi bi-file-earmark-text"></i> Documents</span>
  <div class="ms-auto" style="min-width:220px;">
    <?php include __DIR__ . '/year_filter.php'; ?>
  </div>
</nav>
<div class="container mt-4">
