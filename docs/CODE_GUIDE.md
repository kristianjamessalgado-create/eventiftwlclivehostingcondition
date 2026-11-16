# EVENTIFY — Code Guide (for explaining the system)

Study notes that explain **what each part of the code does** and **how the pieces connect**.
Read sections 1–4 first (the big picture), then use section 5 as a lookup when someone asks
"what is this file for?".

---

## 1. The big picture

EVENTIFY is a **PHP + MySQL web app** for Western Leyte College events.

| Layer | What we use | Where it lives |
|---|---|---|
| Server code | Plain PHP (no framework) | `backend/`, root `*.php` pages |
| Database | MySQL / MariaDB (`school_events_db.sql`) | connected in `config/db.php` |
| Page design | Bootstrap 5 + our own CSS | `assets/css/` |
| Page behaviour | Plain JavaScript (no React/Vue) | `assets/js/` |
| Calendar | FullCalendar 6 | loaded from a CDN |
| Maps / location | Leaflet + OpenStreetMap | `event_location_picker.js`, `backend/auth/geocode_proxy.php` |
| Hosting | XAMPP locally, GoDaddy cPanel live | `deploy/`, `.htaccess`, `.user.ini` |

**Five user roles** (the `users.role` column):

| Role | What they do |
|---|---|
| `student` | Sees the calendar, RSVPs, checks in with QR, buys tickets, views photos, prints the activity card. |
| `organizer` | Creates events (need admin approval), manages day activities, attendance, tickets. |
| `admin` (teacher) | Approves/rejects events, manages users and sections, analytics, revenue, announcements, stamps activity cards. |
| `super_admin` | Account security (activate, lock/unlock, roles, delete), oversight of all events, messages admins. |
| `multimedia` | Uploads event photos/videos; a **photo moderator** also approves other members' uploads. |

---

## 2. How one page works (controller + view)

Most dashboards are split into **two files**:

1. **Controller** (`backend/...`): checks login and role, reads the database, prepares variables.
2. **View** (`views/...` or `admin/`, `super_admin/`): the HTML that shows those variables.

The controller ends with `include` of the view, so the browser only ever opens the controller URL.

**Example: the organizer dashboard**

```
Browser opens  →  backend/auth/dashboardorganizer.php   (CONTROLLER)
                    • session check: must be role "organizer"
                    • loads the organizer's events, stats, notifications,
                      unread messages, feedback, settings
                    • include → views/dashboardorganizer.php
                                   (VIEW: navbar, sidebar, calendar, modals)
                    • the view loads assets/js/dashboardorganizer.js
                      (calendar clicks, event details, profile modal…)
```

| Dashboard | Controller (URL) | View (HTML) | Main JS / CSS |
|---|---|---|---|
| Student | `backend/auth/dashboard_student.php` | `views/dashboard_student.php` | `dashboard_student.js/.css` |
| Organizer | `backend/auth/dashboardorganizer.php` | `views/dashboardorganizer.php` | `dashboardorganizer.js/.css` |
| Multimedia | `backend/auth/dashboard_multimedia.php` | `views/dashboard_multimedia.php` | `dashboard_multimedia_*.js`, `.css` |
| Admin | `backend/admin/dashboard.php` → `dashboard_boot.php` | `admin/dashboard.php` | `dashboard_admin.js/.css` + `dashboardorganizer.js` (calendar) |
| Super Admin | `backend/super_admin/dashboardsuperadmin.php` | `super_admin/dashboardsuperadmin.php` | `dashboardsuperadmin.js/.css` |

**Actions** (save, delete, approve…) are separate small PHP files that receive a form `POST`,
do one job, then **redirect back** with `?msg=` or `?error=` (shown as a flash message).
Example: `backend/auth/update_organizer_profile.php` saves the profile, then redirects to the
organizer dashboard.

**Partials** (`views/partials/*.php`) are reusable HTML pieces included by several pages,
e.g. `create_event_modal.php` is used by both the admin and organizer dashboards.

