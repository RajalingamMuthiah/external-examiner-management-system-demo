@echo off
REM ============================================
REM EEMS Database Setup - Automated Batch Script
REM ============================================
REM Run this as Administrator

setlocal enabledelayedexpansion

REM Configuration
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe
set MYSQL_USER=root
set MYSQL_PASSWORD=
set DB_NAME=eems
set DB_HOST=localhost
set SCRIPT_DIR=%~dp0

echo.
echo ========================================
echo EEMS Database Setup - Automated
echo ========================================
echo.

REM Check if MySQL exists
if not exist "%MYSQL_PATH%" (
    echo ERROR: MySQL not found at %MYSQL_PATH%
    echo Make sure XAMPP is installed and MySQL is in the default location.
    pause
    exit /b 1
)

REM Test MySQL connection
echo [1/4] Testing MySQL connection...
"%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo ERROR: Cannot connect to MySQL. Make sure MySQL is running.
    echo.
    echo Try:
    echo   1. Open XAMPP Control Panel
    echo   2. Click "Start" next to MySQL
    echo   3. Run this script again
    pause
    exit /b 1
)
echo [OK] MySQL connection successful
echo.

REM Create database
echo [2/4] Creating database '%DB_NAME%'...
"%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% -e "DROP DATABASE IF EXISTS %DB_NAME%; CREATE DATABASE %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
if errorlevel 1 (
    echo ERROR: Failed to create database
    pause
    exit /b 1
)
echo [OK] Database created
echo.

REM Import schema
echo [3/4] Importing database schema...
if exist "%SCRIPT_DIR%complete_eems_schema.sql" (
    "%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% %DB_NAME% < "%SCRIPT_DIR%complete_eems_schema.sql" >nul 2>&1
    if errorlevel 1 (
        echo WARNING: Schema import had issues, but continuing...
    ) else (
        echo [OK] Schema imported
    )
) else (
    echo WARNING: complete_eems_schema.sql not found
)
echo.

REM Import sample data
echo [4/4] Importing sample data...
if exist "%SCRIPT_DIR%sample_data.sql" (
    "%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% %DB_NAME% < "%SCRIPT_DIR%sample_data.sql" >nul 2>&1
    if errorlevel 1 (
        echo WARNING: Sample data import had issues
    ) else (
        echo [OK] Sample data imported
    )
) else (
    echo WARNING: sample_data.sql not found
)
echo.

REM Verify tables
echo Verifying tables...
for /f %%A in ('"%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% %DB_NAME% -e "SHOW TABLES;" 2^>nul ^| find /c /v ""') do set TABLE_COUNT=%%A
set /a TABLE_COUNT=%TABLE_COUNT%-1
echo [OK] Found %TABLE_COUNT% tables
echo.

echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Database: %DB_NAME%
echo Host: %DB_HOST%
echo User: %MYSQL_USER%
echo.
echo Next steps:
echo   1. Login at http://localhost/eems
echo   2. Use admin credentials:
echo      Email: arjun@gmail.com
echo      Password: 1234
echo.
pause
