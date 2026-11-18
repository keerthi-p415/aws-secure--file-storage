# aws-secure--file-storage
# Secure File Storage and Management System

A web application where only email-verified, admin-approved users can upload, preview and download files from one shared library. Files are encrypted with AES-256 before they are stored, and every important action is logged.

Built with PHP, MySQL and Nginx on Ubuntu Linux, and deployed on AWS EC2.

## Screenshots
<img width="1920" height="1020" alt="login user" src="https://github.com/user-attachments/assets/a9a42083-977a-48b0-95b2-d5a83c15db94" />
LOGIN PAGE
<img width="1920" height="1020" alt="reg user" src="https://github.com/user-attachments/assets/59352cc8-eacc-4621-8b4c-b9c96221099c" />
REGISTRATION PAGE
<img width="1920" height="1080" alt="dashboard ad" src="https://github.com/user-attachments/assets/8ae88c5d-1ce9-47dc-a6dc-7f9ff4c4f4f4" />
ADMIN DASHBOARD
<img width="1920" height="1020" alt="dashboard user" src="https://github.com/user-attachments/assets/b444d456-6a65-4779-b509-b7b0c9913fb0" />
USER DASHBOARD
<img width="1920" height="1020" alt="profile user" src="https://github.com/user-attachments/assets/4ef91899-07b6-4e8c-ba99-558087f3d8f4" />
PROFILE PAGE
<img width="1920" height="1020" alt="admin records" src="https://github.com/user-attachments/assets/6ff70a68-6527-4d48-ac47-7a75b75ebeca" />
ADMIN RECORDS
<img width="1920" height="1020" alt="all files ad" src="https://github.com/user-attachments/assets/6ec9cb70-dc14-4215-b914-ea1f5ef4da46" />
ALL FILES IN ADMIN
<img width="1920" height="1020" alt="allfiles user" src="https://github.com/user-attachments/assets/6b6a248a-436b-45d6-9c0e-f8f64bd79215" />
ALL FILES IN USER


## Features

- Registration with **OTP email verification** (Gmail SMTP)
- **Admin approval**: new accounts stay Pending until an administrator approves them
- Login with sessions and role-based access (admin and user)
- Upload with validation: PDF, DOCX, PPT, PPTX, JPG, JPEG, PNG, maximum 10 MB
- Shared file library: all approved users can search, preview and download
- Delete allowed only for the file owner and the administrator
- **AES-256-CBC encryption** of every stored file
- Forgot password with OTP, profile page and password change
- Admin dashboard with user approval, charts (Chart.js) and file management
- Admin **Records** page: login, logout, upload, download and delete activity with filters

## Tech Stack

| Layer | Technology |
|---|---|
| OS | Ubuntu Linux |
| Web server | Nginx with PHP-FPM |
| Backend | PHP 8.5 |
| Database | MySQL 8 |
| Frontend | HTML, CSS, JavaScript, Chart.js, Font Awesome |
| Email | PHPMailer with Gmail SMTP |
| Encryption | OpenSSL (AES-256-CBC) |
| Cloud | AWS EC2 |

## Security

- Passwords stored as bcrypt hashes
- Prepared statements (SQL injection protection) and output escaping (XSS protection)
- OTP kept in the server session and valid for 10 minutes
- Extension whitelist and size limit on uploads
- Encrypted files; direct access to the uploads folder blocked in Nginx

## Setup

### 1. Install requirements (Ubuntu)

```bash
sudo apt update
sudo apt install nginx php-fpm php-mysql php-mbstring php-xml php-curl mysql-server composer unzip
```

### 2. Get the code

```bash
git clone https://github.com/keerthi-p415/secure-file-storage.git
sudo mv secure-file-storage /var/www/fileupload
cd /var/www/fileupload
composer require phpmailer/phpmailer
mkdir uploads
sudo chown -R www-data:www-data uploads
```

### 3. Create the database

```bash
sudo mysql -e "CREATE DATABASE file_storage;"
sudo mysql -e "CREATE USER 'filestore_user'@'localhost' IDENTIFIED BY 'your_strong_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON file_storage.* TO 'filestore_user'@'localhost'; FLUSH PRIVILEGES;"
sudo mysql file_storage < schema.sql
```

### 4. Create the configuration files

These files hold secrets and are not part of the repository. Create them yourself.

`db_connect.php`
```php
<?php
$conn = new mysqli("localhost", "filestore_user", "your_strong_password", "file_storage");
if ($conn->connect_error) { die("Database connection failed"); }
```

`mail_config.php` (use a Gmail App Password, not your normal password)
```php
<?php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_PASSWORD', 'your_16_character_app_password');
define('SMTP_PORT', 587);
define('SMTP_FROM_NAME', 'Secure File Storage');
```

`encryption_config.php` (32 characters)
```php
<?php
define('ENCRYPTION_KEY', 'put_a_32_character_secret_key_here');
define('ENCRYPTION_METHOD', 'aes-256-cbc');
```

To get a Gmail App Password: Google Account (myaccount.google.com) → Security → turn on 2-Step Verification → App passwords → Create.

### 5. Configure Nginx and PHP

Point an Nginx server block to `/var/www/fileupload`, pass `.php` files to PHP-FPM, set `client_max_body_size 10M;` and deny access to `/uploads/` and `/vendor/`. In PHP set `upload_max_filesize = 10M` and `post_max_size = 12M`. Then run `sudo nginx -t` and restart Nginx and PHP-FPM.

### 6. Create the first administrator

Register on the site, then promote that account:

```bash
sudo mysql -e "UPDATE file_storage.users SET status='Approved', role='admin', email_verified=1 WHERE username='your_username';"
```

Open `http://your-server/login.php` and log in.

## Project Structure

| File | Purpose |
|---|---|
| register.php, send_otp.php, verify_otp.php | Registration and OTP |
| login.php, logout.php | Authentication |
| forgot_password.php, fp_send_otp.php, fp_verify_otp.php | Password reset |
| dashboard.php, profile.php, file_action.php | User pages, preview and download |
| admin_dashboard.php, admin_files.php, admin_records.php | Admin pages |
| schema.sql | Database tables (no data) |

## Limitations

- Runs over HTTP; HTTPS needs a domain and SSL certificate
- No CSRF tokens or login rate limiting yet
- Files are stored on the server disk, not in cloud storage
- No virus scanning of uploads

## Future Improvements

HTTPS, AWS S3 storage, CSRF protection, rate limiting, two-factor authentication, antivirus scanning, Docker and CI/CD.

## Author

[Keerthi papana] | [GVPCEW/ IT Department]