**Libraries** (`backend/lib/*.php`) hold shared functions (no HTML), e.g.
`event_calendar.php` knows how to work out whether an event is Upcoming / Active / Ended.

---

## 3. Security basics (good to mention in a defense)

| Protection | Where |
|---|---|
| Passwords hashed with `password_hash()` / checked with `password_verify()` | `backend/auth/auth.php` |
| Login lock after **5 wrong passwords**; unlock only by email OTP | `auth.php`, `backend/lib/account_email_otp.php` |
| Email OTP to activate new accounts | `account_email_otp.php`, `verify_account_otp.php` |
| **CSRF tokens** on every form (`csrf_field()` / `csrf_validate()`) | `config/csrf.php` |
| Role checks at the top of every page/action (`$_SESSION['role']`) | every controller/action file |
| **Prepared statements** (`$conn->prepare` + `bind_param`) against SQL injection | everywhere |
| Output escaped with `htmlspecialchars()` against XSS | views |
| Uploads checked by real file type (`finfo`), safe extension, size limit | upload endpoints |
| Check-in **geofence** (must be near the venue), **device lock**, RSVP requirement | `backend/lib/event_checkin_security.php` |
| Activity log of important actions (who did what, when) | `backend/lib/activity_logger.php` → `activity_logs` table |
| Secrets kept out of git in `.env` | `config/env.php`, `.env.example` |

---

## 4. Main flows (step by step)

### 4.1 Register / log in
1. Landing page `index.php` opens a sign-in / sign-up modal (`assets/js/index.js`).
2. The form posts to `backend/auth/auth.php`.
3. **Sign-up:** account is created inactive → email OTP (`account_email_otp.php`) → `verify_account_otp.php` activates it.
4. **Login:** password checked, `$_SESSION['user_id']` and `$_SESSION['role']` set, user is redirected to their dashboard.
   If `must_change_password = 1` they go to `views/change_password.php` first.
5. Forgot password: `backend/auth/forgot_password.php`.

### 4.2 Create and approve an event
1. **Organizer:** clicks a calendar date or **+** → `views/partials/create_event_modal.php`
   (schedule picker `event_schedule_picker.js`, map `event_location_picker.js`).
2. Form posts to `backend/auth/createevent.php` → event saved with status **`pending`**; admins get a notification.
3. **Admin** reviews it in **Pending Events** (`views/partials/admin_pending_events_*.php`)
   → approve or reject (`backend/super_admin/update_event_status.php`, also used by admins).
4. On approval an **OTP** goes to the organizer's contact (`backend/lib/event_approval_otp.php`, email or SMS).
5. Organizer enters the OTP under **My Events** (`backend/auth/verify_event_approval_otp.php`) → status becomes **`active`** (published).
6. **Admin-created** events skip steps 3–5 and publish immediately.
7. `backend/lib/event_status_auto.php` automatically closes events after they end.

> Status words: in the database `active` = **approved/published**. The screen shows
> **Upcoming** (before start), **Active / Happening now** (running), **Ended** (after),
> using `eventify_event_status_ui()` in `backend/lib/event_calendar.php`.

### 4.3 Attendance (QR check-in)
1. Organizer/admin opens the event QR: `event_qr.php` (main event) or `activity_qr.php` (one activity).
2. Student scans it (phone camera or **Scan QR** in the app, `assets/js/scan_qr.js`).
3. `checkin.php` (main event) or `activity_checkin.php` (activity) records the time in/out after
   security checks in `event_checkin_security.php` (location radius, one device per student, RSVP if required).
4. Staff see results in `event_attendance.php` / `activity_attendance.php` and export reports
   (`backend/lib/attendance_report.php`). Students see theirs in `attendance_history.php`.

