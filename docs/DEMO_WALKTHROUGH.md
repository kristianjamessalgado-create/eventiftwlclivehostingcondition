# EVENTIFY — Demo Walkthrough

Step-by-step script for capstone presentation and testing. Run through this at least once before defense (October deadline gives you time to repeat it monthly).

**Base URL:** `https://eventifywlc.com/` (production) or `http://localhost/school_events/` (local with `BASE_URL=/school_events` in `.env`)

**Team:** Kristian James Salgado · Deane Christian Camat · Jabes Brom Bernal · Western Leyte College · A.Y. 2026–2027

---

## Before the demo

- [ ] XAMPP: Apache + MySQL running
- [ ] Database imported (`school_events_db.sql`)
- [ ] `config/db.php` connects OK
- [ ] SMTP set up if you will show **registration** or **event OTP** (`config/smtp.local.php`)
- [ ] `uploads/events/` and `uploads/profile_pictures/` exist and are writable
- [ ] Demo accounts ready — one login per role (see below). Write passwords on paper, not in git.
- [ ] Browser: use normal window + incognito (or two browsers) so you can stay logged in as different roles at once
- [ ] Optional: hard refresh (`Ctrl + F5`) if CSS looks wrong

### Accounts to prepare

| Role | Used for |
|------|----------|
| Organizer | Create event |
| Admin | Approve event |
| Student | RSVP, check-in, view photos |
| Multimedia (uploader) | Upload pending photos |
| Multimedia (moderator) | Approve/reject photos — Super Admin turns on **Photo mod** for one user |
| Super Admin | User management, assign photo moderator (setup only) |

---

## Full demo (about 15–20 minutes)

### Part 1 — Organizer creates an event

1. Log in as **organizer**.
2. Open dashboard → **Create Event** (or go to `backend/auth/createevent.php`).
3. Fill in:
   - Title (e.g. `WLC IT Week 2026`)
   - Click a date on the calendar
   - Location (required)
   - Description, times if needed
4. Submit.

**What should happen**

- Success message: event is **pending approval** (no OTP yet — admin sends it later)
- Admin/super admin get a **bell notification** (“New event pending approval”)

**What to say**

> “Organizers don’t publish events directly. Admin reviews first, then sends an OTP for the organizer to verify.”

---

### Part 2 — Admin requests OTP

1. Log in as **admin** (keep organizer tab open or use incognito for organizer).
2. On admin dashboard, open **Pending Approvals** (inbox button / pending modal).
3. Find the event → click **Request OTP** (confirm the prompt).

**What should happen**

- OTP is sent to the **organizer** (email, SMS if configured, and/or bell notification)
- Event stays **pending** until the organizer verifies

**What to say**

> “Admin doesn’t publish the event with one click. They send a verification OTP so we know the organizer approved posting it.”

### Part 2b — Organizer verifies OTP

1. Log in as **organizer**.
2. On dashboard **My Events**, find the pending event.
3. Enter the 6-digit OTP → **Verify OTP**.

**What should happen**

- Event status becomes **active**
- Event shows on public landing calendar and student dashboard
- Admin gets notification (“Event approved via organizer OTP”)

**What to say**

> “Only after the organizer enters the OTP does the event go live on the calendar.”

---

### Part 3 — Student RSVPs

1. Log in as **student**.
2. On student dashboard, open the **calendar** and click the approved event.
3. **RSVP / Register** for the event.

**What should happen**

- RSVP confirmed badge on “My events”
- Organizer may get “New RSVP” notification
- Student can open **Activities** link for that event later

**What to say**

> “Students register through the dashboard calendar. Capacity limits apply if the organizer set max attendees.”

---

### Part 4 — Check-in (QR)

Pick one method for the demo:

**Option A — Event QR (simplest for panel)**

1. As **organizer**, open the event → show **event QR** / check-in link (`event_qr.php` or QR from dashboard).
2. As **student**, scan QR or open check-in URL (`checkin.php?t=...`).
3. Confirm attendance.

**What should happen**

- Check-in success message
- Student notification: attendance confirmed
- If geo is pinned on the event, location may be required within ~300 m

**Option B — Skip live QR**

- Explain QR flow and show the QR on screen without scanning: “Student scans this on event day.”

**What to say**

> “Check-in ties attendance to RSVP so only registered students are counted.”

---

### Part 5 — Multimedia uploads photos

**Setup (once, before demo): Super Admin**

