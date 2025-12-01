<content>
# CRM Ticket System — Step-by-Step Implementation Plan

## 1) Overview & Goals (short)

Build a secure ticketing system with:

* Authentication (register/login)
* User management (admin view)
* Ticket CRUD (name, description, status, file, timestamps)
* Assignment tracking (assigned_to, assigned_at, unassigned_at)
* Strict access control: authors and assignees only
* Guest can only register / login

Production deliverables:

* Code repo
* Live deploy (example: 000webhost)
* Documentation (file structure, install, DB layout, ER diagram)

---

## 2) Tech stack (recommended)

* Backend: PHP (PDO) or Node/Express (choose PHP if you already use it)
* Database: MySQL / MariaDB
* Authentication: server-side sessions (PHP session) or JWT (if API-first)
* Frontend: vanilla HTML/CSS + Bootstrap or small SPA with React/Vue
* Optional: Redis for SSE or notifications
* Host: 000webhost / shared hosting (PHP+MySQL) or Render/Heroku for Node

---

## 3) File / Project structure (5 min)

```
project-root/
├─ public/                  # web root
│  ├─ index.html
│  ├─ app.js
│  └─ css/
├─ public/api/              # API endpoints
│  ├─ auth.php
│  ├─ users.php
│  ├─ tickets.php
│  └─ tickets_stream.php
├─ src/
│  ├─ bootstrap.php        # db connect, session start
│  ├─ middleware.php       # auth helpers
│  └─ models/
│     ├─ Ticket.php
│     └─ User.php
├─ migrations/
│  └─ 001_create_tables.sql
├─ storage/
│  ├─ uploads/
│  └─ logs/
├─ .env
└─ README.md
```

---

## 4) Database design (30 min) — core tables & migrations

### Tables

1. `users`
2. `tickets`
3. `ticket_assignments`

### SQL (migration)

```sql
-- users
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL
);

-- tickets
CREATE TABLE tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  status VARCHAR(30) NOT NULL DEFAULT 'pending', -- allowed: pending,inprogress,completed,onhold
  file_path VARCHAR(512) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  deleted_at DATETIME NULL,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- assignments (history)
CREATE TABLE ticket_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  assigned_to INT UNSIGNED NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  unassigned_at DATETIME NULL,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id),
  FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- index helpful
CREATE INDEX idx_tickets_created_by ON tickets(created_by);
CREATE INDEX idx_ticket_assignments_ticket ON ticket_assignments(ticket_id);
CREATE INDEX idx_ticket_assignments_assigned_to ON ticket_assignments(assigned_to);
```

ER concept: `users (1) — (N) tickets` via `created_by`; `tickets (1) — (N) ticket_assignments` history linking assignee users.

---

## 5) Authentication & Registration (flow)

* **Registration**

  * Route: `POST /api/auth.php?action=register`
  * Fields: `name`, `email`, `password`, `password_confirm`
  * Validate: email format, password strength, unique email
  * Store: `password_hash = password_hash($password, PASSWORD_DEFAULT)` (PHP)
  * Response: success -> optionally log in automatically (create session)

* **Login**

  * Route: `POST /api/auth.php?action=login`
  * Fields: `email`, `password`
  * Verify: fetch user by email, `password_verify`
  * On success: `$_SESSION['user_id'] = $user_id; $_SESSION['is_admin'] = $user->is_admin;`
  * Response: `{success:true, user:{id,name,email,is_admin}}`

* **Logout**

  * Route: `GET /api/auth.php?action=logout` -> `session_destroy()`

* **Guest**

  * Can only access registration and login endpoints and public static pages.

---

## 6) Authorization / Access Control (Security rules)

Implement middleware functions available to every API route.

Core helpers (pseudocode):

```php
function current_user_id(): int { return $_SESSION['user_id'] ?? 0; }
function is_admin(): bool { return !empty($_SESSION['is_admin']); }
function is_author($pdo,$ticketId,$userId) { /* check tickets.created_by */ }
function is_assignee($pdo,$ticketId,$userId) { /* check latest ticket_assignments unassigned_at IS NULL */ }
function ticket_visible_to($pdo,$ticketId,$userId) { return is_author(...) || is_assignee(...); }
```

Enforce:

* **Only author can update all ticket fields** (name, description, file, assign/unassign) — check `is_author()`.
* **Assignee** may update status to `inprogress` or `completed` — check `is_assignee()` before allowing status changes.
* **No user** can see/update other users’ tickets unless they are the author or current assignee. (Admin can optionally bypass.)
* **Guest** limited to register/login.

Important: Always check server-side; **never trust client**.

---

## 7) Ticket Management API (contract)

* `GET /api/tickets.php` — list visible tickets (query: `q`, `limit`, `all=1` if admin)

  * Response: `{success:true, tickets:[...], total: N}`
* `GET /api/tickets.php?id=123` — single ticket (author/assignee or admin)
* `POST /api/tickets.php` — create ticket

  * Body (form-data): `title`, `description`, `file` (optional)
  * Server sets `created_by` = current user
* `POST /api/tickets.php?action=status` — update status

  * Fields: `id`, `status` — allowed statuses validated
  * Authorization:

    * if current user is author → allow any status
    * if assignee → allow only `inprogress` or `completed`
* `POST /api/tickets.php?action=assign` — assign ticket to user

  * Only author (or admin) allowed
  * Implementation: close previous assignment (`unassigned_at = NOW()`), insert new assignment
* `DELETE /api/tickets.php?id=123` — soft delete (set deleted_at), only author or admin

---

## 8) Frontend Forms & Fields (2.5 hrs)

* **Register form**: name, email, password, password_confirm
* **Login form**: email, password
* **Ticket create form**:

  * Fields: `title` (text), `description` (textarea), `file` (file), `submit`
