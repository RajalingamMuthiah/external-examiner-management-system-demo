# Import Database Script
# Usage: .\db\import_db.ps1

$dbName = "eems"
$username = "root"
$password = ""  # Add your password if needed
$importPath = "db\latest_db.sql"
$mysql = "C:\xampp\mysql\bin\mysql.exe"

Write-Host "Importing database: $dbName" -ForegroundColor Green
Write-Host "From: $importPath" -ForegroundColor Cyan

if (-Not (Test-Path $importPath)) {
    Write-Host "[ERROR] SQL file not found: $importPath" -ForegroundColor Red
    Write-Host "Did you run 'git pull' first?" -ForegroundColor Yellow
    exit 1
}

# Backup current database first
Write-Host "Creating backup first..." -ForegroundColor Yellow
$backupPath = "db\backup\backup_$(Get-Date -Format 'yyyyMMdd_HHmmss').sql"
New-Item -ItemType Directory -Force -Path "db\backup" | Out-Null

if ($password -eq "") {
    & "C:\xampp\mysql\bin\mysqldump.exe" -u $username $dbName > $backupPath
} else {
    & "C:\xampp\mysql\bin\mysqldump.exe" -u $username -p$password $dbName > $backupPath
}

Write-Host "[SUCCESS] Backup created: $backupPath" -ForegroundColor Green

# Import new database
Write-Host "Importing new database..." -ForegroundColor Yellow

if ($password -eq "") {
    Get-Content $importPath | & $mysql -u $username $dbName
} else {
    Get-Content $importPath | & $mysql -u $username -p$password $dbName
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "[SUCCESS] Database imported successfully!" -ForegroundColor Green
} else {
    Write-Host "[ERROR] Import failed! Restore from backup: $backupPath" -ForegroundColor Red
}
