# Blood Bank Management System

## Project Overview
The Blood Bank Management System is a web application developed using PHP and MySQL to manage blood donors, donations, patients, blood stock, blood requests, blood issues, and staff accounts.

## Technologies Used
- Frontend: HTML, CSS, JavaScript
- Backend: PHP
- Database: MySQL
- Local Server: XAMPP
- Version Control: Git and GitHub

## Features
- Login and logout
- Dashboard with summary statistics
- Donor registration and management
- Recording blood donations
- Patient registration and management
- Blood stock monitoring
- Blood request management
- Blood issue recording and stock updates
- Staff account management
- Database validation and transaction handling

## Project Structure
- `assets/` — CSS and JavaScript assets
- `config/` — Database configuration
- `pages/` — Application modules
- `dashboard.php` — Main dashboard
- `index.php` — Application entry point
- `login.php` — Login page
- `logout.php` — Logout functionality

## Requirements
- XAMPP
- PHP
- MySQL
- Web browser

## Local Setup
1. Place the project folder inside the XAMPP `htdocs` directory.
2. Create the `BloodBankDB` database in MySQL.
3. Import the required database tables and sample data.
4. Configure your database connection locally in `config/database.php`.
5. Start Apache and the MySQL service.
6. Open `http://localhost:8080/BloodBankProject/` in your browser.

## Security
Do not upload database passwords, access tokens, or other private credentials to GitHub.

## Project Status
The main application modules have been implemented and primary functionality has been tested locally.
