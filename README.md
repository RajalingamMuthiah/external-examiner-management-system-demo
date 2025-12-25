# EEMS - Exam Examiner Management System

A comprehensive web-based examination management system designed for educational institutions to manage exams, faculty assignments, and examiner nominations efficiently.

## 📋 Overview

EEMS is a role-based exam management platform that streamlines the entire examination lifecycle - from exam creation to examiner assignment, scheduling, and reporting. The system supports multiple user roles with granular permissions and provides automated workflows for efficient exam administration.

## ✨ Key Features

### Multi-Role Dashboard System
- **Administrator**: Complete system oversight, user verification, permission management
- **Principal**: Exam approval, institutional overview, faculty management
- **Vice Principal**: Department-wise exam oversight, examiner request management
- **HOD (Head of Department)**: Faculty nominations, availability management
- **Teacher/Faculty**: Exam assignment acceptance, availability declaration

### Core Functionality
- ✅ User registration and multi-level verification
- 📝 Dynamic exam creation with customizable parameters
- 👥 Automated faculty assignment and workload balancing
- 📧 Email notifications for invitations and updates
- 📊 Comprehensive analytics and reporting
- 🔒 Role-based access control with granular permissions
- 📄 Document generation (PDF exports)
- ⭐ Examiner rating system
- 🔍 Advanced search and filtering
- 📋 Audit trail for all administrative actions
- 🔐 Privacy enforcement and data protection

### Advanced Features
- Inter-college exam visibility and examiner sharing
- Real-time notifications system
- Bulk user operations
- Deployment diagnostics
- Security audit tools
- Performance testing suite
- UAT (User Acceptance Testing) dashboard

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Libraries**: jQuery, Chart.js, Bootstrap Icons
- **Server**: Apache (XAMPP)
- **Version Control**: Git/GitHub

## 📦 Requirements

### System Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Apache**: 2.4+
- **RAM**: 2GB minimum (4GB recommended)
- **Disk Space**: 500MB minimum

### PHP Extensions
- PDO
- MySQLi
- JSON
- Session
- OpenSSL
- mbstring

## 🚀 Installation

### 1. Clone the Repository
```bash
cd C:\xampp\htdocs\external
git clone https://github.com/RajalingamMuthiah/eems.git
cd eems
```

### 2. Database Setup
```bash
# Start XAMPP services
# Open phpMyAdmin: http://localhost/phpmyadmin

# Create database
CREATE DATABASE eems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Import schema (choose one):
mysql -u root -p eems < db/complete_eems_schema.sql
# OR
mysql -u root -p eems < db/latest_db.sql
```

### 3. Configuration

#### Database Configuration
Edit `config/db.php`:
```php
$host = 'localhost';
$dbname = 'eems';
$username = 'root';
$password = ''; // Your MySQL password
```

#### Email Configuration
Edit `config.json` for email notifications:
```json
{
  "email": {
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_user": "your-email@gmail.com",
    "smtp_pass": "your-app-password"
  }
}
```

### 4. Permissions Setup
```bash
# Ensure write permissions for logs directory
chmod -R 755 logs/
```

### 5. Access the Application
```
http://localhost/external/eems/
```

## 👤 Default Users

After database import, you can use these default credentials:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@eems.local | admin123 |
| Principal | principal@college.edu | Welcome@123 |
| Vice Principal | vp@college.edu | Welcome@123 |

⚠️ **Important**: Change default passwords immediately after first login!

## 📁 Project Structure

```
eems/
├── admin_dashboard.php          # Admin control panel
├── dashboard.php                # Principal dashboard
├── VP.php                       # Vice Principal dashboard
├── hod_dashboard.php           # HOD dashboard
├── teacher_dashboard.php       # Teacher dashboard
├── login.php                   # Authentication
├── register.php                # User registration
├── config/
│   └── db.php                  # Database configuration
├── includes/
│   └── functions.php           # Core functions
├── api/
│   ├── colleges.php            # College API
│   ├── invites.php             # Invitation management
│   ├── notifications.php       # Notification system
│   └── generate_document.php   # PDF generation
├── db/
│   ├── complete_eems_schema.sql  # Full database schema
│   └── latest_db.sql             # Latest DB snapshot
├── modules/                    # Feature modules
├── public/                     # Public assets (CSS, JS, images)
├── logs/                       # Application logs
└── docs/                       # Documentation
```

## 📖 User Guides

