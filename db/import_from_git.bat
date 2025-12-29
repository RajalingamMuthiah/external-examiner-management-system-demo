@echo off
REM Import database from git pull

echo Creating database eems...
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS eems; CREATE DATABASE eems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if errorlevel 1 (
    echo ERROR: Failed to create database
    pause
    exit /b 1
)

echo [OK] Database created
echo.
echo Importing database from latest_db.sql...
echo.

C:\xampp\mysql\bin\mysql.exe -u root eems < "%~dp0latest_db.sql"

if errorlevel 1 (
    echo.
    echo WARNING: Import completed with warnings (check above)
) else (
    echo.
    echo ========================================
    echo [OK] Database imported successfully!
    echo ========================================
)

echo.
echo Verifying tables...
C:\xampp\mysql\bin\mysql.exe -u root eems -e "SHOW TABLES;"

echo.
echo ========================================
echo Setup Complete on PC 2!
echo ========================================
echo.
echo You can now:
echo   1. Login at http://localhost/eems
echo   2. Email: arjun@gmail.com
echo   3. Password: 1234
echo.

pause
