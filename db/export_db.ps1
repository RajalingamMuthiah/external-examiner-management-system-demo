# Export Database Script
# Usage: .\db\export_db.ps1

$dbName = "eems"
$username = "root"
$password = ""  # Add your password if needed
$exportPath = "db\latest_db.sql"
$mysqldump = "C:\xampp\mysql\bin\mysqldump.exe"

Write-Host "Exporting database: $dbName" -ForegroundColor Green

if ($password -eq "") {
    & $mysqldump -u $username $dbName > $exportPath
} else {
    & $mysqldump -u $username -p$password $dbName > $exportPath
}

if ($LASTEXITCODE -eq 0) {
    Write-Host "[SUCCESS] Database exported successfully to: $exportPath" -ForegroundColor Green
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "1. git add $exportPath"
    Write-Host "2. git commit -m 'Update database'"
    Write-Host "3. git push origin main"
} else {
    Write-Host "[ERROR] Export failed!" -ForegroundColor Red
}