### For Administrators
See [ADMINISTRATOR_GUIDE.md](ADMINISTRATOR_GUIDE.md) for:
- User verification workflows
- Permission management
- System configuration
- Audit log monitoring

### For Colleges
See [COLLEGE_ASSIGNMENT_GUIDE.md](COLLEGE_ASSIGNMENT_GUIDE.md) for:
- College setup procedures
- Department configuration
- Faculty onboarding

### For End Users
See [USER_MANUAL.md](USER_MANUAL.md) for:
- Registration process
- Dashboard navigation
- Exam assignment workflow
- Profile management

## 🔧 Configuration Guides

- **[DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)**: Production deployment steps
- **[MYSQL_FIX_GUIDE.md](MYSQL_FIX_GUIDE.md)**: Database troubleshooting
- **[PRIVACY_QUICK_REFERENCE.md](PRIVACY_QUICK_REFERENCE.md)**: Privacy compliance
- **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)**: API endpoints reference

## 🔐 Security Features

- Password hashing with bcrypt
- CSRF token protection
- SQL injection prevention (PDO prepared statements)
- XSS protection (input sanitization)
- Session security with secure flags
- Role-based access control (RBAC)
- Audit logging for all critical actions
- Privacy enforcement mechanisms

## 🧪 Testing

### Run Security Audit
```bash
php security_audit.php
```

### Run Performance Tests
```bash
php test_performance.php
```

### Run E2E Tests
```bash
php test_e2e.php
```

### UAT Dashboard
Access: `http://localhost/external/eems/uat_dashboard.php`

## 🐛 Troubleshooting

### Common Issues

**Issue**: Cannot connect to database
```bash
# Check MySQL is running
net start MySQL

# Or use XAMPP fix script
fix_mysql.bat
```

**Issue**: phpMyAdmin not accessible
```bash
# Run fix script
fix_phpmyadmin.bat
```

**Issue**: Session errors
- Clear browser cookies
- Check `session.save_path` in php.ini
- Ensure proper file permissions

**Issue**: Email not sending
- Check SMTP credentials in config.json
- Enable "Less secure app access" for Gmail
- Or use App Passwords

## 📊 Database Maintenance

### Export Database
```powershell
.\db\export_db.ps1
```

### Import Database
```powershell
.\db\import_db.ps1
```

### Backup
Regular backups are stored in `db/backups/` directory

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Coding Standards
- Follow PSR-12 coding standards for PHP
- Use meaningful variable and function names
- Comment complex logic
- Write secure code (prevent SQL injection, XSS)
- Test before committing

## 📝 Changelog

See [PROGRESS_REPORT.md](PROGRESS_REPORT.md) and [PROJECT_COMPLETION_SUMMARY.md](PROJECT_COMPLETION_SUMMARY.md) for detailed change history.

## 🔮 Roadmap

- [ ] Mobile responsive design improvements
- [ ] REST API for mobile app integration
- [ ] Real-time notifications with WebSockets
- [ ] Advanced analytics with data visualization
- [ ] Multi-language support
- [ ] Dark mode theme
- [ ] Automated exam scheduling
- [ ] Integration with LMS platforms

## 📄 License

This project is proprietary software. All rights reserved.

## 👥 Team

**Developer**: Rajalingam Muthiah  
**Email**: arjunmudaliyar99@gmail.com  
**GitHub**: [RajalingamMuthiah](https://github.com/RajalingamMuthiah)

## 🆘 Support

For support, please:
1. Check the documentation in the `docs/` folder
2. Review troubleshooting guides
3. Open an issue on GitHub
4. Contact the development team

## 🙏 Acknowledgments

- Bootstrap team for the UI framework
- Chart.js for analytics visualization
- PHP community for excellent documentation
- All contributors and testers

---

**Version**: 2.0  
**Last Updated**: December 2025  
**Status**: Production Ready

## Quick Start Commands

```bash
# Clone and setup
git clone https://github.com/RajalingamMuthiah/eems.git
cd eems

# Start XAMPP
open_eems.bat

# Access application
start http://localhost/external/eems/

# Run diagnostics
php diagnostic.php
```

## Support Links

- 📚 [Full Documentation](docs/)
- 🐛 [Report Bug](https://github.com/RajalingamMuthiah/eems/issues)
- 💡 [Request Feature](https://github.com/RajalingamMuthiah/eems/issues)
- 📧 [Email Support](mailto:arjunmudaliyar99@gmail.com)

---

**Made with ❤️ for Educational Institutions**