### 4.4 Activities hub (day activities inside an event)
- `event_activities.php` = the hub for one event (schedule, activity RSVP, check-in, photos).
- `activities_hub.php` = list of events that have activities.
- Data and rules: `backend/lib/event_day_sessions.php`; API for add/edit/delete: `backend/auth/event_day_sessions_api.php`;
  front-end: `assets/js/event_day_sessions.js`.

### 4.5 Paid tickets (e.g. pageant)
1. Organizer sets ticket types in `manage_event_tickets.php`.
2. Student buys in `event_tickets.php` → `ticket_checkout.php` → pays (`ticket_payment.php`, GCash via PayMongo `backend/lib/paymongo.php`, return `ticket_payment_return.php`, webhook `backend/auth/paymongo_webhook.php`).
3. Student shows the pass `ticket_pass.php`; staff scan it at the door with `scan_tickets.php` (`backend/auth/staff_ticket_scan.php`) or `ticket_checkin.php`.
4. Admin sees money in the **Revenue** panel. Shared logic: `backend/lib/event_ticketing.php`.

### 4.6 Event photos and videos (multimedia)
1. Multimedia uploads in their dashboard → `backend/auth/upload_event_photo.php` (photos + videos).
2. Non-moderators' uploads are **draft**; the **photo moderator** approves/rejects
   (`moderate_event_photo.php`, `moderate_event_photos_bulk.php`).
3. Published media appear to students (`photo_gallery.php`, student dashboard gallery, activities hub).
4. Download as ZIP: `download_event_photos.php`. Shared logic: `backend/lib/event_photos.php`, `multimedia_moderator.php`.

### 4.7 Student activity card (teacher stamp)
1. Student opens `activity_card.php` (Format 1 table or Format 2 per day), filters, prints.
2. The QR on the card opens `activity_card_view.php` for **admins/organizers**, showing only the filtered rows.
3. Teacher presses **Stamp** → saved in `activity_card_stamps`; the stamp then shows on the student's card.
   Logic: `backend/lib/activity_card.php`; shared card HTML: `views/partials/activity_card_sheet.php`.

### 4.8 Staff messages
- Admin ↔ Organizer and Admin ↔ Super Admin chats.
- Rules: `backend/lib/staff_messaging.php`. Endpoints: `backend/messaging/staff_send.php`, `staff_fetch.php`, `staff_mark_read.php`.
- UI: admin panel `views/partials/admin_messages_panel.php`; others use `backend/messaging/staff_messenger.php`; JS `assets/js/staff_messenger.js`.

### 4.9 Notifications and reminders
- In-app bell: `notifications` table, `backend/lib/notifications_service.php`, `notifications_ui.php`, `assets/js/eventify_notifications.js`.
- Phone push (students): `backend/lib/web_push.php` (VAPID keys), `push_subscription_api.php`, service worker `sw-student.js`.
- Reminders before events: `backend/cron/send_event_reminders.php` (run by a cron job).
- Admin announcements to students: `backend/lib/admin_announcements.php`.

---

## 5. File-by-file reference

### 5.1 Root pages (open directly in the browser)

