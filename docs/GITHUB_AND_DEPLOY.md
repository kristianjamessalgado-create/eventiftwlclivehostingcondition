# EVENTIFY — GitHub + laptop / PC / live server

Use **one GitHub repository** for your code. Your **laptop** and **PC** both clone the same repo. The **live site** (GoDaddy) gets files by FTP — it keeps its own `.env` and uploads folder.

## Three places, one codebase

| Place | Path example | Config |
|-------|----------------|--------|
| Laptop (XAMPP) | `C:\xampp\htdocs\school_events` | `.env` from `.env.example` (`BASE_URL=/school_events`) |
| PC (XAMPP) | `C:\xamppfinal\htdocs\school_events` | Same as laptop — copy your local `.env` manually once |
| Live (GoDaddy) | `public_html/` | `.env` from `.env.production.example` — never overwrite from Git |

Secrets (`.env`, `config/*.local.php`, FTP password) are **gitignored** and stay on each machine/server.

---

## Step 1 — Create the GitHub repository (once)

1. Log in to [github.com](https://github.com) → **New repository**
2. Name: `eventifywlc` (or `school_events`)
3. **Private** recommended (school project + config examples)
4. Do **not** add README, .gitignore, or license (this project already has them)
5. Click **Create repository**

---

## Step 2 — Push from this PC (first time)

Open Git Bash or PowerShell in the project folder:

```bash
cd C:\xamppfinal\htdocs\school_events

git init
git add .
git commit -m "Initial commit — EVENTIFY school events app"

git branch -M main
git remote add origin https://github.com/kristianjamessalgado-create/eventiftwlclivehostingcondition.git
git push -u origin main
```

Replace `YOUR_USERNAME` and repo name with yours.

---

## Step 3 — Clone on your laptop

```bash
cd C:\xampp\htdocs
git clone https://github.com/kristianjamessalgado-create/eventiftwlclivehostingcondition.git school_events
cd school_events
composer install
copy .env.example .env
```

Edit `.env` for local XAMPP (`BASE_URL=/school_events`, local DB). Copy any local-only files you still need from the old folder (`.env`, `config/db.local.php` if you use them).

---

## Daily workflow (laptop ↔ PC)

**Before you stop working:**

```bash
git add .
git commit -m "Describe what you changed"
git push
```

**When you sit down at the other computer:**

```bash
git pull
composer install
```

Then test at `http://localhost/school_events/`.

---

## Deploy to live (GoDaddy)

1. Test locally
2. Run `deploy\sync-to-godaddy.bat` (after setting up `deploy/sync-to-godaddy.txt` on **each** machine — that file is not in Git)
3. Hard refresh https://eventifywlc.com/

The sync script skips `.env`, `uploads/`, and local config files so live settings stay safe.

---

## Optional: `production` branch

If you want a branch that always matches what you last deployed:

```bash
git checkout -b production
git push -u origin production
```

After each successful FTP deploy:

```bash
git checkout production
git merge main
git push
```

Most teams only need **`main`** — use whichever you prefer.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `git push` asks for login | Use a [GitHub Personal Access Token](https://github.com/settings/tokens) as the password, or sign in with GitHub Desktop |
| `.env` missing after clone | Copy `.env.example` → `.env` and fill in local values |
| Live site breaks after sync | Never upload `.env` from laptop; live server keeps its own |
| `vendor/` missing | Run `composer install` after every `git pull` |
