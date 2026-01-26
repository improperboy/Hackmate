# HackMate - Comprehensive Hackathon Management System

HackMate is a complete, feature-rich hackathon management system designed to streamline the organization and participation in hackathon events. Built with PHP, MySQL, and modern web technologies, it provides a comprehensive platform for administrators, mentors, participants, and volunteers.

## 📋 Table of Contents
- [Features](#-features)
- [Folder Structure](#-folder-structure)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage Guide](#-usage-guide)
- [Security](#-security-considerations)
- [Troubleshooting](#-troubleshooting)
- [License](#-license)

## 📁 Folder Structure

```
HackMate/
├── admin/                          # Admin panel files
│   ├── add_user.php               # Add new users
│   ├── ai_mentor_recommendations.php
│   ├── analytics.php              # System analytics
│   ├── blockchain_certificates.php
│   ├── certificate_settings.php
│   ├── certificate_templates.php
│   ├── chatbot_analytics.php
│   ├── dashboard.php              # Admin dashboard
│   ├── export.php                 # Data export
│   ├── export_team_pdf.php
│   ├── floors_rooms.php           # Venue management
│   ├── generate_certificates.php
│   ├── github_repositories.php
│   ├── manage_users.php           # User management
│   ├── mentor_assignments.php
│   ├── mentor_recommendations.php
│   ├── mentoring_rounds.php
│   ├── migrate_user_skills.php
│   ├── posts.php                  # Announcements
│   ├── recent_activity.php
│   ├── sidebar.php                # Admin navigation
│   ├── submission_settings.php
│   ├── support_messages.php
│   ├── system_settings.php        # System configuration
│   ├── team_rankings.php
│   ├── teams.php                  # Team management
│   ├── themes.php                 # Hackathon themes
│   ├── view_announcement.php
│   ├── view_submissions.php
│   ├── view_support_message.php
│   └── volunteer_assignments.php
│
├── ajax/                          # AJAX endpoints
│   ├── create_team.php
│   ├── delete_join_request.php
│   ├── final_submission.php
│   ├── get_team_details.php
│   ├── manage_team_members.php
│   ├── mentor_assignment.php
│   ├── score_submit.php
│   ├── search_users.php
│   ├── send_invitation.php
│   ├── send_join_request.php
│   └── support_message.php
│
├── api/                           # API endpoints
│   ├── chatbot.php               # AI chatbot API
│   ├── github_checker.php        # GitHub validation
│   ├── mentor_recommendations.php
│   └── notifications.php         # Push notifications
│
├── assets/                        # Static assets
│   ├── css/
│   │   ├── style.css
│   │   └── tailwind.css
│   ├── icons/                    # PWA icons
│   │   ├── apple-touch-icon.png
│   │   ├── icon-128x128.png
│   │   ├── icon-144x144.png
│   │   ├── icon-152x152.png
│   │   ├── icon-192x192.png
│   │   ├── icon-384x384.png
│   │   ├── icon-512x512.png
│   │   ├── icon-72x72.png
│   │   ├── icon-96x96.png
│   │   ├── shortcut-dashboard.png
│   │   ├── shortcut-submit.png
│   │   └── shortcut-teams.png
│   └── js/
│       ├── main.js
│       ├── notifications.js
│       ├── pwa.js
│       └── security.js
│
├── includes/                      # Core includes
│   ├── announcement_component.php
│   ├── auth_check.php
│   ├── auth.php
│   ├── chatbot_component.php
│   ├── db.php                    # Database configuration
│   ├── github_checker_component.php
│   ├── maintenance_check.php
│   ├── session_config.php
│   ├── system_settings.php
│   └── utils.php
│
├── lib/                          # Libraries
│   ├── BlockchainCertificate.php
│   └── utils.ts
│
├── mentor/                       # Mentor panel
│   ├── announcements.php
│   ├── assigned_teams.php
│   ├── contact_admin.php
│   ├── dashboard.php
│   ├── debug_teams.php
│   ├── mentor_guidelines.php
│   ├── rankings.php
│   ├── schedule.php
│   ├── score_teams.php
│   ├── scoring_history.php
│   ├── sidebar.php
│   ├── support_messages.php
│   ├── team_progress.php
│   ├── view_announcement.php
│   └── view_support_message.php
│
├── participant/                  # Participant panel
│   ├── announcements.php
│   ├── certificates.php
│   ├── create_team.php
│   ├── dashboard.php
│   ├── join_team.php
│   ├── manage_requests.php
│   ├── mentoring_rounds.php
│   ├── my_join_requests.php
│   ├── rankings.php
│   ├── search_users.php
│   ├── sidebar.php
│   ├── submit_project.php
│   ├── support.php
│   ├── team_actions.php
│   ├── team_details.php
│   ├── team_invitations.php
│   ├── view_announcement.php
│   └── view_support_message.php
│
├── public/                       # Public assets
│   ├── placeholder-logo.png
│   ├── placeholder-logo.svg
│   ├── placeholder-user.jpg
│   ├── placeholder.jpg
│   └── placeholder.svg
│
├── sql/                          # Database files
│   ├── hackmate_schema.sql      # Database schema
│   └── README.md
│
├── styles/                       # Additional styles
│   └── globals.css
│
├── templates/                    # Template files
│   └── default.php
│
├── tmp/                          # Temporary files
│   └── sessions/                # PHP sessions
│
├── uploads/                      # User uploads
│   ├── certificate_templates/   # Certificate PDFs
│   └── certificates/            # Generated certificates
│
├── vendor/                       # Composer dependencies
│   └── (auto-generated)
│
├── .gitignore                   # Git ignore rules
├── .htaccess                    # Apache configuration
├── .htaccess_minimal            # Minimal Apache config
├── announcements.php            # Public announcements
├── change_password.php          # Password change
├── composer.json                # PHP dependencies
├── composer.lock                # Dependency lock file
├── generate_pdf.php             # PDF generation
├── github_checker.php           # GitHub validation
├── index.php                    # Landing page
├── installer.html               # Installation wizard
├── login.php                    # Login page
├── logout.php                   # Logout handler
├── manifest.json                # PWA manifest
├── next.config.mjs              # Next.js config
├── offline.html                 # Offline page
├── QR.html                      # QR code generator
├── QR!.png                      # QR code image
├── README.md                    # This file
├── register.php                 # Registration page
├── setup_ai_recommendations.php # AI setup
├── setup_chatbot.php            # Chatbot setup
├── splash.php                   # Splash screen
├── sw.js                        # Service worker
├── team_rankings.php            # Public rankings
├── unauthorized.php             # Access denied page
├── verify_certificate.php       # Certificate verification
└── view_announcement.php        # View announcements
```

## 📋 Table of Contents
- [Installation Guide](#-installation-guide)
- [Features](#-features)
- [Usage Guide](#-usage-guide)
- [Configuration](#-configuration-options)
- [Troubleshooting](#-troubleshooting)

## Open it in private tab as it is a subdomain
 Live Link: [https://hackmate.ct.ws/](https://hackmate.ct.ws/)
 
 Admin Pannel Credentials
 
 Email: admin@hackathon.com
 Password: password

## 🛠️ Installation Guide

### Prerequisites
Before installing HackMate, ensure you have the following installed on your system:

- **PHP** >= 7.4 (PHP 8.0+ recommended)
- **MySQL** >= 5.7 or **MariaDB** >= 10.2
- **Apache/Nginx** web server
- **Composer** (PHP dependency manager)
- **XAMPP/WAMP/MAMP** (optional, for local development)

### Installation Steps

#### 1. Clone the Repository
```bash
git clone https://github.com/improperboy/Hackmate.git
cd Hackmate
```

#### 2. Install Dependencies
```bash
composer install
```

This will install required packages including:
- DomPDF (for certificate generation)
- PHP libraries for PDF and SVG handling

#### 3. Database Setup

**Option A: Using phpMyAdmin (Recommended for Beginners)**
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `hackmate`
3. Import the database schema:
   - Click on the `hackmate` database
   - Go to "Import" tab
   - Choose file from `sql/` directory
   - Click "Go"

**Option B: Using MySQL Command Line**
```bash
mysql -u root -p
CREATE DATABASE hackmate;
USE hackmate;
SOURCE /path/to/Hackmate/sql/hackmate.sql;
EXIT;
```

#### 4. Database Configuration

Create and configure the database connection file:

1. Navigate to `includes/` directory
2. Create `db.php` (if not exists)
3. Add the following configuration:

```php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Your MySQL username
define('DB_PASS', '');                // Your MySQL password
define('DB_NAME', 'hackmate');        // Database name

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
?>
```

#### 5. Configure File Permissions

Ensure the following directories have write permissions:

**For Linux/Mac:**
```bash
chmod -R 755 uploads/
chmod -R 755 tmp/
chmod -R 755 lib/
```

**For Windows (XAMPP):**
- Right-click on folders → Properties → Security
- Ensure "Users" has "Write" permissions

#### 6. Environment Configuration

**Optional: Set up environment variables**

Create a `.env` file in the root directory (optional):
```env
# Application
APP_NAME=HackMate
APP_URL=http://localhost/Hackmate

# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=hackmate

# Google Gemini AI (Optional)
GEMINI_API_KEY=your_api_key_here

# Email Configuration (Optional)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password
```

#### 7. Web Server Configuration

**For XAMPP:**
1. Copy the project folder to `C:\xampp\htdocs\Hackmate`
2. Start Apache and MySQL from XAMPP Control Panel
3. Access the application at `http://localhost/Hackmate`

**For Production (Apache):**

Edit your `.htaccess` file (already included) or virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName hackmate.local
    DocumentRoot "/path/to/Hackmate"
    
    <Directory "/path/to/Hackmate">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog "/var/log/apache2/hackmate-error.log"
    CustomLog "/var/log/apache2/hackmate-access.log" combined
</VirtualHost>
```

#### 8. Initial Setup

1. Access the application: `http://localhost/Hackmate`
2. Complete the installation wizard (if available)
3. Or manually create an admin account in the database:

```sql
INSERT INTO users (username, email, password, role, status) 
VALUES ('admin', 'admin@hackmate.com', '$2y$10$YourHashedPasswordHere', 'admin', 'active');
```

To generate a hashed password:
```php
<?php
echo password_hash('your_password', PASSWORD_BCRYPT);
?>
```

#### 9. Configure AI Features (Optional)

**Google Gemini AI Setup:**
1. Get an API key from [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Run the chatbot setup: `http://localhost/Hackmate/setup_chatbot.php`
3. Enter your API key when prompted

#### 10. Test Your Installation

1. Login with admin credentials
2. Navigate to Admin Dashboard
3. Create test users with different roles
4. Test core functionalities:
   - Team creation
   - Project submission
   - Certificate generation
   - Notifications

### 🚀 Quick Start After Installation

1. **Create an Event:**
   - Admin Dashboard → Settings → Configure Event Details

2. **Add Users:**
   - Admin Dashboard → Users → Add Participants/Mentors/Volunteers

3. **Configure Teams:**
   - Settings → Team Settings → Set min/max team size

4. **Set Up Venue:**
   - Settings → Manage Floors & Rooms

5. **Enable Features:**
   - AI Chatbot, Notifications, Certificates

### 🌐 Deployment to Production

#### Using cPanel
1. Upload files via File Manager or FTP
2. Import database via phpMyAdmin
3. Update `includes/db.php` with production credentials
4. Set proper file permissions
5. Configure SSL certificate for HTTPS (required for PWA)

#### Using VPS/Cloud Server
```bash
# Update system
sudo apt update && sudo apt upgrade

# Install LAMP stack
sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql

# Install PHP extensions
sudo apt install php-mbstring php-xml php-curl php-zip php-gd

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Clone and setup
git clone https://github.com/improperboy/Hackmate.git /var/www/html/hackmate
cd /var/www/html/hackmate
composer install

# Set permissions
sudo chown -R www-data:www-data /var/www/html/hackmate
sudo chmod -R 755 /var/www/html/hackmate

# Configure Apache virtual host
sudo nano /etc/apache2/sites-available/hackmate.conf
sudo a2ensite hackmate.conf
sudo systemctl reload apache2
```

## 🚀 Features

### 👥 Multi-Role Support
- **Administrators**: Complete event management and oversight
- **Participants**: Team formation, project submission, and collaboration
- **Mentors**: Team guidance, scoring, and feedback
- **Volunteers**: Event support and assistance

### 🏆 Core Functionality

#### Team Management
- **Team Creation & Registration**: Participants can create teams with customizable themes
- **Join Requests**: Smart team joining system with approval workflows
- **Team Invitations**: Leaders can invite specific participants
- **Dynamic Team Sizing**: Configurable min/max team sizes (default: 1-4 members)
- **Location Assignment**: Floor and room allocation for teams
- **Status Tracking**: Pending, approved, and rejected team states

#### Project Submissions
- **GitHub Integration**: Direct repository linking and validation
- **Live Demo Links**: Optional live project demonstrations
- **Tech Stack Documentation**: Detailed technology specifications
- **Submission Deadlines**: Configurable submission windows
- **File Upload Support**: Multiple file formats (PDF, DOC, ZIP, etc.)
- **Submission Analytics**: Track submission rates and statistics

#### Scoring & Evaluation
- **Multi-Round Scoring**: Configurable evaluation rounds
- **Mentor Assignments**: Location-based mentor allocation
- **Weighted Scoring**: Different score weights per round
- **Comment System**: Detailed feedback from mentors
- **Rankings**: Real-time team rankings and leaderboards
- **Score Analytics**: Performance tracking and insights

### 🤖 AI-Powered Features

#### Intelligent Chatbot
- **Google Gemini Integration**: Advanced AI-powered assistance
- **Role-Based Context**: Personalized help based on user role
- **Smart Navigation**: Automatic link generation to relevant features
- **Conversation Logging**: Analytics and improvement tracking
- **Multi-Language Ready**: Extensible for international events

#### AI Mentor Recommendations
- **Skill Matching**: Automatic mentor-participant pairing
- **Compatibility Scoring**: Advanced matching algorithms
- **Preference Learning**: Improves recommendations over time

### 🔐 Blockchain Certificates

#### Secure Certification System
- **SHA-256 Hashing**: Tamper-proof certificate verification
- **PDF Template Management**: Customizable certificate designs
- **Bulk Generation**: Mass certificate creation for events
- **Public Verification**: Anyone can verify certificate authenticity
- **Download Tracking**: Monitor certificate access
- **Revocation System**: Admin control over certificate validity

### 📱 Progressive Web App (PWA)
- **Offline Capability**: Works without internet connection
- **Mobile Responsive**: Optimized for all device sizes
- **App-Like Experience**: Install as native mobile app
- **Push Notifications**: Real-time event updates
- **Fast Loading**: Optimized performance and caching

### 🔔 Communication System

#### Announcements & Notifications
- **Role-Based Targeting**: Send messages to specific user groups
- **Priority Levels**: Urgent, high, medium, low priority messages
- **Push Notifications**: Real-time browser notifications
- **Email Integration**: Optional email notification system
- **Quiet Hours**: Respect user notification preferences

#### Support System
- **Multi-Level Support**: Participant → Volunteer → Mentor → Admin
- **Location-Based Routing**: Support requests based on team location
- **Priority Management**: Urgent support request handling
- **Resolution Tracking**: Complete support ticket lifecycle

### 📊 Analytics & Reporting

#### Comprehensive Dashboard
- **Real-Time Statistics**: Live event metrics and KPIs
- **User Activity Tracking**: Detailed user engagement analytics
- **Team Formation Insights**: Registration and approval trends
- **Submission Analytics**: Project submission tracking
- **Mentor Utilization**: Mentor assignment and activity metrics

#### Advanced Analytics
- **Daily Activity Charts**: Visual representation of event progress
- **Chatbot Analytics**: AI interaction insights
- **Certificate Verification Logs**: Security and usage tracking
- **GitHub Repository Analysis**: Project repository insights

### 🛠️ Administrative Tools

#### User Management
- **Bulk User Creation**: Mass user registration
- **Role Assignment**: Flexible user role management
- **Skill Tracking**: Participant and mentor skill databases
- **Activity Monitoring**: User engagement tracking

#### Event Configuration
- **System Settings**: Comprehensive event customization
- **Theme Management**: Hackathon theme creation and management
- **Floor & Room Management**: Venue layout configuration
- **Submission Settings**: Deadline and requirement management

#### Data Management
- **Export Capabilities**: Team and user data export
- **Backup Systems**: Data protection and recovery
- **Migration Tools**: Easy data transfer between environments

### Security Features
- **Password Hashing**: bcrypt encryption
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Input sanitization
- **CSRF Protection**: Token-based security
- **Role-Based Access Control**: Granular permissions
- **Secure File Uploads**: Validation and sanitization


## � Requirements

### Server Requirements
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.7 or higher / MariaDB 10.2+
- **Apache**: 2.4+ with mod_rewrite enabled
- **Composer**: Latest version
- **SSL Certificate**: Required for PWA features

### PHP Extensions Required
- `mysqli` or `pdo_mysql`
- `mbstring`
- `json`
- `session`
- `gd` or `imagick` (for image processing)
- `zip` (for file handling)
- `curl` (for API calls)

### Recommended Server Configuration
- **Memory Limit**: 256MB minimum
- **Upload Max Filesize**: 50MB
- **Post Max Size**: 50MB
- **Max Execution Time**: 300 seconds
- **Session Save Path**: Writable directory

## 🚀 Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/improperboy/hackmate.git
cd hackmate
```

### Step 2: Install Dependencies

```bash
composer install
```

This will install:
- `dompdf/dompdf` - PDF generation library

### Step 3: Database Setup

1. **Create a MySQL database:**

```sql
CREATE DATABASE hackmate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Import the database schema:**

```bash
mysql -u your_username -p hackmate < sql/hackmate_schema.sql
```

Or use phpMyAdmin to import `sql/hackmate_schema.sql`

### Step 4: Configure Database Connection

1. **Copy the database configuration template:**

```bash
cp includes/db.php.example includes/db.php
```

2. **Edit `includes/db.php` with your database credentials:**

```php
<?php
$host = 'localhost';
$dbname = 'hackmate';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
```

### Step 5: Set Directory Permissions

```bash
# Linux/Mac
chmod 755 uploads/
chmod 755 uploads/certificates/
chmod 755 uploads/certificate_templates/
chmod 755 tmp/
chmod 755 tmp/sessions/

# Windows (Run as Administrator in PowerShell)
icacls uploads /grant Users:F /T
icacls tmp /grant Users:F /T
```

### Step 6: Configure Apache

1. **Enable mod_rewrite:**

```bash
# Linux
sudo a2enmod rewrite
sudo systemctl restart apache2

# The .htaccess file is already included in the project
```

2. **Ensure AllowOverride is set to All in your Apache configuration:**

```apache
<Directory /var/www/html/hackmate>
    AllowOverride All
    Require all granted
</Directory>
```

### Step 7: Configure SSL (Required for PWA)

For production, obtain an SSL certificate:
- Use Let's Encrypt (free): https://letsencrypt.org/
- Or use your hosting provider's SSL

For development:
```bash
# Generate self-signed certificate (development only)
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /path/to/private.key \
  -out /path/to/certificate.crt
```

### Step 8: Initial Setup

1. **Access the installer:**
   - Navigate to: `https://yourdomain.com/installer.html`
   - Or manually create the first admin user in the database

2. **Create admin user manually (alternative):**

```sql
INSERT INTO users (username, email, password, role, created_at) 
VALUES (
    'admin',
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'admin',
    NOW()
);
```

3. **Login with default credentials:**
   - Username: `admin`
   - Password: `password`
   - **IMPORTANT**: Change the password immediately after first login!

### Step 9: Configure Optional Features

#### AI Chatbot (Google Gemini)
1. Get API key from: https://makersuite.google.com/app/apikey
2. Run setup: `https://yourdomain.com/setup_chatbot.php`
3. Enter your API key

#### AI Mentor Recommendations
1. Run setup: `https://yourdomain.com/setup_ai_recommendations.php`
2. Configure matching algorithms

#### Blockchain Certificates
1. Navigate to: Admin Panel → Blockchain Certificates
2. Upload certificate templates
3. Configure certificate settings

### Step 10: System Configuration

1. **Login as admin**
2. **Navigate to System Settings**
3. **Configure:**
   - Event name and description
   - Event dates
   - Team size limits (min/max)
   - Registration settings
   - Submission deadlines
   - Notification preferences

### Step 11: Verify Installation

Check that everything is working:
- ✅ Login page loads
- ✅ Admin dashboard accessible
- ✅ Database connection successful
- ✅ File uploads working
- ✅ PWA manifest loads
- ✅ Service worker registers (check browser console)

## ⚙️ Configuration

### Environment Variables

Create a `.env` file (optional, for advanced configuration):

```env
DB_HOST=localhost
DB_NAME=hackmate
DB_USER=your_username
DB_PASS=your_password

GEMINI_API_KEY=your_api_key_here
SESSION_LIFETIME=3600
UPLOAD_MAX_SIZE=52428800

# Email Configuration (optional)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password
```

### System Settings (via Admin Panel)

After installation, configure these settings in the admin panel:

1. **Event Settings**
   - Event name
   - Start/end dates
   - Registration open/close dates
   - Submission deadlines

2. **Team Settings**
   - Minimum team size (default: 1)
   - Maximum team size (default: 4)
   - Allow solo participants
   - Team approval required

3. **Submission Settings**
   - Required fields
   - File upload limits
   - GitHub repository required
   - Live demo link required

4. **Notification Settings**
   - Email notifications
   - Push notifications
   - Quiet hours

5. **Security Settings**
   - Session timeout
   - Password requirements
   - Two-factor authentication (if enabled)

## 📖 Usage Guide

### For Administrators
1. **Event Setup**: Configure hackathon details, dates, and settings
2. **User Management**: Add participants, mentors, and volunteers
3. **Team Oversight**: Approve team registrations and assignments
4. **Analytics Monitoring**: Track event progress and engagement
5. **Certificate Management**: Generate and manage participant certificates

### For Participants
1. **Registration**: Create account and complete profile
2. **Team Formation**: Create team or join existing ones
3. **Project Development**: Use assigned workspace and mentorship
4. **Submission**: Submit projects before deadline
5. **Certificate Access**: Download completion certificates

### For Mentors
1. **Assignment**: Get assigned to teams based on location/skills
2. **Guidance**: Provide technical and strategic guidance
3. **Scoring**: Evaluate teams across multiple rounds
4. **Feedback**: Provide detailed comments and suggestions

### For Volunteers
1. **Support**: Handle participant questions and issues
2. **Coordination**: Assist with event logistics
3. **Communication**: Bridge between participants and organizers

## 🔧 Configuration Options

### System Settings
- **Event Details**: Name, description, dates
- **Team Limits**: Min/max team sizes
- **Registration**: Open/close registration
- **Visibility**: Control what participants can see
- **Notifications**: Configure communication preferences

### Advanced Features
- **AI Chatbot**: Enable/disable AI assistance
- **Blockchain Certificates**: Configure certificate generation
- **PWA Features**: Control offline capabilities
- **Analytics**: Set tracking preferences

## 🛡️ Security Considerations

### Data Protection
- All passwords are hashed using bcrypt
- SQL injection prevention through prepared statements
- XSS protection via input sanitization
- File upload validation and restrictions
- Secure session management

### Privacy
- User data encryption where applicable
- Configurable data retention policies
- GDPR compliance features
- Audit logging for sensitive operations

## 🔍 Troubleshooting

### Common Issues

**Database Connection Errors**
- Verify database credentials in `includes/db.php`
- Ensure MySQL service is running
- Check database permissions

**File Upload Issues**
- Verify directory permissions (755 or 777)
- Check PHP upload limits in `php.ini`
- Ensure sufficient disk space

**AI Chatbot Not Working**
- Verify Google Gemini API key
- Check internet connectivity
- Review API quota limits

**PWA Installation Issues**
- Ensure HTTPS is enabled
- Verify manifest.json configuration
- Check service worker registration

## 🚀 Performance Optimization

### Database Optimization
- Regular index maintenance
- Query optimization
- Connection pooling
- Automated cleanup scripts

### Caching Strategies
- Browser caching for static assets
- Database query caching
- API response caching
- CDN integration for global events

### Monitoring
- Error logging and monitoring
- Performance metrics tracking
- User activity analytics
- Resource usage monitoring

## 🔮 Future Enhancements

### Planned Features
- **Multi-Language Support**: International event support
- **Advanced Analytics**: Machine learning insights
- **Mobile Apps**: Native iOS/Android applications
- **External Integrations**: Slack, Discord, GitHub Apps
- **Video Conferencing**: Built-in meeting capabilities
- **Blockchain Integration**: External blockchain networks

### API Development
- RESTful API for third-party integrations
- Webhook support for external services
- GraphQL endpoint for flexible queries
- Rate limiting and authentication

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.


## 🙏 Acknowledgments

- **Google Gemini AI**: For powering our intelligent chatbot
- **Tailwind CSS**: For the beautiful, responsive design
- **Chart.js**: For data visualization capabilities
- **Font Awesome**: For comprehensive icon library
- **Open Source Community**: For inspiration and contributions

---

**Built with ❤️ for the hackathon community**

*HackMate - Where Innovation Meets Organization*
