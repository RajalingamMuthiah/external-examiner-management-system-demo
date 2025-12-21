# MySQL Auto-Shutdown Fix Guide

## Problem Identified
MySQL starts but shuts down automatically after a few seconds.

## Common Causes & Solutions

### Solution 1: Clear Lock Files & Restart (RECOMMENDED - TRY FIRST)
```batch
1. Stop all MySQL processes
2. Delete lock files from c:\xampp\mysql\data\
3. Restart MySQL through XAMPP Control Panel
```

Run the fix script: `fix_mysql.bat`

---

### Solution 2: Fix Corrupted InnoDB Files

**Steps:**
1. **Backup your databases first!**
2. Stop MySQL completely
3. Navigate to: `c:\xampp\mysql\data\`
4. **Delete these files:**
   - `ib_logfile0`
   - `ib_logfile1`
   - `ibdata1` (ONLY if you have backups!)
5. Restart MySQL - it will recreate these files

**PowerShell commands:**
```powershell
Stop-Process -Name mysqld -Force -ErrorAction SilentlyContinue
Remove-Item "c:\xampp\mysql\data\ib_logfile0" -Force
Remove-Item "c:\xampp\mysql\data\ib_logfile1" -Force
Start-Process "c:\xampp\mysql_start.bat"
```

---

### Solution 3: Increase Memory Allocation

Edit `c:\xampp\mysql\bin\my.ini`:

Find and change:
```ini
innodb_buffer_pool_size = 16M
```

Change to:
```ini
innodb_buffer_pool_size = 64M
```

Also add/modify:
```ini
innodb_log_file_size = 5M
innodb_flush_log_at_trx_commit = 2
```

---

### Solution 4: Run as Administrator

1. Open XAMPP Control Panel as **Administrator**
2. Stop MySQL if running
3. Start MySQL
4. Install MySQL as a service: Click "Install" button next to MySQL

---

### Solution 5: Check Disk Space

Ensure you have at least 1GB free space on C: drive

```powershell
Get-PSDrive C | Select-Object Used,Free
```

---

### Solution 6: Disable Antivirus/Firewall Temporarily

Sometimes Windows Defender or antivirus blocks MySQL:
1. Temporarily disable Windows Defender
2. Try starting MySQL
3. If it works, add exception for: `c:\xampp\mysql\bin\mysqld.exe`

---

### Solution 7: Check Windows Event Viewer

1. Open Event Viewer (eventvwr.msc)
2. Go to: Windows Logs → Application
3. Look for MySQL errors with timestamps matching shutdown times

---

## Quick Diagnostic Commands

**Check if MySQL is running:**
```powershell
Get-Process mysqld -ErrorAction SilentlyContinue
```

**View last 30 lines of error log:**
```powershell
Get-Content "c:\xampp\mysql\data\mysql_error.log" -Tail 30
```

**Test MySQL connection:**
```powershell
c:\xampp\mysql\bin\mysql.exe -u root -e "SELECT VERSION();"
```

---

## Most Likely Fix (Based on Your Logs)

Your MySQL starts successfully but something terminates it. Try this order:

1. ✅ **Run fix_mysql.bat** (I created this for you)
2. ✅ **Delete ib_logfile0 and ib_logfile1** (Solution 2)
3. ✅ **Run XAMPP as Administrator** (Solution 4)
4. ✅ **Check Event Viewer** for what's killing MySQL (Solution 7)

---

## Need More Help?

If none of these work, check the full error log:
```
c:\xampp\mysql\data\mysql_error.log
```

Look for lines with `[ERROR]` or `[Warning]` near the bottom of the file.
