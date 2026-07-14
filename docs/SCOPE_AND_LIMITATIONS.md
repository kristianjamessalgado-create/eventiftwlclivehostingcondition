# EVENTIFY — Scope and Limitations

**System:** EVENTIFY — Web & App-Based School Events Monitoring System  
**Institution:** Western Leyte College  
**Developers:** Kristian James Salgado · Deane Christian Camat · Jabes Brom Bernal  
**Academic Year:** 2026–2027  

This document defines what EVENTIFY is designed to cover (scope) and what it deliberately does not cover or cannot fully guarantee (limitations). It is written for capstone documentation, faculty review, and thesis defense.

---

## 1. Project scope

EVENTIFY is a centralized platform for planning, approving, announcing, and monitoring school events. The system supports five user roles with separate dashboards and permissions.

### 1.1 User roles and access

| Role | In scope |
|------|----------|
| **Student** | Register (with email OTP), browse calendar, RSVP, QR/ticket check-in, activities hub, post-event evaluation, view published photos, in-app notifications |
| **Organizer** | Create and edit own events, verify event approval OTP, manage day activities/sessions, ticket sales (paid events), view attendance and feedback |
| **Admin** | Review pending events, **assign/reassign organizer** on pending events, request organizer OTP, reject events, analytics dashboard, system settings, staff messaging with organizers |
| **Multimedia** | Upload event photos (pending until moderated), manage own uploads |
| **Super Admin** | User management (activate/deactivate, change roles), direct event approve/reject, assign multimedia photo moderator, full system oversight |

### 1.2 Event lifecycle (in scope)

1. **Organizer** submits an event → status **Pending** (not visible to students).
2. **Admin** may **assign the correct organizer** if the wrong account created the event (pending only).
3. **Admin** reviews the submission and sends an **approval OTP** to the assigned organizer via **email and in-app notification** (bell icon).
4. **Organizer** enters the OTP → event becomes **Active** and appears on the student calendar.
5. **Super Admin** may **approve pending events directly** when urgent (bypasses organizer OTP step).
6. Events can be **rejected**, **withdrawn**, or marked **ended/closed** when appropriate.
7. Past events can be auto-marked completed/closed when dashboards load (no separate cron required on XAMPP).

### 1.3 Student participation (in scope)

- Department-filtered event calendar (Google-style views).
- Main-event RSVP and optional **paid ticket** flow with digital pass/QR.
- **QR check-in** for main events and activity sessions (optional GPS geofence when venue coordinates are set).
- **Activities hub** — per-day sessions, session RSVP, personal schedule, `.ics` export.
- **Post-event evaluation** (anonymous to organizers; department visible) for students who checked in.
- **Progressive Web App (PWA)** support on the student dashboard (install to home screen).

### 1.4 Content and communication (in scope)

- **Event photos:** multimedia uploads → **pending** → designated **photo moderator** approves/rejects → students see **published** gallery only.
- **In-app notifications** (bell icon) for approvals, OTP, RSVP, photos, tickets, etc.; configurable retention (default 30 days).
- **Staff messaging** between admin and organizer (text-based).
- **Activity logging** for sensitive actions (approvals, OTP, role changes).

### 1.5 Security and account management (in scope)

- Password hashing (bcrypt), CSRF protection on forms, session-based login.
- **Email OTP** for registration, account reactivation, and **organizer event approval** (primary delivery channel for this project).
- New accounts remain **inactive** until **Super Admin** activates them after email verification.
- Organizer **event approval OTP** ties publishing to verified organizer consent (entered on the organizer dashboard).
- Privacy notice and terms acceptance at registration.

### 1.6 Technical environment (in scope)

- **Web application** built with **PHP**, **MySQL**, **Bootstrap**, and **JavaScript** (FullCalendar, Leaflet maps where used).
- Designed and tested on **XAMPP** (local Apache + MySQL + PHP 8).
- **Gmail SMTP** (or equivalent) for email OTP and alerts — required for registration and event approval OTP in this deployment.
- Responsive layouts for desktop and mobile (admin drawer, student/organizer mobile navigation).

