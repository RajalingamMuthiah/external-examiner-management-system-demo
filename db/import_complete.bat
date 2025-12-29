@echo off
REM Import database with error handling

setlocal enabledelayedexpansion

set MYSQL=C:\xampp\mysql\bin\mysql.exe
set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DB_FILE=C:\xampp\htdocs\eems\db\latest_db.sql

echo.
echo ========================================
echo EEMS Database Import from Git
echo ========================================
echo.

REM Check if file exists
if not exist "%DB_FILE%" (
    echo ERROR: Database file not found
    echo Expected: %DB_FILE%
    pause
    exit /b 1
)

echo Database file: %DB_FILE%
for %%A in ("%DB_FILE%") do set FILE_SIZE=%%~zA
echo File size: %FILE_SIZE% bytes
echo.

REM Drop existing database
echo Step 1: Preparing database...
"%MYSQL%" -u root -e "DROP DATABASE IF EXISTS eems;"
if errorlevel 1 (
    echo ERROR: Failed to drop database
    pause
    exit /b 1
)

echo [OK] Old database removed
echo.

REM Create new database  
echo Step 2: Creating new database...
"%MYSQL%" -u root -e "CREATE DATABASE eems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo ERROR: Failed to create database
    pause
    exit /b 1
)

echo [OK] Database created
echo.

REM Import with proper error handling
echo Step 3: Importing data (this may take a moment)...
echo.

REM Read file and import
for /f "usebackq" %%A in ("%DB_FILE%") do (
    set "line=%%A"
)

"%MYSQL%" -u root --default-character-set=utf8mb4 eems < "%DB_FILE%" 2>"%TEMP%\mysql_error.log"

REM Check for errors
if errorlevel 1 (
    echo.
    echo WARNING: Import completed with errors
    echo.
    REM Show last few error lines
    findstr /v "^$" "%TEMP%\mysql_error.log" | tail -20
)

echo.
echo [OK] Import complete
echo.

REM Verify
echo Step 4: Verifying tables...
echo.

"%MYSQL%" -u root eems -e "SELECT COUNT(*) as 'Tables' FROM information_schema.tables WHERE table_schema='eems';" > "%TEMP%\table_count.txt"

for /f "skip=1" %%A in (%TEMP%\table_count.txt) do (
    set TABLE_COUNT=%%A
)

if "%TABLE_COUNT%"=="0" (
    echo ERROR: No tables found! Import failed.
    pause
    exit /b 1
)

echo [OK] Found %TABLE_COUNT% tables
echo.

REM Show sample data
echo Sample tables:
"%MYSQL%" -u root eems -e "SHOW TABLES LIMIT 5;"

echo.
echo ========================================
echo [SUCCESS] Database Imported!
echo ========================================
echo.
echo Tables created: %TABLE_COUNT%
echo.
echo You can now login at: http://localhost/eems
echo.
echo Credentials:
echo   Email: arjun@gmail.com
echo   Password: 1234
echo.

pause
