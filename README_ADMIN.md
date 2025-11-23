Admin password migration & setup

This file explains how to create or update password-based credentials for admin/principal users.

1) Create a hashed password using PHP (recommended)

Use the PHP CLI on your laptop to generate a bcrypt/argon2 hash. Replace "YourSecretPassword" with the real password:

```powershell
php -r "echo password_hash('YourSecretPassword', PASSWORD_DEFAULT) . PHP_EOL;"
```

The command prints a hash like `$2y$10$...`.

2) Update an existing admin user in the database

Open your database client (phpMyAdmin, MySQL shell, etc.) and run the SQL (use the hash produced above):

```sql
UPDATE users
SET password = '<PASTE_HASH_HERE>'
WHERE email = 'admin@example.com';
```

3) Create a new admin user with a password (SQL)

If you prefer to create a new admin user directly with SQL, first generate a hash as in step 1, then run:

```sql
INSERT INTO users (name, email, password, post, college_name, phone, status)
VALUES ('Admin Name', 'admin@example.com', '<PASTE_HASH_HERE>', 'principal', 'Your College', '+911234567890', 'verified');
```

4) Testing

- Start the PHP built-in server from the project root:

```powershell
cd 'C:\Users\LENOVO\Documents\GitHub\eems'
php -S localhost:8000
```

- Visit http://localhost:8000/login.php and login with the admin email and the password you set.

Notes & safety

- Use strong passwords and change them regularly.
- For production, implement email-based password reset flows and require HTTPS.
- If you need, I can add a small CLI script to set passwords programmatically (safe for local use).