---

## 2. Delimitations (what is outside scope)

The following are **not** part of the current EVENTIFY implementation:

| Topic | Delimitation |
|-------|----------------|
| **Native mobile apps** | No separate Android/iOS app in the app stores; mobile access is via responsive web and PWA. |
| **Automatic event publishing** | Organizers cannot publish events without admin review (OTP or Super Admin approve). |
| **Organizer self-publish (trusted/fast-track)** | No “trusted organizer” tier that skips pending approval. |
| **Multi-school / multi-tenant** | Single-institution deployment (Western Leyte College); not a SaaS for many schools. |
| **SMS / text messaging (Semaphore)** | **Not used in this project.** Third-party SMS (e.g. Semaphore) is **paid** (~₱0.56 per message in the Philippines). EVENTIFY relies on **email OTP**, **in-app notifications**, and dashboard actions instead. Code may include optional SMS hooks for future school deployment, but SMS is **out of scope** for the capstone due to cost. |
| **Admin SMS alerts or approve-by-text** | Admins are not notified or approved via SMS; review happens in the web dashboard only. |
| **Offline mode** | Requires network access to server and database. |
| **Payment gateway integration** | Paid tickets use manual payment confirmation workflow, not automated PayPal/GCash API. |
| **Video livestream / virtual events** | Not included; focus is on physical school events and attendance. |
| **AI scheduling or recommendation** | No automated event suggestion or conflict resolution engine. |
| **Parent/guardian portal** | Only student, staff, and admin roles; no separate parent account type. |
| **Forgot-password self-service** | Password reset via email is listed as future work; reactivation uses OTP for locked accounts. |
| **Bulk data export / formal reporting module** | Basic analytics on admin dashboard; no full PDF/Excel reporting suite. |
| **Real-time chat for students** | Student-to-student or event-wide chat is not included. |
| **Cron-based background jobs** | Maintenance tasks (notification cleanup, status updates) run on dashboard load, not a dedicated job scheduler. |

---

## 3. Limitations

These are constraints of the **current version**, environment, or design choices—not necessarily bugs.

### 3.1 Operational limitations

- **Admin availability:** If no admin or super admin is available, a pending event **cannot go live** until someone with approval access acts (Request OTP or direct Approve).
- **OTP expiry:** Event approval OTPs expire after **10 minutes**; a new OTP must be requested if expired.
- **Single approval path for admin:** Regular admin uses **Request OTP** → organizer verifies; admin cannot “one-click publish” without organizer OTP (Super Admin can approve directly).
- **Organizer tied to creator by default:** Wrong organizer on an event can be fixed by admin **Assign organizer** on pending events; active events cannot be reassigned in the UI.

### 3.2 User and role limitations

- **Admin cannot create users or change roles** — only **Super Admin** manages accounts and role assignment.
- **Registration requires SMTP** (or working PHP `mail()`) for email OTP; without email, self-registration cannot complete verification.
- **Student course/department** must match registration rules; cross-department misuse is limited by form validation, not external identity verification.
- **One photo moderator** flag per multimedia user (assigned by Super Admin); no complex moderation hierarchy.

### 3.3 Technical limitations

- **Local/hosted dependency:** Performance and uptime depend on XAMPP or the server where PHP/MySQL run; no cloud SLA is implied.
- **No SMS delivery:** Event approval OTPs and account OTPs are **not sent by text message** in this deployment. Users who do not receive email must use the **bell notification** (organizer OTP is always stored there when admin requests it) or ask admin to resend.
- **Email deliverability:** OTP and alerts may land in spam; school inboxes may delay delivery by 1–2 minutes. **SMTP must be configured** (`config/smtp.local.php`) for reliable demos.
- **GPS check-in:** Geofence is optional and depends on device location permission and accurate venue coordinates; can be disabled for testing.
- **Browser support:** Best on modern Chromium, Firefox, Safari, and Edge; legacy browsers may not support PWA or all UI features.
- **Concurrent load:** Not load-tested for thousands of simultaneous users; suitable for college-scale deployment with reasonable traffic.
- **File storage:** Uploaded photos and profile pictures are stored on the server filesystem, not cloud object storage.

