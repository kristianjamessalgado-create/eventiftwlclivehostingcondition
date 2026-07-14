# EVENTIFY

School events management system for **Western Leyte College** — capstone project, A.Y. **2026–2027**.

**Kristian James Salgado · Deane Christian Camat · Jabes Brom Bernal**

EVENTIFY handles the full event lifecycle: organizers submit events, admins approve them, students RSVP and check in via QR, multimedia uploads photos (with teacher moderation before publish), and everyone gets in-app bell notifications. Built with PHP, MySQL, and Bootstrap. We run it locally on XAMPP.

Open the app at:

- **Production (domain root):** `https://eventifywlc.com/` — leave `BASE_URL` empty in `.env`
- **Local XAMPP (subfolder):** `http://localhost/school_events/` — set `BASE_URL=/school_events` in `.env`

---

## What you need

- XAMPP (Apache + MySQL + PHP 8 recommended)
- PHP extensions: `mysqli`, `json`, `session`, `gd`, `openssl`
- **Gmail SMTP** for registration OTP and event approval OTP (required for this project; see `config/smtp.local.php.example`)
- SMS (Semaphore) is **not used** — paid per message; documented in [Scope and limitations](docs/SCOPE_AND_LIMITATIONS.md)

---

## Setup (first time)

**1. Put the project in htdocs**

```
C:\xampp\htdocs\school_events
```

**2. Import the database**

Start Apache and MySQL in XAMPP, open phpMyAdmin, create a database called `school_events_db`, then import `school_events_db.sql` from the project root.

**3. Check `config/db.php`**

Default XAMPP settings should work as-is:

```php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "school_events_db";
```

**4. Set `BASE_URL`**

Copy `.env.example` to `.env`:

- **Local XAMPP** (project in `htdocs/school_events`): `BASE_URL=/school_events`
- **Live hosting** (files in `public_html` root): `BASE_URL=` (empty)

If your local folder has a different name, match it: e.g. `BASE_URL=/eventify` for `http://localhost/eventify/`.

**5. Email (needed for register + OTP)**

Copy `config/smtp.local.php.example` to `config/smtp.local.php` and add your SMTP details. We use Gmail App Password for testing. Do not commit this file.

To test if mail works: `http://localhost/school_events/tools/test_smtp.php` (or `/tools/test_smtp.php` on production)

**6. Upload folders**

Make sure these exist and are writable:

- `uploads/profile_pictures/`
- `uploads/events/`

**7. Log in**

Go to your site URL (see **Set `BASE_URL`** above). The old `views/login.php` link just redirects back to the landing page.

---

## Roles

Each role has its own dashboard after login:

- **Student** → `backend/auth/dashboard_student.php`
- **Organizer** → `backend/auth/dashboardorganizer.php`
- **Admin** → `backend/admin/dashboard.php`
- **Multimedia** → `backend/auth/dashboard_multimedia.php`
- **Super Admin** → `backend/super_admin/dashboardsuperadmin.php`

The SQL dump may already have test users, but passwords are hashed so we don't list them here. For defense demo, create one account per role (or use Super Admin → User Management to activate users). New registration needs SMTP for email OTP, then admin approval.

---

## If you get SQL errors after import

A fresh import of `school_events_db.sql` should be enough. If a page says a column or table is missing, run the matching script in phpMyAdmin:

- Most feature patches: files inside `migrations/`
- Photo draft/publish/reject, feedback, RSVP capacity: `school_events_high_value_features.sql`
- Photo moderator column: `migrations/multimedia_moderator.sql`

Only run what the error asks for — no need to run all 14 migration files on a new setup.

---

## Folder overview

```
school_events/
├── index.php              landing page + login/register
├── config/                db, BASE_URL, CSRF, SMTP
├── backend/auth/          login, dashboards, RSVP, photos, check-in
├── backend/admin/         admin dashboard + settings
├── backend/super_admin/   users, events, photo moderator toggle
├── backend/lib/           shared PHP helpers
├── views/                 page templates
├── assets/                CSS and JS
├── uploads/               photos (not in git)
├── migrations/            extra SQL patches
├── docs/                  privacy, terms, demo script, scope & limitations
└── school_events_db.sql   full database for import
```

---

## What the system does

**Events** — Organizers create events with schedules, departments, and capacity. Admins review, assign organizers on pending events, request OTP, or reject. Super Admin can approve directly. Past events auto-mark as completed/closed.

**Students** — RSVP, calendar, QR check-in (optional GPS radius around the venue), tickets, post-event feedback. Student dashboard supports PWA install.

**Activities hub** — Day-of sessions under an event: separate schedule, RSVP, activity check-in, photo status.

**Photos** — Multimedia uploads go to *pending*. Super admin picks a photo moderator. Moderator approves or rejects; students only see published photos. Uploader gets a bell notification.

**Notifications** — Bell icon on dashboards. Admin can set retention (default 30 days); older alerts are deleted automatically.

**Messaging** — Admin and organizer can chat in-app (text only for now).

Privacy notice and terms: `docs/` folder, or `/privacy-notice.php` and `/terms.php` on the site.

**Capstone documentation:**

- [Demo walkthrough](docs/DEMO_WALKTHROUGH.md)
- [Scope and limitations](docs/SCOPE_AND_LIMITATIONS.md)

---

## Demo flow (for presentation)

1. Organizer submits a new event.
2. Admin reviews and clicks **Request OTP**.
3. Organizer enters the OTP on their dashboard — event goes live on the calendar.
4. Student RSVPs and checks in (QR or ticket).
5. Multimedia uploads photos → pending.
6. Moderator approves photos.
7. Student sees published gallery + notification.

---

## Common problems

**Connection failed** — MySQL not running, or wrong name/password in `config/db.php`.

**Styles look broken** — `BASE_URL` doesn't match your folder name under `htdocs`.

**OTP email not arriving** — Set up `config/smtp.local.php`, then try `tools/test_smtp.php`.

**Photo upload fails** — Check `uploads/events/` permissions and admin max upload size in settings.

**Unknown column** — Run the relevant file from `migrations/` (see section above).

**PWA icon missing** — Manifest expects `assets/pwa/icon-192.png` and `icon-512.png`; add those if you want install-to-home-screen to look right.

---

## Notes for developers

Passwords use bcrypt. Forms use CSRF tokens. Don't push `.env` or `config/smtp.local.php` to git. App timezone is **Asia/Manila**.

Notification cleanup and auto-completing past events both run when someone loads a dashboard — no separate cron job needed for local/XAMPP use.

---

## Project info

| | |
|---|---|
| System | EVENTIFY — School Events Management System |
| Developers | Kristian James Salgado, Deane Christian Camat, Jabes Brom Bernal |
| Institution | Western Leyte College |
| Academic year | 2026–2027 |
