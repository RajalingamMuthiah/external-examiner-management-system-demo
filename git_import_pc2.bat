@echo off
REM ============================================
REM EEMS Git Import - PC 2
REM ============================================
REM Clone repository and import database from PC 1
REM
REM Prerequisites:
REM   - Git must be installed
REM   - MySQL must be running
REM   - PC 1 repository must be accessible

setlocal enabledelayedexpansion

set GIT_PATH=C:\Program Files\Git\cmd\git.exe
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe
set MYSQL_USER=root
set MYSQL_PASSWORD=
set DB_HOST=localhost

echo.
echo ========================================
echo EEMS Git Import - PC 2 (Destination)
echo ========================================
echo.

REM Check if Git is installed
if not exist "%GIT_PATH%" (
    echo ERROR: Git not found
    echo Please install Git from: https://git-scm.com/download/win
    pause
    exit /b 1
)

echo [OK] Git is installed
echo.

REM Check MySQL
echo Testing MySQL connection...
"%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% -e "SELECT 1;" >nul 2>&1
if errorlevel 1 (
    echo ERROR: MySQL is not running
    echo Please start MySQL in XAMPP Control Panel
    pause
    exit /b 1
)

echo [OK] MySQL is running
echo.

REM Get repository source
set /p GIT_SOURCE="Enter Git repository URL or path (e.g., \\PC1\eems or https://github.com/user/eems.git): "

if "!GIT_SOURCE!"=="" (
    echo ERROR: No repository source provided
    pause
    exit /b 1
)

echo.
echo Cloning repository from: !GIT_SOURCE!
echo.

REM Clone repository
"%GIT_PATH%" clone "!GIT_SOURCE!" eems_repo

if errorlevel 1 (
    echo ERROR: Failed to clone repository
    echo Make sure the source is accessible and correct
    pause
    exit /b 1
)

echo [OK] Repository cloned
echo.

REM Navigate to cloned repo
cd eems_repo

REM Check for database file
if not exist "db\eems_database.sql" (
    echo ERROR: Database file not found at db\eems_database.sql
    echo Make sure the repository contains the database export
    pause
    exit /b 1
)

echo Found database file: db\eems_database.sql
echo.

REM Import database
echo Importing database...
echo.

"%MYSQL_PATH%" -u %MYSQL_USER% -h %DB_HOST% < "db\eems_database.sql"

if errorlevel 1 (
    echo.
    echo WARNING: Import completed with some errors
    echo Check the messages above
) else (
    echo.
    echo ========================================
    echo [OK] Database imported successfully!
    echo ========================================
)

echo.
echo Next steps:
echo   1. Navigate to: C:\xampp\htdocs\eems_repo
echo   2. Copy the PHP files to your EEMS directory
echo   3. Or use this as your main EEMS folder
echo   4. Visit http://localhost/eems_repo
echo   5. Login with: arjun@gmail.com / 1234
echo.
echo To pull updates from PC 1:
echo   1. Open PowerShell in this folder
echo   2. Run: git pull
echo.

cd ..

pause
