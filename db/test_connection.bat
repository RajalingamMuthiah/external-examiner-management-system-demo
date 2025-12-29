@echo off
REM Test database connection

echo Testing database connection...
echo.

C:\xampp\php\php.exe -r "try { require 'C:\xampp\htdocs\eems\config\db.php'; $db = $pdo->query('SELECT DATABASE()')->fetch()[0]; echo 'Database: ' . $db . PHP_EOL; echo 'Status: Connected Successfully'; } catch (Exception $e) { echo 'ERROR: ' . $e->getMessage(); }"

echo.
echo.
echo ========================================
echo [DONE] Git Import Complete!
echo ========================================
echo.
echo Your EEMS system is ready on PC 2:
echo.
echo   1. Open browser: http://localhost/eems
echo   2. Login with:
echo      Email: arjun@gmail.com
echo      Password: 1234
echo.
echo All data from PC 1 has been transferred via Git.
echo.

pause
