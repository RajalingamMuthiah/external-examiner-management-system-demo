@echo off
REM Import the working schema file

echo Importing schema from complete_eems_schema.sql...

C:\xampp\mysql\bin\mysql.exe -u root eems < "C:\xampp\htdocs\eems\db\complete_eems_schema.sql"

if errorlevel 1 (
    echo WARNING: Some errors occurred during import
) else (
    echo [OK] Schema imported
)

echo.
echo Verifying tables created...
C:\xampp\mysql\bin\mysql.exe -u root eems -e "SELECT COUNT(*) as 'Total Tables' FROM information_schema.tables WHERE table_schema='eems';"

echo.
echo ========================================
echo Database Setup Complete!
echo ========================================
echo.
echo You can now login at: http://localhost/eems
echo Email: arjun@gmail.com
echo Password: 1234
echo.

pause
