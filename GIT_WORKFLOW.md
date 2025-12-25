# EEMS Git Workflow Guide

## Setup Instructions

### PC 1 (Source with Database)

**Step 1:** Run Git Setup
```bash
cd C:\xampp\htdocs\eems
git_setup_pc1.bat
```

This will:
- Initialize Git repository
- Export the complete database
- Commit files to Git

---

### PC 2 (Destination - Empty)

**Step 1:** Run Git Import
```bash
cd C:\xampp\htdocs\eems
git_import_pc2.bat
```

When prompted, enter the Git source. Choose one option:

---

## 3 Transfer Methods

### **Option A: Local Network Folder (Fastest)**

**On PC 1:**
1. Right-click `C:\xampp\htdocs\eems` folder
2. Properties → Sharing → "Share this folder"
3. Add "Everyone" with Read/Write permissions
4. Get the network path: `\\PC1-NAME\eems`

**On PC 2:**
1. Run `git_import_pc2.bat`
2. Enter: `\\PC1-NAME\eems` (replace PC1-NAME with actual computer name)
3. Done! Database imports automatically

---

### **Option B: GitHub (Best for Backup)**

**On PC 1:**
1. Create repository at github.com
2. Open PowerShell in `C:\xampp\htdocs\eems`
3. Run these commands:
```bash
git remote add origin https://github.com/yourname/eems.git
git branch -M main
git push -u origin main
```

**On PC 2:**
1. Run `git_import_pc2.bat`
2. Enter: `https://github.com/yourname/eems.git`
3. Done!

---

### **Option C: USB Drive / External Storage**

**On PC 1:**
1. Copy entire `C:\xampp\htdocs\eems` folder to USB
2. USB now contains complete Git repo with database

**On PC 2:**
1. Copy from USB to: `C:\xampp\htdocs\`
2. Open PowerShell in that folder
3. Run: `git pull` to sync latest

---

## After Import on PC 2

The database will be automatically imported. Then:

1. **Copy PHP files** from cloned repo to your EEMS directory
2. **Or use** the cloned folder directly as your EEMS

```bash
REM Copy cloned files to main EEMS folder
copy eems_repo\*.php C:\xampp\htdocs\eems\
copy eems_repo\api C:\xampp\htdocs\eems\api\
copy eems_repo\config C:\xampp\htdocs\eems\config\
copy eems_repo\includes C:\xampp\htdocs\eems\includes\
```

---

## Sync Updates

Whenever PC 1 changes the data:

**On PC 1:**
```bash
git add .
git commit -m "Update database and files"
git push origin main
```

**On PC 2:**
```bash
git pull origin main
```

This pulls all latest data!

---

## Advantages of Git Method

✅ Version control - Track all changes  
✅ Easy sync - `git pull` to get latest  
✅ Backup - Everything is tracked  
✅ Collaboration - Multiple users can sync  
✅ Revert - Undo unwanted changes  

---

## Troubleshooting

**Git not found?**
```bash
Download from: https://git-scm.com/download/win
```

**Network path not working?**
```bash
Check computer name:
  Win + X → System
  Look for "Computer name"
  
Format: \\COMPUTERNAME\eems
```

**GitHub authentication failed?**
```bash
Use Personal Access Token instead of password
Create at: github.com/settings/tokens
```

---

**Questions? Run the batch files - they'll guide you through!**