* **Ticket edit form** (author only):

  * Fields: title, description, file replace (optional), status, assign dropdown (users)
* **Ticket list view**:

  * Columns: id, subject, status, author, created_at, assignee, actions(view/edit/close)
* **Ticket view modal**:

  * Show full description, comments (if implemented), assignment history

UX notes:

* Show/hide action buttons based on server response or user role.
* Disable fields on edit if user is assignee but not author (only status permitted).

---

## 9) Validation & Security (1.5 hr)

* **Server-side validation**: required fields, length limits, allowed statuses
* **Sanitize inputs**: use prepared statements (PDO) to prevent SQL injection
* **File uploads**: check MIME type, restrict to safe types, store outside web root or rename and serve via controlled route
* **Session security**: use `session_regenerate_id()` on login, set `session.cookie_secure` and `httponly`
* **Authorization checks**: every API endpoint verifies ownership/assignment
* **Rate limiting** (optional): basic rate limits for auth endpoints
* **Logging**: log exceptions and failed auth attempts

---

## 10) Database operations mapping (CRUD)

* **Create**: insert into `tickets` and optionally `ticket_assignments`
* **Read**: list with `LEFT JOIN` for current assignment (or subselect)
* **Update**:

  * Full update (author): update `name`, `description`, `file_path`, `status`, `updated_at`
  * Status only (assignee): update `status`, `completed_at` when status = completed
  * Assignment: insert new row to `ticket_assignments` + set previous `unassigned_at`
* **Delete**: soft delete `deleted_at = NOW()`

---

## 11) Integration & Tests (3.5 hr)

* Manual test plan:

  1. Register, login, create ticket → should appear in list for author
  2. Another user (assignee candidate) should not see ticket unless assigned
  3. Author assigns to user → assignee should see ticket
  4. Assignee sets status to `inprogress` and then `completed` → allowed
  5. Non-author/non-assignee cannot edit or change status → fail with 403
  6. Soft delete → ticket disappears from lists
* Automated tests (optional): unit test for DB layer, integration tests for API endpoints using PHPUnit or Postman collection

---

## 12) Security Checklist (3 hrs)

* Ensure all endpoints check `current_user_id()` and enforce rules
* Use prepared statements for all DB queries
* Sanitize file names, restrict file types and size
* Session hardening: `cookie_secure`, `httponly`, strict cookie path
* Log errors to `storage/logs` with appropriate permissions
* Optionally add middleware for role checks (admin)

---

## 13) Deployment & Deliverables

* Code repo (Git) with README, migrations, .env.example
* Live site on shared host: upload public/ and configure DB.
* Documentation:

  * File structure
  * Installation steps
  * DB migration SQL
  * ER diagram (textual: users → tickets → ticket_assignments)
  * API endpoints and examples (curl)
* Optional: Docker Compose with PHP+MySQL for local dev

---

## 14) Task breakdown mapped to your 16 hours (one-week, 5 workdays)

A suggested daily schedule that matches ~16 hrs total:

**Day 1 (3.5 hrs)**

* Setup project skeleton + config (`bootstrap.php`) — 0.5 hr
* DB design + create migration — 1 hr
* Implement DB connection + session handling — 0.5 hr
* Implement auth: register/login/logout endpoints — 1.5 hr

**Day 2 (3.0 hrs)**

* Implement tickets API: create, read (list + single) — 1.5 hr
* Implement file upload handling and storage — 0.5 hr
* Implement frontend forms: register/login/create ticket — 1.0 hr

**Day 3 (3.0 hrs)**

* Implement update status & assign endpoints — 1.0 hr
* Implement access control checks (author/assignee) — 1.0 hr
* Implement ticket edit (author) UI & wiring — 1.0 hr

**Day 4 (3.0 hrs)**

* Listing UI, search/filter, pagination/limit — 1.0 hr
* Integration testing & fix bugs — 1.5 hr
* Add logging and error handling — 0.5 hr

**Day 5 (3.5 hrs)**

* Security hardening & middleware (CSP, sessions) — 1.0 hr
* Final polish, documentation, README, migration script — 1.0 hr
* Deploy to free host and test live — 1.0 hr
* Buffer / fixes — 0.5 hr

Total ≈ 16 hours

---

## 15) Acceptance criteria (how you know it's done)

* Users can register/login and get a session cookie.
* Author sees their tickets; assignee sees assigned tickets.
* Author can edit ticket details and assign/unassign.
* Assignee can change status (inprogress/completed) but not edit other fields.
* No user can view or edit tickets they are not authorized for.
* File uploads stored securely and linked to ticket.
* API returns JSON `{success:true, tickets:[], total: N}` consistently.
* Documentation and migrations available in repo.

---

## 16) Example curl flows

**Register**

```bash
curl -X POST -d "name=Alice&email=alice@example.com&password=Pass123" https://example.com/api/auth.php?action=register
```

**Login**

```bash
curl -c cookiejar -X POST -d "email=alice@example.com&password=Pass123" https://example.com/api/auth.php?action=login
```

**Create ticket**

```bash
curl -b cookiejar -F "title=Help me" -F "description=Details" -F "file=@/path/screenshot.png" https://example.com/api/tickets.php
```

**List tickets**

```bash
curl -b cookiejar https://example.com/api/tickets.php
```

---

## 17) Next steps I can do for you (pick one)

* Provide complete `tickets.php` / `auth.php` implementation (PDO + prepared statements) tailored to your current codebase.
* Create React/Bootstrap frontend pages wired to the API.
* Build DB migration SQL file + sample seed data.
* Provide a ready-to-deploy zip configured for 000webhost.

Tell me which part you want next (server code, migrations, frontend pages, or deployment), and I'll produce it immediately.
