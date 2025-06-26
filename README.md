# 🏦 ATM Management System

A complete ATM (Automated Teller Machine) management system built with PHP, SQLite, and modern web technologies. This system provides comprehensive banking functionality for users, managers, and administrators.

## ✨ Features

### 🔐 Authentication & Security
- **Multi-role authentication** (Admin, Manager, User)
- **CSRF protection** for all forms
- **Session management** with secure logout
- **Input validation** and sanitization
- **Password hashing** using PHP's built-in functions

### 👤 User Features
- **Withdraw money** from account with validation
- **Transfer money** to other users
- **View transaction history** with filtering and search
- **Real-time balance** updates
- **Account status** monitoring

### 👨‍💼 Manager Features
- **User management** (create, view, update users)
- **Transaction monitoring** and approval
- **Account status** management
- **Financial reports** and analytics

### 👨‍💻 Admin Features
- **Complete system oversight**
- **Manager account management**
- **System health monitoring**
- **Transaction analytics** with animated charts
- **Database viewer** for system administration

### 📊 Analytics & Reporting
- **Animated pie charts** showing transaction distribution
- **Real-time statistics** dashboard
- **Transaction filtering** by type and date
- **Financial insights** and spending patterns

## 🛠️ Technology Stack

- **Backend**: PHP 7.4+
- **Database**: SQLite
- **Frontend**: HTML5, CSS3, JavaScript
- **UI Framework**: Tailwind CSS
- **Charts**: Chart.js
- **Icons**: FontAwesome 6.4.0

## 📁 Project Structure

```
atm-system/
├── admin/                 # Admin dashboard and functions
│   ├── index.php         # Admin main dashboard
│   ├── create_user.php   # User creation interface
│   ├── managers.php      # Manager management
│   └── pending_approvals.php
├── user/                  # User interface
│   ├── index.php         # User dashboard
│   ├── withdraw.php      # Money withdrawal
│   ├── transfer.php      # Money transfer
│   ├── history.php       # Transaction history
│   └── register.php      # User registration
├── manager/              # Manager interface
│   ├── index.php         # Manager dashboard
│   ├── create_user.php   # User creation
│   ├── transactions.php  # Transaction management
│   └── pending_approvals.php
├── includes/             # Core system files
│   ├── config.php        # Database configuration
│   ├── auth.php          # Authentication functions
│   ├── functions.php     # Utility functions
│   ├── header.php        # Common header
│   └── footer.php        # Common footer
├── assets/               # Static assets
│   ├── css/             # Stylesheets
│   ├── js/              # JavaScript files
│   └── images/          # Images and icons
├── database.sqlite       # SQLite database
├── database.sql          # Database schema
├── setup.php            # Initial setup script
└── README.md            # This file
```

## 🚀 Installation

### Prerequisites
- PHP 7.4 or higher
- SQLite extension enabled
- Web server (Apache/Nginx) or PHP built-in server

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/atm-system.git
   cd atm-system
   ```

2. **Set up the database**
   ```bash
   # Run the setup script
   php setup.php
   ```

3. **Start the development server**
   ```bash
   php -S localhost:8000
   ```

4. **Access the system**
   - Open your browser and go to `http://localhost:8000`
   - Use the default admin credentials (created during setup)

## 🔧 Configuration

### Database Configuration
The system uses SQLite by default. Database configuration is in `includes/config.php`:

```php
$db_path = __DIR__ . '/../database.sqlite';
$conn = new PDO('sqlite:' . $db_path);
```

### Security Settings
Update security settings in `includes/config.php`:

```php
define('SECRET_KEY', 'your-secret-key-here');
define('CSRF_TOKEN_EXPIRE', 3600); // 1 hour
```

## 👥 User Roles & Permissions

### Admin
- Full system access
- Create/manage managers and users
- View all transactions and analytics
- System health monitoring

### Manager
- Create and manage user accounts
- Approve pending transactions
- View assigned user transactions
- Basic reporting

### User
- Withdraw money from account
- Transfer money to other users
- View personal transaction history
- Check account balance

## 📊 Database Schema

### Main Tables
- **users**: Customer account information
- **managers**: Manager account information
- **admin**: Administrator accounts
- **transactions**: All financial transactions
- **activity_logs**: System activity tracking

## 🔒 Security Features

- **Password Hashing**: All passwords are hashed using PHP's `password_hash()`
- **CSRF Protection**: All forms include CSRF tokens
- **Input Validation**: All user inputs are validated and sanitized
- **Session Security**: Secure session management with proper logout
- **SQL Injection Prevention**: Prepared statements for all database queries

## 🎨 UI/UX Features

- **Responsive Design**: Works on desktop, tablet, and mobile
- **Modern Interface**: Clean, professional design using Tailwind CSS
- **Interactive Charts**: Animated pie charts for transaction analytics
- **Real-time Updates**: Live balance and transaction updates
- **Intuitive Navigation**: Easy-to-use interface for all user types

## 🚀 Deployment

### Shared Hosting
1. Upload all files to your web server
2. Ensure PHP and SQLite are enabled
3. Run `setup.php` to initialize the database
4. Update database path in `includes/config.php` if needed

### Cloud Platforms
This system is compatible with:
- **Railway** (Recommended)
- **Render**
- **Heroku** (with add-ons)
- **DigitalOcean App Platform**

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure SQLite extension is enabled
   - Check file permissions for database.sqlite

2. **Session Issues**
   - Verify session directory is writable
   - Check session configuration in PHP

3. **Permission Denied**
   - Set proper file permissions (755 for directories, 644 for files)
   - Ensure web server can write to database file

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Tailwind CSS** for the beautiful UI framework
- **Chart.js** for interactive charts
- **FontAwesome** for the icon library
- **PHP** community for excellent documentation

## 📞 Support

If you encounter any issues or have questions:
1. Check the troubleshooting section above
2. Review the code comments for guidance
3. Open an issue on GitHub

---

**Built with ❤️ using modern web technologies**