| File | Purpose |
|---|---|
| `index.php` | Landing page: login/sign-up modals, featured photos, about, developers. |
| `activities_hub.php` | Choose an event to browse its day activities. |
| `event_activities.php` | Activities hub for one event (schedule, RSVP, check-in, photos, organizer tools). |
| `event_activities_ics.php` | Download a calendar (.ics) file of activities. |
| `activity_schedule.php` | Printable schedule of an event's activities. |
| `activity_qr.php` / `activity_checkin.php` / `activity_attendance.php` | QR, check-in handler, and attendance list for one **activity**. |
| `event_qr.php` / `checkin.php` / `event_attendance.php` | QR, check-in handler, and attendance list for the **main event**. |
| `event_rsvp.php` | RSVP list for an event (staff). |
| `attendance_history.php` | Student: my check-in history. |
| `activity_card.php` | Student: printable activity card with teacher QR. |
| `activity_card_view.php` | Teacher: view and stamp a student's card (opened from the QR). |
| `event_tickets.php`, `ticket_checkout.php`, `ticket_payment.php`, `ticket_payment_return.php` | Student ticket purchase and GCash payment. |
| `ticket_pass.php` | Student's digital ticket with QR. |
| `ticket_checkin.php`, `scan_tickets.php` | Staff: scan tickets at the entrance. |
| `manage_event_tickets.php` | Organizer: ticket types, confirm payments. |
| `photo_gallery.php` | Student: published photos/videos of one event. |
| `event_photos_qr.php` | QR that opens an event's photo gallery. |
| `upcoming_events.php` | List of upcoming events for any logged-in user. |
| `my_tickets.php`, `student_photos.php` | Old links → now redirect into the student dashboard. |
| `manifest-student.php`, `sw-student.js`, `offline.html` | Student PWA (installable app, offline page, push). |
| `privacy-notice.php`, `terms-and-conditions.php` | Legal pages. |

### 5.2 `backend/auth/` (logged-in user actions + student/organizer/multimedia controllers)

| File | Purpose |
|---|---|
| `auth.php` | Login and registration handler (password check, lockout, redirects by role). |
| `logout.php` | Ends the session. |
| `forgot_password.php`, `change_password.php` | Password reset / forced change. |
| `verify_account_otp.php`, `resend_account_otp.php` | Account activation by email OTP. |
| `dashboard_student.php`, `dashboardorganizer.php`, `dashboard_multimedia.php` | **Controllers** for those dashboards (see section 2). |
| `createevent.php` | Saves a new event (organizer → pending, admin → published). Old page now redirects to the dashboard modal. |
| `edit_event.php` (+ `editevent.php` alias) | Edit an event. |
| `verify_event_approval_otp.php` | Organizer enters approval OTP to publish. |
| `update_organizer_event_status.php` | Organizer ends/reopens their event. |
| `event_day_sessions_api.php` | JSON API for day activities (add/edit/delete/RSVP). |
| `register_event_rsvp.php`, `cancel_event_rsvp.php`, `student_rsvp_ajax.php`, `student_event_rsvp_status.php` | Student RSVP. |
| `submit_event_feedback.php` | Student post-event rating and evaluation. |
| `upload_attendance_proof.php` | Optional photo proof of attendance. |
| `upload_event_photo.php`, `delete_event_photo.php`, `delete_my_event_photos.php`, `delete_gallery_photos.php` | Multimedia uploads and deletes. |
| `moderate_event_photo.php`, `moderate_event_photos_bulk.php`, `publish_event_photos.php` | Photo moderation / publishing. |
| `download_event_photos.php` | ZIP download of an event's media. |
| `update_student_profile.php`, `update_organizer_profile.php`, `update_multimedia_profile.php` | Save profile (name, picture, remove picture). |
| `update_student_settings.php`, `update_organizer_settings.php` | Save preferences (calendar view, etc.). |
| `mark_notification_read.php` | Mark bell notifications read/clear. |
| `push_subscription_api.php`, `vapid_public_key.php` | Register phone push notifications. |
| `student_tickets_api.php` | Student tickets as JSON (offline cache). |
| `staff_ticket_scan.php` | API used by the ticket scanner. |
| `paymongo_webhook.php` | PayMongo tells us a payment succeeded. |
| `geocode_proxy.php` | Address search for the map (OpenStreetMap). |

### 5.3 `backend/admin/` (admin actions)

