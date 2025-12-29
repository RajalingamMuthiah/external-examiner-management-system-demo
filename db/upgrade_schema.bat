@echo off
REM Upgrade existing eems database: apply migrations and import data without dropping DB

echo Applying schema alignment (if present)...
if exist "%~dp0migration_align_schema.sql" (
  C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems < "%~dp0migration_align_schema.sql"
)

echo Applying complete schema upgrades...
C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems < "%~dp0complete_eems_schema.sql"
if errorlevel 1 (
  echo WARNING: Some schema upgrades reported issues (safe to continue if minor)
)

echo Importing latest data (if available)...
if exist "%~dp0latest_db.sql" (
  C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems < "%~dp0latest_db.sql"
  if errorlevel 1 (
    echo WARNING: latest_db.sql import had issues
  ) else (
    echo [OK] Latest data imported
  )
) else if exist "%~dp0seed.sql" (
  C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems < "%~dp0seed.sql"
  echo [OK] Seed data imported
) else if exist "%~dp0sample_data.sql" (
  C:\xampp\mysql\bin\mysql.exe --binary-mode=1 -u root eems < "%~dp0sample_data.sql"
  echo [OK] Sample data imported
) else (
  echo [!] No data file found
)

echo Verifying tables...
C:\xampp\mysql\bin\mysql.exe -u root eems -e "SELECT COUNT(*) AS tables FROM information_schema.tables WHERE table_schema='eems';"

echo Done.
pause
