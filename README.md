# Aula Kost Management System

A complete boarding house management system with user authentication, room management, payments, announcements, and chat functionality.

## Features

- User authentication (login, register, forgot password)
- Dashboard with overview of costs and transactions
- Room listings and details
- Payment processing
- Announcements
- Group chat
- User profile management

## Database Setup

1. Create a MySQL database named `aula_kost`
2. Import the `database.sql` file to create the necessary tables and sample data

## Installation

1. Clone the repository to your web server directory
2. Configure the database connection in `config/database.php`
3. Make sure your web server has PHP 7.4+ installed
4. Navigate to the project URL in your browser

## Default Admin Account

- Email: admin@auliakost.com
- Password: password

## Directory Structure

- `config/` - Configuration files
- `includes/` - Reusable components
- `pages/` - Page templates
- `assets/` - CSS, JavaScript, and images
- `uploads/` - User uploaded files

## Security Features

- Password hashing using PHP's password_hash()
- CSRF protection
- Session management
- Remember me functionality
- Secure password reset

## License

This project is licensed under the MIT License.