| File | Purpose |
|---|---|
| `dashboard.php` → `dashboard_boot.php` | Admin **controller**: loads everything, then shows `admin/dashboard.php`. |
| `edit_pending_event.php` | Admin fixes a pending event's details. |
| `assign_event_organizer.php` | Move an event to another organizer. |
| `delete_event.php` | Permanently delete an event (with typed confirmation). |
| `create_announcement.php`, `edit_announcement.php`, `delete_announcement.php` | Announcements to students. |
| `activate_user.php`, `reject_user.php` | Approve or reject new accounts. |
| `update_student_course.php`, `update_student_section.php`, `manage_class_sections.php` | Student course/section management. |
| `update_admin_profile.php`, `update_settings.php` | Admin profile and system settings. |
| `diag_admin.php`, `diag_boot.php`, `dsshboard.php` | Old troubleshooting files / typo redirect (safe to remove later). |

### 5.4 `backend/super_admin/`

| File | Purpose |
|---|---|
| `dashboardsuperadmin.php` | Super admin **controller** (users, security lists, stats, charts). |
| `activate_user.php`, `deactivate_user.php`, `reactivate_user.php`, `unlock_user.php`, `delete_user.php` | Account lifecycle. Reactivation sends an OTP; direct unlock is blocked on purpose. |
| `update_user_role.php`, `update_multimedia_moderator.php` | Change role / set photo moderator. |
| `update_event_status.php`, `update_event_status_bulk.php` | Approve / reject / close events (also used by admin). |
| `manage_events.php` | Older full-page event manager. |

### 5.5 `backend/lib/` (shared logic, no pages)

| File | What it provides |
|---|---|
| `event_calendar.php` | Dates, multi-day schedules, calendar data, **Upcoming/Active/Ended** status. |
| `event_status_auto.php` | Auto-close past events; daily "maintenance" run by dashboards. |
| `event_day_sessions.php` | Day activities: load, save, RSVP, audiences. |
| `event_checkin_security.php` | Geofence, device lock, RSVP requirement for check-in. |
| `event_approval_otp.php` | Generate/send/verify event approval OTPs. |
| `event_organizer_assign.php` | Reassign events to another organizer. |
| `event_ticketing.php`, `paymongo.php` | Tickets, orders, revenue, GCash. |
| `event_photos.php`, `multimedia_moderator.php`, `student_photo_gallery.php` | Photos/videos, moderation, galleries. |
| `event_feedback_schema.php`, `event_evaluation.php` | Feedback and evaluation questions. |
| `event_reminders.php`, `notifications_service.php`, `notifications_ui.php`, `notification_retention.php`, `web_push.php` | Notifications, reminders, push. |
| `attendance_report.php`, `attendance_proof.php` | Attendance reports and proof photos. |
| `activity_card.php` | Activity card rows, QR tokens, teacher stamps. |
| `admin_analytics.php`, `admin_announcements.php`, `admin_event_delete.php`, `admin_pending_event_edit.php` | Admin features. |
| `account_email_otp.php`, `email_sender.php`, `sms_sender.php` | OTP, SMTP email, SMS (Semaphore). |
| `staff_messaging.php` | Who can message whom; inbox data. |
| `profile_picture.php` | Remove picture / safe image extension. |
| `activity_logger.php` | `log_activity()` → `activity_logs`. |
| `nav_helpers.php` | "Back to dashboard" links per role. |

### 5.6 `config/`

| File | Purpose |
|---|---|
| `config.php` | `BASE_URL`, timezone (Asia/Manila), feature switches (geofence, radius, keys). |
| `env.php` | Reads `.env` (passwords/keys not stored in git). |
| `db.php` | Opens the MySQL connection `$conn`. |
| `session.php`, `csrf.php` | Session cookie settings and CSRF helpers. |
| `departments.php`, `organizer_departments.php`, `student_profile_fields.php`, `student_sections.php` | Department, course, year level, and section options. |
| `*.example` files | Templates for local/production secrets (DB, SMTP, PayMongo, VAPID). |
| `hash.php` | Dev helper to make a password hash. |

### 5.7 `views/` and `views/partials/`

