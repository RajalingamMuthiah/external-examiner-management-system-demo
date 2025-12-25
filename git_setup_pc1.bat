@echo off
REM ============================================
REM EEMS Git Setup - PC 1
REM ============================================
REM Initialize Git repo and prepare for data transfer
REM
REM Prerequisites:
REM   - Git must be installed (https://git-scm.com)
REM   - MySQL must be running

setlocal enabledelayedexpansion

set GIT_PATH=C:\Program Files\Git\cmd\git.exe
set MYSQL_DUMP=C:\xampp\mysql\bin\mysqldump.exe
set MYSQL_USER=root
set MYSQL_PASSWORD=
set DB_NAME=eems
set DB_HOST=localhost

echo.
echo ========================================
echo EEMS Git Setup - PC 1 (Source)
echo ========================================
echo.

REM Check if Git is installed
if not exist "%GIT_PATH%" (
    echo ERROR: Git not found at %GIT_PATH%
    echo.
    echo Please install Git from: https://git-scm.com/download/win
    echo Then run this script again
    pause
    exit /b 1
)

echo [OK] Git is installed
echo.

REM Check if in correct directory
if not exist ".git" (
    echo Initializing Git repository...
    "%GIT_PATH%" init
    echo [OK] Git repository initialized
    echo.
) else (
    echo [OK] Git repository already exists
    echo.
)

REM Create .gitignore
echo Creating .gitignore...
(
    echo node_modules/
    echo vendor/
    echo .env
    echo .env.local
    echo *.log
    echo .DS_Store
    echo *.swp
    echo db/backup/
) > .gitignore

echo [OK] .gitignore created
echo.

REM Export database
echo Exporting database "%DB_NAME%"...
if not exist "db" mkdir db

"%MYSQL_DUMP%" --user=%MYSQL_USER% -h %DB_HOST% --single-transaction --lock-tables=false "%DB_NAME%" > "db\eems_database.sql"

if errorlevel 1 (
    echo ERROR: Database export failed
    pause
    exit /b 1
)

echo [OK] Database exported to db\eems_database.sql
echo.

REM Add to Git
echo Adding files to Git...
"%GIT_PATH%" add .
echo [OK] Files staged

echo.
echo Committing to Git...
"%GIT_PATH%" git commit -m "Initial EEMS database export from PC 1 - %date% %time%"

if errorlevel 1 (
    echo [WARNING] Commit may have failed or nothing to commit
) else (
    echo [OK] Changes committed
)

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Next steps:
echo.
echo Option A - Using Local Folder (Recommended for same network):
echo   1. Share this folder on PC 1
echo   2. On PC 2, clone it using: git clone file:///path/to/eems
echo.
echo Option B - Using GitHub:
echo   1. Create a GitHub repository at github.com
echo   2. Run: git remote add origin https://github.com/yourname/eems.git
echo   3. Run: git branch -M main
echo   4. Run: git push -u origin main
echo   5. On PC 2, clone using: git clone https://github.com/yourname/eems.git
echo.
echo Option C - Using Local Git Server:
echo   1. Create a bare repository on a shared drive
echo   2. Push this repo to that location
echo   3. Clone on PC 2 from that location
echo.

pause
