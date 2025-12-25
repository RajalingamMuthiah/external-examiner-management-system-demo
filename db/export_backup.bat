@echo off
REM ============================================
REM EEMS Database Export - PC 1
REM ============================================
REM This script exports the complete database from PC 1
REM to a SQL file that can be imported on PC 2
REM
REM Usage:
REM   1. Make sure the database exists on PC 1
REM   2. Run this script
REM   3. A SQL file will be created

setlocal enabledelayedexpansion

set MYSQL_PATH=C:\xampp\mysql\bin\mysqldump.exe
set MYSQL_USER=root
set MYSQL_PASSWORD=
set DB_NAME=eems
set DB_HOST=localhost
set OUTPUT_DIR=%~dp0
set TIMESTAMP=%date:~-4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%
set OUTPUT_FILE=eems_backup_%TIMESTAMP%.sql

echo.
echo ========================================
echo EEMS Database Export - PC 1
echo ========================================
echo.

REM Check if mysqldump exists
if not exist "%MYSQL_PATH%" (
    echo ERROR: mysqldump not found at %MYSQL_PATH%
    echo Make sure XAMPP is installed correctly
    pause
    exit /b 1
)

REM Test MySQL connection
echo Testing MySQL connection...
"%MYSQL_PATH%" --user=%MYSQL_USER% -h %DB_HOST% --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Cannot connect to MySQL
    echo Make sure MySQL is running in XAMPP
    pause
    exit /b 1
)
echo [OK] MySQL connection successful
echo.

REM Export database
echo Exporting database "%DB_NAME%" to file...
echo Output file: %OUTPUT_FILE%
echo.

"%MYSQL_PATH%" --user=%MYSQL_USER% -h %DB_HOST% --single-transaction --lock-tables=false "%DB_NAME%" > "%OUTPUT_DIR%%OUTPUT_FILE%"

if errorlevel 1 (
    echo ERROR: Export failed
    pause
    exit /b 1
)

REM Check file size
for %%A in ("%OUTPUT_DIR%%OUTPUT_FILE%") do set SIZE=%%~zA
echo.
echo ========================================
echo [OK] Export successful!
echo ========================================
echo.
echo File: %OUTPUT_FILE%
echo Size: %SIZE% bytes
echo Location: %OUTPUT_DIR%
echo.
echo Next steps:
echo   1. Copy this SQL file to a USB drive or email it
echo   2. Transfer to PC 2
echo   3. On PC 2, place it in: C:\xampp\htdocs\eems\db\
echo   4. Run: import_backup.bat on PC 2
echo.

pause