- `views/dashboard_*.php`, `views/dashboardorganizer.php`: dashboard **HTML** (see section 2).
- `views/staff_messenger.php`: full-page messages (organizer, super admin).
- `views/change_password.php`: forced password change page.
- `views/login.php`, `views/verify_account_otp.php`, `views/register_event.php`: old links that now redirect.
- **Partials by prefix:**
  - `admin_*`: admin dashboard panels (pending, users, events, analytics, revenue, messages, audit log, feedback, announcements).
  - `multimedia_*`, `photo_*`: multimedia panels and moderation modals.
  - `organizer_*`: organizer My Events, feedback, hub hero/toolbar.
  - `student_*`: student panels (upcoming, tickets, gallery, attendance, hub parts, scan QR footer).
  - `event_*_fields.php`: pieces of the create/edit event form (schedule, audience, check-in times, registration mode).
  - `notification_*`: bell cards, detail modal, clear-all confirm.
  - `create_event_modal.php`, `activity_card_sheet.php`, `scan_qr_modal.php`, `logout_confirm_modal.php`, `calendar_event_state_legend.php`, `standalone_page_shell_*.php`: shared pieces.

### 5.8 `assets/js/` (browser behaviour)

| File | Purpose |
|---|---|
| `dashboardorganizer.js` | Organizer **and admin** calendar, event details modal, profile, filters. |
| `dashboard_admin.js` | Admin panels (pending approvals, users, events filter, charts, confirms). |
| `dashboard_student.js` | Student calendar, panels, RSVP, gallery viewer. |
| `dashboard_multimedia_calendar.js`, `dashboard_multimedia_panels.js` | Multimedia calendar and panel switching. |
| `dashboardsuperadmin.js` | Super admin sidebar, charts, confirms, calendar. |
| `create_event_modal.js`, `event_schedule_picker.js`, `event_location_picker.js` | Create/edit event form: dates, times, map. |
| `event_day_sessions.js`, `event_activities_main_rsvp.js`, `event_activities_hub_nav.js`, `eah_*.js`, `activities_hub_filter.js` | Activities hub. |
| `scan_qr.js` | In-app QR scanner (students check in; staff open activity cards). |
| `activity_card.js` | Activity card filters, Format 2, QR follows the filter. |
| `multimedia_photo_upload_queue.js`, `event_media.js`, `photo_moderation_confirm.js` | Uploads with progress, photo/video display, moderation confirms. |
| `staff_messenger.js` | Messages UI. |
| `eventify_notifications.js`, `eventify_pwa.js` | Bell notifications; student app install/offline/push. |
| `eventify_alert_modal.js`, `eventify_toast.js`, `eventify_spinner.js`, `logout_confirm.js` | Branded pop-ups, toasts, loading, logout confirm. |
| `profile_picture_ui.js` | Profile picture viewer and "Remove photo". |
| `eventify_calendar_colors.js`, `eventify_schedule_display.js`, `eventify_registration_mode.js` | Calendar colors, schedule text, entry-type badges. |
| `eventify_dashboard_keyboard_scroll.js`, `eventify_pull_to_refresh.js` | Keyboard scrolling, pull-to-refresh on phones. |
| `attendance_report.js`, `attendance_proof.js` | Attendance report filters, proof photos. |
| `index.js`, `login.js` | Landing page and auth modals. |

### 5.9 `assets/css/`
One stylesheet per page/feature with matching names (e.g. `dashboardorganizer.css`, `activity_card.css`).
Shared ones: `eventify_modal.css` (all modals), `eventify_dashboard_brand.css` (navbar/sidebar look on all dashboards),
`event_media.css` (photo/video tiles), `calendar_legend.css`, `notifications.css`.
The `?v=12` after a CSS/JS link is a **cache buster**: change the number so phones download the new file.

### 5.10 Other folders

