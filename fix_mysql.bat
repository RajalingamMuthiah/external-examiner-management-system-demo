@echo off
echo ========================================
echo MySQL Auto-Shutdown Fix Script
echo ========================================
echo.

echo This script will fix the most common MySQL shutdown issues
echo.
echo IMPORTANT: This will NOT delete your databases!
echo.
pause

echo.
echo Step 1: Force stopping any MySQL processes...
taskkill /F /IM mysqld.exe >nul 2>&1
timeout /t 3 >nul

echo Step 2: Checking for port conflicts on 3306...
netstat -ano | findstr :3306 >nul
if %errorlevel% equ 0 (
    echo WARNING: Port 3306 is in use by another process!
    echo You may need to stop that process first.
    netstat -ano | findstr :3306
) else (
    echo Port 3306 is free - Good!
)
echo.

echo Step 3: Removing MySQL lock and PID files...
del /Q "c:\xampp\mysql\data\*.pid" >nul 2>&1
del /Q "c:\xampp\mysql\data\*.lock" >nul 2>&1
echo Lock files removed.
echo.

echo Step 4: Fixing InnoDB log files (Common cause of crashes)...
echo Creating backup folder...
if not exist "c:\xampp\mysql\data\backup_logs" mkdir "c:\xampp\mysql\data\backup_logs"

echo Moving old log files to backup...
move "c:\xampp\mysql\data\ib_logfile0" "c:\xampp\mysql\data\backup_logs\" >nul 2>&1
move "c:\xampp\mysql\data\ib_logfile1" "c:\xampp\mysql\data\backup_logs\" >nul 2>&1
echo InnoDB log files removed - MySQL will recreate them.
echo.

echo Step 5: Starting MySQL...
echo Please wait while MySQL initializes...
start "" "c:\xampp\mysql_start.bat"
timeout /t 8 >nul

echo.
echo Step 6: Verifying MySQL is running...
tasklist | findstr "mysqld.exe" >nul
if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCESS! MySQL is now running!
    echo ========================================
    echo.
    echo Testing connection...
    c:\xampp\mysql\bin\mysql.exe -u root -e "SELECT 'Connected!' as Status, VERSION() as MySQL_Version;" 2>nul
    if %errorlevel% equ 0 (
        echo.
        echo MySQL is working perfectly!
        echo You can now access phpMyAdmin at: http://localhost:8080/phpmyadmin/
    ) else (
        echo MySQL process is running but connection test failed.
        echo Try accessing: http://localhost:8080/phpmyadmin/
    )
) else (
    echo.
    echo ========================================
    echo MySQL FAILED to start!
    echo ========================================
    echo.
    echo Showing error log (last 30 lines):
    echo.
    powershell -Command "Get-Content 'c:\xampp\mysql\data\mysql_error.log' -Tail 30"
    echo.
    echo.
    echo NEXT STEPS TO TRY:
    echo ==================
    echo 1. Run XAMPP Control Panel as ADMINISTRATOR
    echo 2. Check if antivirus is blocking MySQL
    echo 3. Ensure you have at least 1GB free disk space
    echo 4. Read: MYSQL_FIX_GUIDE.md for more solutions
    echo.
)

echo.
echo ========================================
pause