1. Log in as **super admin**.
2. User list → find a multimedia account → enable **Photo mod** (moderator).
3. Use a *different* multimedia account as the uploader (or same account if only one — moderator can approve others’ uploads).

**Upload**

1. Log in as **multimedia** (non-moderator uploader).
2. Dashboard → pick the event → **Upload photos**.
3. Upload one or two images.

**What should happen**

- Photos saved as **pending** (not visible to students yet)
- Uploader sees “Pending” on their cards
- Moderator sees count on **Photo approvals** sidebar

**What to say**

> “Multimedia can’t publish directly. Uploads wait for a teacher-moderator, like a simple approval queue.”

---

### Part 6 — Moderator approves photos

1. Log in as **multimedia moderator** (or open `dashboard_multimedia.php?open_modal=photo_approvals`).
2. Open **Photo approvals** from sidebar.
3. **Approve** one photo (confirm in modal). Optionally **Reject** one to show both flows.

**What should happen**

- Uploader gets **bell notification** (approved / rejected)
- Approved photo status → published for students
- **Photo activity log** (moderator) shows who uploaded and who approved

**What to say**

> “Only published photos appear in the student gallery. Rejected uploads stay hidden.”

---

### Part 7 — Student sees published photos

1. Log in as **student**.
2. Open **Activities hub** for the event: `event_activities.php?id=EVENT_ID`
   - Or use **Activities** from “My events” on the dashboard.
3. Open the **photos / gallery** section.

**What should happen**

- Only **approved** photos visible
- Pending/rejected not shown

**What to say**

> “Students see the final gallery after moderation — not raw uploads.”

---

### Part 8 — Notifications (quick show)

1. On any dashboard, click the **bell** icon.
2. Show unread count, mark read, or clear all.
3. Mention: **Admin → Settings → Notification Retention** — default **30 days**, old alerts auto-deleted.

**What to say**

> “All roles get in-app alerts for important actions. We keep them for 30 days by default so the database doesn’t grow forever.”

---

## Optional extras (if panel asks)

| Feature | Where to show |
|---------|----------------|
| Activities / sessions | Organizer → Activities hub → add session → student activity RSVP → `activity_checkin.php` |
| Paid tickets | Event with ticketing → student checkout → organizer verifies payment |
| Post-event feedback | After event date passes → student dashboard prompts feedback |
| Staff messenger | Admin ↔ organizer chat from dashboards |
| Super admin users | Activate/deactivate accounts, change roles |
| Reject event | Admin pending modal → Reject with reason |
| PWA | Student dashboard → install prompt (needs `assets/pwa/icon-192.png` icons) |

---

## Short 5-minute version (if time is limited)

1. Landing page → log in as organizer → create event  
2. Admin → **Request OTP**  
3. Organizer → enter OTP → event goes live  
4. Student → RSVP on calendar  
5. Multimedia upload → moderator approve  
6. Student activities hub → see photo  
7. Bell notification on uploader  

---

## Demo day checklist

- [ ] Laptop charged, XAMPP started before panel enters
- [ ] Test login for each role (morning of defense)
- [ ] Event date on demo event is today or future (not confusing “past event” UI)
- [ ] At least one photo already uploaded if Wi‑Fi/upload is slow — backup pending item in queue
- [ ] Close unrelated tabs; zoom browser to 100%
- [ ] Have `README.md` and this file open in repo if panel asks about setup

---

## If something breaks live

| Problem | Quick fix |
|---------|-----------|
| “Access denied” | Wrong role logged in — use incognito for second account |
| Event not on calendar | Still **pending** — approve as admin |
| Student can’t RSVP | Event not **active** or wrong date |
| Upload fails | Check `uploads/events/` folder exists |
| No OTP email | Skip OTP in admin settings or show notification bell OTP instead |
| Photos visible too early | Moderator not enabled — Super Admin → Photo mod |
| SQL error | Run missing migration from `README.md` |

Stay calm, switch to the **5-minute version** or screenshots if needed. Panel cares more about you explaining the flow than a perfect live click.

---

## After the demo (for your paper)

Use this walkthrough as the basis for your **Testing** chapter:

- **Test case** = each Part above  
- **Expected result** = “What should happen” bullets  
- **Actual result** = fill in when you practice  
- **Pass/Fail** = tick when rehearsed  

Practice this full script at least **3 times** before October — once in June/July, once August, once the week before defense.
