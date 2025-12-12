# EEMS Database Migration Runner
# This script executes the database migration for EEMS system

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "EEMS Database Migration" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Paths
$mysqlPath = "C:\xampp\mysql\bin\mysql.exe"
$migrationFile = "C:\xampp\htdocs\external\eems\db\eems_migration_complete.sql"
$database = "eems"
$username = "root"

# Check if MySQL executable exists
if (-not (Test-Path $mysqlPath)) {
    Write-Host "ERROR: MySQL executable not found at $mysqlPath" -ForegroundColor Red
    Write-Host "Please check your XAMPP installation." -ForegroundColor Yellow
    exit 1
}

# Check if migration file exists
if (-not (Test-Path $migrationFile)) {
    Write-Host "ERROR: Migration file not found at $migrationFile" -ForegroundColor Red
    exit 1
}

Write-Host "Starting migration..." -ForegroundColor Green
Write-Host ""

# Execute migration
try {
    Get-Content $migrationFile | & $mysqlPath -u $username $database 2>&1 | Out-String | Write-Host
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "Migration completed successfully!" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "The following changes have been applied:" -ForegroundColor Cyan
        Write-Host "  - Created 'colleges' table" -ForegroundColor White
        Write-Host "  - Created 'departments' table" -ForegroundColor White
        Write-Host "  - Updated 'users' table with college_id and department_id" -ForegroundColor White
        Write-Host "  - Updated 'exams' table with full schema" -ForegroundColor White
        Write-Host "  - Renamed 'assignments' to 'exam_assignments' with full schema" -ForegroundColor White
        Write-Host "  - Created 'faculty_availability' table" -ForegroundColor White
        Write-Host ""
        Write-Host "Next steps:" -ForegroundColor Yellow
        Write-Host "  1. Insert sample colleges and departments data" -ForegroundColor White
        Write-Host "  2. Update existing user records with college_id and department_id" -ForegroundColor White
        Write-Host "  3. Test the login and dashboards" -ForegroundColor White
        Write-Host ""
    } else {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Red
        Write-Host "Migration failed!" -ForegroundColor Red
        Write-Host "========================================" -ForegroundColor Red
        Write-Host ""
        Write-Host "Please check the error messages above." -ForegroundColor Yellow
        Write-Host "Common issues:" -ForegroundColor Cyan
        Write-Host "  - MySQL service not running (start it from XAMPP Control Panel)" -ForegroundColor White
        Write-Host "  - Database 'eems' does not exist (create it first)" -ForegroundColor White
        Write-Host "  - Incorrect MySQL credentials" -ForegroundColor White
        exit 1
    }
} catch {
    Write-Host ""
    Write-Host "ERROR: $_" -ForegroundColor Red
    exit 1
}