### 3.4 Security limitations

- **Session-based auth:** Users must log out on shared devices; no hardware-bound MFA beyond email OTP for registration/reactivation.
- **Secrets in config:** SMTP and API keys belong in local config files (`.env`, `smtp.local.php`) and must not be committed to version control.
- **HTTPS:** Production deployment should use HTTPS; local XAMPP demo may run on HTTP.

### 3.5 Academic / project limitations

- **Capstone timeline:** Features are prioritized for demonstration of core monitoring workflow, not enterprise completeness.
- **Test data:** Demo accounts and sample events may not reflect all real-world edge cases (multi-day holidays, room conflicts, etc.).
- **Documentation language:** Primary UI and docs are in **English**; full Filipino localization is not implemented.

---

## 4. Assumptions

EVENTIFY assumes the following conditions during normal operation:

1. The school provides **at least one active Admin** and **one Super Admin** for account and event oversight.
2. Organizers and students have a **valid email** for OTP and alerts; **SMS/phone OTP is not used** in this project (paid third-party cost).
3. Students access the system with **school-appropriate accounts** approved by Super Admin.
4. **Internet access** is available to users during RSVP, check-in, and photo viewing.
5. **Western Leyte College** department and course lists in the database match current academic structure (maintained by administrators).

---

## 5. Recommended future enhancements

These items were discussed during design review and are **out of current scope** but align with EVENTIFY’s goals:

| Enhancement | Rationale |
|-------------|-----------|
| **Admin assign organizer to event** | Correct ownership when the wrong person submitted or office creates on behalf of a club *(implemented for pending events)* |
| **Trusted organizer fast-track** | Allow pre-approved offices to publish without full OTP loop, with audit log |
| **Emergency publish with audit trail** | Documented Super Admin override (partially exists as direct Approve) |
| **Forgot password (email reset)** | Reduce Super Admin workload for routine lockouts |
| **Reports and exports** | Attendance, RSVP, and feedback CSV/PDF for accreditation and records |
| **Backup admin / delegate approver** | Reduce dependency on a single admin account |
| **Automated payment gateway** | For ticketed events with online payment |
| **SMS OTP via Semaphore (or similar)** | If the school allocates budget (~₱0.56/SMS), optional SMS for organizer OTP or admin alerts could be enabled |
| **Scheduled notification cleanup cron** | For production servers without constant dashboard traffic |

Adding **admin assign organizer** would **not** defeat EVENTIFY’s purpose if approval still occurs before students see the event. Allowing **all organizers to self-publish without review** would weaken the **monitoring** objective unless limited to trusted roles with logging. **SMS** was excluded from scope because it is **not free**; email and in-app notifications are the supported channels for this capstone.

---

## 6. Summary statement (for defense)

> **Scope:** EVENTIFY covers centralized school event creation, admin-supervised approval, student RSVP and QR check-in, activity sessions, moderated photo publishing, and role-based dashboards for students, organizers, admins, multimedia staff, and super administrators. OTP and alerts use **email and in-app notifications**, not SMS.  
>  
> **Limitations:** The system depends on admin/super admin availability for publishing, does not use **paid SMS/text messaging** (Semaphore), relies on **SMTP email** and bell notifications for OTP delivery, and is deployed as a single-institution web application on PHP/MySQL. These boundaries keep the project achievable within the capstone timeline while preserving the core goal: **monitoring school events in one place with accountable approval before public visibility.**

---

## Related documents

- [Demo walkthrough](DEMO_WALKTHROUGH.md) — step-by-step presentation script  
- [Data privacy notice](DATA_PRIVACY_NOTICE_EVENTIFY.md)  
- [Terms and conditions](TERMS_AND_CONDITIONS_EVENTIFY.md)  
- [README](../README.md) — setup and feature overview  
