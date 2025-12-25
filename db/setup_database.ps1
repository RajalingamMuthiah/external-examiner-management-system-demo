# ============================================
# EEMS Database Setup Automation Script
# ============================================
# This script creates the database and imports all schemas automatically
# Run this in PowerShell as Administrator

# Configuration
$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
$mysqlUser = "root"
$mysqlPassword = ""
$dbName = "eems"
$dbHost = "localhost"

# Database setup files in order
$setupFiles = @(
    "complete_eems_schema.sql",
    "sample_data.sql"
)

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "EEMS Database Setup - Automated" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if MySQL exists
if (-not (Test-Path $mysqlPath)) {
    Write-Host "ERROR: MySQL not found at $mysqlPath" -ForegroundColor Red
    Write-Host "Make sure XAMPP is installed and MySQL is in the default location." -ForegroundColor Yellow
    exit 1
}

Write-Host "[1/3] Checking MySQL connection..." -ForegroundColor Yellow
# Test connection
& $mysqlPath -u $mysqlUser -h $dbHost -e "SELECT 1;" 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Cannot connect to MySQL. Make sure MySQL is running." -ForegroundColor Red
    exit 1
}
Write-Host "[✓] MySQL connection successful" -ForegroundColor Green
Write-Host ""

# Drop existing database if it exists
Write-Host "[2/3] Creating/Resetting database..." -ForegroundColor Yellow
$dropCmd = "DROP DATABASE IF EXISTS $dbName; CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& $mysqlPath -u $mysqlUser -h $dbHost -e $dropCmd
if ($LASTEXITCODE -eq 0) {
    Write-Host "[✓] Database '$dbName' created successfully" -ForegroundColor Green
} else {
    Write-Host "ERROR: Failed to create database" -ForegroundColor Red
    exit 1
}
Write-Host ""

# Import SQL files
Write-Host "[3/3] Importing database schemas..." -ForegroundColor Yellow
$importedCount = 0
foreach ($file in $setupFiles) {
    $filePath = Join-Path $scriptDir $file
    
    if (Test-Path $filePath) {
        Write-Host "  → Importing $file..." -ForegroundColor Cyan
        & $mysqlPath -u $mysqlUser -h $dbHost $dbName < $filePath
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "    [✓] $file imported" -ForegroundColor Green
            $importedCount++
        } else {
            Write-Host "    [!] Warning: $file import had issues" -ForegroundColor Yellow
        }
    } else {
        Write-Host "    [!] Warning: $file not found at $filePath" -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Database: $dbName" -ForegroundColor Cyan
Write-Host "Files imported: $importedCount" -ForegroundColor Cyan
Write-Host ""
Write-Host "You can now:" -ForegroundColor Cyan
Write-Host "  1. Access phpMyAdmin at http://localhost/phpmyadmin" -ForegroundColor White
Write-Host "  2. Login to your application at http://localhost/eems" -ForegroundColor White
Write-Host "  3. Use admin credentials:" -ForegroundColor White
Write-Host "     Email: arjun@gmail.com" -ForegroundColor White
Write-Host "     Password: 1234" -ForegroundColor White
Write-Host ""
Write-Host "Database connection details in config/db.php:" -ForegroundColor Cyan
Write-Host "  Host: $dbHost" -ForegroundColor White
Write-Host "  User: $mysqlUser" -ForegroundColor White
Write-Host "  Database: $dbName" -ForegroundColor White
Write-Host ""

# Verify tables were created
Write-Host "Verifying tables..." -ForegroundColor Yellow
$tableCount = & $mysqlPath -u $mysqlUser -h $dbHost -e "USE $dbName; SHOW TABLES;" 2>&1 | Measure-Object | Select-Object -ExpandProperty Count
$tableCount = $tableCount - 1  # Subtract header line
Write-Host "[✓] Found $tableCount tables in database" -ForegroundColor Green
Write-Host ""
Write-Host "Press any key to close..." -ForegroundColor Gray
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