| Folder / file | Purpose |
|---|---|
| `migrations/*.sql` | Small database updates added over time (new tables/columns). Many are also created automatically by code ("ensure" functions). |
| `school_events_db.sql` (+ other root `*.sql`) | Full database export / setup scripts. **Do not import over the live database.** |
| `tools/` | Developer test scripts (DB, SMTP, OTP, VAPID keys, PWA icons). Not used by users. |
| `deploy/` | Notes and a script for uploading to GoDaddy. |
| `docs/` | Demo walkthrough, privacy notice, terms, scope, this guide. |
| `uploads/` | User files: profile pictures, event photos/videos, attendance proofs, message images. |
| `vendor/`, `composer.json` | PHP packages installed by Composer (e.g. web push, mailer). |
| `.htaccess`, `.user.ini` | Apache rules and PHP settings for hosting. |

---

## 6. Database tables (what each one stores)

| Table | Stores |
|---|---|
| `users` | Every account: name, email, password hash, role, status, department, failed attempts, profile picture. |
| `account_email_otps` | Account activation / reactivation codes. |
| `events` | Events: title, dates, times, location + GPS, department/audience, status, organizer, QR token. |
| `event_schedule_dates` | Each day of a multi-day or specific-days event (with its own times). |
| `event_approval_otps` | OTP codes for publishing approved events. |
| `event_day_sessions` (+ `_rsvps`, `_attendance`) | Day activities inside an event, their RSVPs and check-ins. |
| `registrations` | Main-event RSVPs and check-ins (time in / time out, status present). |
| `attendance` | Older attendance records. |
| `event_checkin_device_locks`, `event_session_checkin_device_locks` | One device per student for check-in. |
| `event_feedback` | Ratings, comments, evaluation answers. |
| `event_photos` | Uploaded photos **and videos**, status (draft / published / rejected), captions. |
| `event_ticket_types`, `ticket_orders`, `ticket_order_items`, `event_tickets`, `event_session_ticket_types` | Paid tickets and payments. |
| `notifications` | Bell notifications for every user. |
| `push_subscriptions` | Phones registered for push notifications. |
| `event_reminder_sent`, `activity_reminder_sent` | Prevents sending the same reminder twice. |
| `admin_announcements` | Announcements sent by admins. |
| `staff_messages` | Staff chat messages. |
| `activity_card_share_tokens`, `activity_card_stamps` | Activity card QR links and teacher stamps. |
| `admin_settings`, `organizer_settings`, `student_settings` | Preferences and system settings. |
| `activity_logs` | Audit trail of important actions. |

---

## 7. Quick answers for common questions

- **"What is `dashboardorganizer.php`?"** There are two. `backend/auth/dashboardorganizer.php` is the
  **controller** (security check + database queries for the organizer). `views/dashboardorganizer.php` is the
  **view** (the HTML the organizer sees). The controller includes the view at the end.
- **"How does the system know who is logged in?"** PHP sessions: after login `auth.php` saves
  `$_SESSION['user_id']` and `$_SESSION['role']`; every page checks them first.
- **"How do you stop fake check-ins?"** A QR token per event/activity, a location check (within 300 m of the pin),
  one device per student, check-in only during the event day/time, and optional RSVP requirement.
- **"How are events approved?"** Organizer submits (pending) → admin approves → OTP to organizer → organizer
  enters OTP → published. Admin-created events publish immediately.
- **"Why `?v=` numbers on CSS/JS?"** To force browsers to load the new version after an update.
- **"Where are passwords?"** Only hashed in `users.password`; real secrets (DB, SMTP, keys) are in `.env`, not in git.

---

## 8. Files you can clean up later (not used by the website)

- `CalculatorScreen.kt`, `MainActivity.kt`: Android files unrelated to EVENTIFY.
- `vendor.zip`: a zip of the `vendor/` folder (backup only).
- `backend/admin/diag_admin.php`, `backend/admin/diag_boot.php`: temporary troubleshooting pages (their own comments say "delete after fixing HTTP 500").
- `landing.mp4`, `landing1.mp4`, `landing2.mp4` (project root): no page or script refers to them (the site's video lives in `assets/video/`).
