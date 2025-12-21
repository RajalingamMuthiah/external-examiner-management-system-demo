@echo off
echo ========================================
echo phpMyAdmin Fix Script
echo ========================================
echo.

echo Step 1: Stopping Apache...
taskkill /F /IM httpd.exe >nul 2>&1
timeout /t 2 >nul

echo Step 2: Clearing PHP temp files...
del /Q c:\xampp\tmp\* >nul 2>&1

echo Step 3: Starting Apache...
start "" "c:\xampp\apache_start.bat"
timeout /t 5 >nul

echo Step 4: Testing connection...
echo.
echo Opening phpMyAdmin in your browser...
echo.
echo If it doesn't work, try these URLs:
echo   1. http://localhost:8080/phpmyadmin/
echo   2. http://localhost:8080/phpMyAdmin/
echo   3. http://127.0.0.1:8080/phpmyadmin/
echo.

start http://localhost:8080/phpmyadmin/
timeout /t 2 >nul
start http://localhost:8080/phpMyAdmin/

echo.
echo ========================================
echo Script completed!
echo ========================================
pause
