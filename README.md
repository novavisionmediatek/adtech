# VidyaSetu – School Management Portal

PHP 8 + SQLite, no external dependencies.

## Run on localhost
First time after downloading from GitHub: copy `config.example.php` to `config.php` (**run.bat** does this for you). `config.php`, `data/` and backups are never uploaded to GitHub.

Double-click **run.bat** (or):
```
C:\php-8.3.33\php.exe -S localhost:8000 -t public public/index.php
```
Open http://localhost:8000. The database and demo data are created automatically on first run.
To reset everything: stop the server, delete the `data` folder, start again.

## Demo logins
All demo accounts share one password. Set it as `demo_password` in `config.php` **before the first run**; if you leave it empty, a random one is made and saved in `data/demo_password.txt`. In demo mode the login page shows it.

| Role | Login |
|---|---|
| Super Admin | superadmin@vidyasetu.test |
| School Admin | admin@dps.test |
| Faculty | anita.verma@dps.test (also rakesh.kumar@, suman.gupta@ ... @dps.test) |
| Student | DPS-6A01 (Student ID, not e-mail) |
| Parent | parent1@example.test |

## E-mail
By default (demo mode) every e-mail is stored in the **Email Log** (admin) / **Alerts received** (parent).
For real delivery edit `config.php` → `smtp` (host, port, user, app-password) and enable `extension=openssl` in php.ini (needed for TLS, e.g. Gmail).

## Modules added later
| Module | Who uses it |
|---|---|
| Forgot password, login limits | everyone (`/forgot`) |
| Exams, marks, report cards | admin creates, faculty enter marks, students/parents see published results |
| Leave requests | faculty and parents apply, admin decides |
| Homework hand-in | students upload, faculty check |
| Fees and receipts (money received at school; no online payment) | admin; students/parents view |
| Online admission (`/admission`), certificates | public, admin |
| Library, transport | admin; students/parents view |
| SMS / WhatsApp alerts (needs a provider in `config.php`; only for parents who agree) | parents, admin log |
| Hindi interface (menus and common screens; dictionary in `app/lang_hi.php`) | everyone |
| Government report, backup, system check | super admin |

**Backups:** a database copy is saved daily in `data/backups/`. Download it, and the uploaded files, from Super Admin > Backup.

**Before real use:** open Super Admin > **System check** on the live site and clear every "Fix".

## Structure
- `public/` – web root (router `index.php`, CSS, JS)
- `app/lib.php` – helpers, auth, DB; `app/mail.php` – mail + SMTP; `app/install.php` – schema + demo data
- `app/pages/{public,super,admin,faculty,student,parent,account}/` – one file per page
- `data/` – SQLite DB and uploaded files (outside web root)

See **GOVERNMENT_PRESENTATION.md** for the proposal / demo script.

## Using MySQL / MariaDB instead of SQLite

SQLite is the default (no setup). To run on MySQL 8 / MariaDB 10.5+:

1. Create a database in your hosting panel (hPanel > Databases), open phpMyAdmin, select that database, then Import `database/vidyasetu_mysql.sql` (no CREATE DATABASE needed).
2. Optional demo data: `database/vidyasetu_demo_data.sql` (kept only on the developer's computer, not on GitHub, because it contains password hashes).
3. In `config.php` set `'db' => ['driver' => 'mysql', 'host' => ..., 'name' => 'vidyasetu', 'user' => ..., 'pass' => ...]`
4. Enable the `pdo_mysql` PHP extension (already on in the bundled PHP 8.3) and restart the server.

Uploaded files (homework, lectures, resumes) always stay on disk in `data/uploads/`; only their names/paths are in the database.
