<?php
// Documents module routes
// Include this file once from main router: include 'modules/documents/routes/documents_routes.php';

$__doc_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (strpos($__doc_uri, '/documents') !== 0) {
    return; // Not a documents route, let other routers handle
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../models/Document.php';
require_once __DIR__ . '/../controllers/DocumentsController.php';

$controller = new DocumentsController($pdo);
$sub = trim(substr($__doc_uri, strlen('/documents')), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch (true) {
    case $sub === '' && $method === 'GET':
        $controller->index();
        break;
    case $sub === 'upload' && $method === 'GET':
        $controller->uploadGet();
        break;
    case $sub === 'upload' && $method === 'POST':
        $controller->uploadPost();
        break;
    case $sub === 'my-uploads' && $method === 'GET':
        $controller->myUploads();
        break;
    case $sub === 'received' && $method === 'GET':
        $controller->received();
        break;
    case $sub === 'hod/review' && $method === 'GET':
        $controller->hodReview();
        break;
    case $sub === 'hod/action' && $method === 'POST':
        $controller->hodActionPost();
        break;
    case $sub === 'vp/all' && $method === 'GET':
        $controller->vpAll();
        break;
    case $sub === 'admin/all' && $method === 'GET':
        $controller->adminAll();
        break;
    case preg_match('#^download/(\d+)$#', $sub, $m):
        $controller->download((int)$m[1]);
        break;
    default:
        http_response_code(404);
        echo 'Not Found';
}
exit; // Stop further routing once handled
