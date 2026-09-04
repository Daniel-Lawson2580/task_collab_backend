# Task and Project Collaboration System

A PHP + MySQL web application for managing projects and tasks in teams —
built with plain PHP (no framework), mysqli, and a Trello-style status board.

## Features

- User registration & login (passwords hashed with `password_hash`)
- Create projects and add teammates to them
- Kanban-style board per project: **To Do / In Progress / Done**
- Create tasks with description, assignee, and due date
- Change task status directly from the board (dropdown, auto-saves)
- Task detail page with a comment thread for team discussion
- Overdue tasks are flagged in red automatically

## Tech Stack

- PHP (mysqli, prepared statements throughout — no raw SQL concatenation)
- MySQL / MariaDB
- Plain CSS (no framework), mobile-responsive board layout
- Session-based authentication

## Folder Structure

```
task_collab_system/
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── config/
│   └── db.php              <- database credentials go here
├── includes/
│   ├── functions.php       <- session/auth helpers
│   ├── header.php
│   └── footer.php
├── css/
│   └── style.css
├── sql/
│   └── schema.sql          <- run this to create the database
├── index.php                <- dashboard (project list)
├── create_project.php
├── project.php               <- kanban board for one project
├── task_add.php
├── task_update_status.php
└── task_view.php             <- task detail + comments
```

## Setup Instructions (XAMPP / WAMP)

1. **Copy the project folder**
   Copy the entire `task_collab_system` folder into your web server's
   document root:
   - XAMPP (Windows): `C:\xampp\htdocs\task_collab_system`
   - XAMPP (Mac): `/Applications/XAMPP/htdocs/task_collab_system`
   - WAMP: `C:\wamp64\www\task_collab_system`

2. **Start Apache and MySQL**
   Open the XAMPP/WAMP control panel and start both services.

3. **Create the database**
   - Open `http://localhost/phpmyadmin`
   - Click **Import**, choose `sql/schema.sql`, and click **Go**
   - This creates the `task_collab_system` database with all tables,
     and adds two sample users + a sample project so you have data to
     look at immediately.
   - Sample login: `felix@example.com` / `password123`
     (or `ama@example.com` / `password123`)

4. **Check your database credentials**
   Open `config/db.php` and confirm the values match your setup.
   Defaults (`root` user, no password) work for a fresh XAMPP install.

5. **Open the app in your browser**
   ```
   http://localhost/task_collab_system/
   ```
   You'll be redirected to the login page. Register a new account or
   use one of the sample logins above.

## Notes for Your Report / Presentation

- **Security**: all queries use prepared statements (protects against
  SQL injection); passwords are hashed with bcrypt via `password_hash()`;
  output is escaped with `htmlspecialchars()` (protects against XSS);
  every page checks `require_login()` and project membership before
  showing data, so users can only see projects they belong to.
- **Database design**: five tables — `users`, `projects`,
  `project_members` (many-to-many link table for team membership),
  `tasks`, and `comments` — with foreign keys enforcing referential
  integrity and `ON DELETE CASCADE` so deleting a project cleans up
  its tasks and comments automatically.
- **Possible extensions** if you want to go further for extra marks:
  file attachments on tasks, email notifications, task priority levels,
  drag-and-drop on the board (would need a bit of JavaScript with the
  Fetch API calling `task_update_status.php`), or an admin role that
  can manage all projects.
