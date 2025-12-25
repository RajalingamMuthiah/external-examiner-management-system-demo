@echo off
REM ============================================
REM EEMS Database Import - PC 2 Simple Method
REM ============================================
REM This script imports a SQL backup file from PC 1
REM 
REM Usage:
REM   1. Place your SQL backup file in this directory (db folder)
REM   2. Run this script
REM   3. Enter the SQL filename when prompted

setlocal enabledelayedexpansion

set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe
set MYSQL_USER=root
set MYSQL_PASSWORD=
set DB_HOST=localhost

echo.
echo ========================================
echo EEMS Database Import - PC 2
echo ========================================
echo.

REM Check if MySQL exists
if not exist "%MYSQL_PATH%" (
    echo ERROR: MySQL not found
    echo Make sure XAMPP is installed
    pause
    exit /b 1
)

REM Check MySQL is running
echo Testing MySQL connection...
"%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo ERROR: MySQL is not running!
    echo.
    echo Please:
    echo   1. Open XAMPP Control Panel
    echo   2. Click "Start" next to MySQL
    echo   3. Run this script again
    pause
    exit /b 1
)
echo [OK] MySQL is running
echo.

REM List available SQL files
echo Available SQL files in this directory:
echo.
dir /b *.sql
echo.

REM Get filename from user
set /p SQLFILE="Enter the SQL filename (e.g., eems_backup.sql): "

if not exist "%SQLFILE%" (
    echo ERROR: File "%SQLFILE%" not found
    pause
    exit /b 1
)

echo.
echo Starting import from: %SQLFILE%
echo.

REM Import the file
"%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% < "%SQLFILE%"

if errorlevel 1 (
    echo.
    echo WARNING: Import completed with some errors
    echo Check the messages above for details
) else (
    echo.
    echo ========================================
    echo [OK] Database imported successfully!
    echo ========================================
    echo.
    echo You can now:
    echo   1. Visit http://localhost/eems
    echo   2. Login with:
    echo      Email: arjun@gmail.com
    echo      Password: 1234
    echo.
)

pause
