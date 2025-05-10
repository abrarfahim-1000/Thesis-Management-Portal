# Thesis Management System

A web-based Thesis Management System for universities, built using PHP, MySQL (phpMyAdmin), HTML, and CSS. It supports end-to-end management of undergraduate thesis projects for students, faculty, and administrative staff.

## Features

- User authentication (students, faculty)
- Thesis team formation along with self registration and supervision
- Thesis document uploads and progress tracking
- Scheduling and with own supervisor or other faculties
- Feedback and notification system from supervisors to students
- Team collaboration via internal messaging

**More features will be added in future updates**

## Tech Stack

- **Frontend:** HTML, CSS  
- **Backend:** PHP  
- **Database:** MySQL (managed via phpMyAdmin)

## Database Design

The system consists of 14 relational tables with well-defined primary and foreign keys:

- `User`
- `Faculty`
- `Student`
- `Thesis_Team`
- `Supervisor`
- `Co_Supervisor`
- `ThesisDB`
- `Progress_Report`
- `Schedule`
- `Notification`
- `Feedback`
- `Team_Search`
- `Thesis_Document`
- `Faculty_Feedback`

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/thesis-management-system.git
   ```
2. Move the project to your server's root directory (e.g., htdocs for XAMPP).
3. Import the SQL file into phpMyAdmin to create the database and tables.
4. Start Apache and MySQL from your local server environment.
5. Go to `C:\xampp\php\php.ini` and set these variables to the given number (Not necessary if already set to a higher value).
    - upload_max_filesize = 10M
    - post_max_size = 10M
    - memory_limit = 128M
    - max_execution_time = 300 
6. Go to `C:\xampp\mysql\bin\my.ini` and set these variables to the given number (Not necessary if already set to a higher value).
    - max_allowed_packet=64M
7. Access the project in your browser at:
   ```bash
   http://localhost/thesis-helper/
   ```
## Notes

- This is a dummy version for demonstration purposes.
- Passwords are stored in plain text (no hashing).
- Code can be extended for production-grade deployment with additional security and optimization.
