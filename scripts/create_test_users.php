<?php
// scripts/create_test_users.php
// Run: php scripts/create_test_users.php
// Creates/updates test admin, principal and vice-principal users with secure password hashes.

require_once __DIR__ . '/../config/db.php';

$defaults = [
    'admin_email' => getenv('EEMS_ADMIN_EMAIL') ?: 'admin@gmail.com',
    'admin_password' => getenv('EEMS_ADMIN_PASSWORD') ?: 'admin123',
    'principal_email' => getenv('EEMS_PRINCIPAL_EMAIL') ?: 'principal@example.com',
    'vp_email' => getenv('EEMS_VP_EMAIL') ?: 'vp@example.com',
    'test_password' => getenv('EEMS_TEST_PASSWORD') ?: 'P@ssw0rd123',
];

function upsert_user($pdo, $name, $email, $password_plain, $post, $phone = null) {
    $hash = password_hash($password_plain, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (name, email, password, post, phone, status) VALUES (:name, :email, :password, :post, :phone, 'verified')
            ON DUPLICATE KEY UPDATE password = VALUES(password), post = VALUES(post), status = 'verified', phone = VALUES(phone), name = VALUES(name)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hash,
        ':post' => $post,
        ':phone' => $phone,
    ]);
}

try {
    echo "Creating/updating test users...\n";

    // Admin
    upsert_user($pdo, 'Built-in Admin', $defaults['admin_email'], $defaults['admin_password'], 'admin', '9999999990');
    echo "- Admin: {$defaults['admin_email']} / (configured password)\n";

    // Principal
    upsert_user($pdo, 'Principal Test', $defaults['principal_email'], $defaults['test_password'], 'principal', '9999999991');
    echo "- Principal: {$defaults['principal_email']} / {$defaults['test_password']}\n";

    // Vice Principal
    upsert_user($pdo, 'Vice Principal Test', $defaults['vp_email'], $defaults['test_password'], 'vice-principal', '9999999992');
    echo "- Vice Principal: {$defaults['vp_email']} / {$defaults['test_password']}\n";

    echo "Done. All accounts set to status=verified.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
