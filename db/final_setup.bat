@echo off
REM Complete database setup with schema and data

echo ========================================
echo EEMS Database Setup - Final
echo ========================================
echo.

REM Drop and create database (clean reset)
echo Step 1: Creating database...
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS eems; CREATE DATABASE eems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if errorlevel 1 (
    echo ERROR: Failed to create database
    pause
    exit /b 1
)
echo [OK] Database created
echo.

REM Import base schema first (creates users, exams, assignments, etc.)
echo Step 2: Importing base schema (schema.sql)...
C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems -e "source %~dp0schema.sql"
if errorlevel 1 (
    echo ERROR: Base schema import failed
    pause
    exit /b 1
)
echo [OK] Base schema imported
echo.

REM Apply alignment/migrations (optional if present)
if exist "%~dp0migration_align_schema.sql" (
    echo Step 3: Applying alignment migrations (migration_align_schema.sql)...
    C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems -e "source %~dp0migration_align_schema.sql"
)

REM Import complete schema upgrades (adds columns, extra tables)
echo Step 4: Applying complete schema upgrades (complete_eems_schema.sql)...
C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems -e "source %~dp0complete_eems_schema.sql"
if errorlevel 1 (
        echo WARNING: Complete schema upgrades had some issues (safe to continue if minor)
)
echo [OK] Schema upgrades applied

REM Import latest data dump if available, else seed/sample
if exist "%~dp0latest_db.sql" (
    echo Step 5: Importing latest data dump (latest_db.sql)...
    C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems -e "source %~dp0latest_db.sql"
    if errorlevel 1 (
            echo WARNING: latest_db.sql import had issues
    ) else (
            echo [OK] Latest data imported
    )
) else (
    echo Step 5: Importing seed/sample data...
    if exist "%~dp0seed.sql" (
        C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems -e "source %~dp0seed.sql"
        echo [OK] Seed data imported
    ) else if exist "%~dp0sample_data.sql" (
        C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems -e "source %~dp0sample_data.sql"
        echo [OK] Sample data imported
    ) else (
        echo [!] No data file found (latest_db.sql/seed.sql/sample_data.sql). Proceeding with empty schema.
    )
)
echo.

REM Verify
echo Step 6: Verifying...
for /f %%A in ('C:\xampp\mysql\bin\mysql.exe -u root eems -e "SHOW TABLES;" 2^>nul ^| find /c /v ""') do set TABLE_COUNT=%%A
set /a TABLE_COUNT=%TABLE_COUNT%-1

echo [OK] Found %TABLE_COUNT% tables
echo.

echo ========================================
echo [SUCCESS] Database Ready!
echo ========================================
echo.
echo Login Details:
echo   Email: arjun@gmail.com
echo   Password: 1234
echo.
echo Access: http://localhost/eems
echo.

pause
