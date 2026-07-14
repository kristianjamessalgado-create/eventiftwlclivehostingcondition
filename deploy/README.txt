EVENTIFY — Deploy to GoDaddy with WinSCP (Option 1)
====================================================

ONE-TIME SETUP (about 10 minutes)
---------------------------------

1) Install WinSCP
   https://winscp.net/eng/download.php
   (Use the default install — includes WinSCP.com for scripts)

2) Get FTP credentials from GoDaddy
   - Log in to GoDaddy → Hosting → cPanel
   - Open "FTP Accounts"
   - Note: FTP server hostname, username, password
   - Remote folder for the site is usually: public_html

3) Test connection in WinSCP (GUI)
   - File protocol: FTP (or FTPS if GoDaddy requires it)
   - Host name: e.g. ftp.eventifywlc.com (see cPanel)
   - User name + password: from FTP Accounts
   - Click Login → you should see public_html on the right

4) Save the session (optional but handy)
   - Session → Save Session As... → name it "Eventify GoDaddy"
   - Next time: double-click the saved session to connect

5) One-click sync script (recommended)
   - Copy sync-to-godaddy.example.txt → sync-to-godaddy.txt
   - Edit sync-to-godaddy.txt: replace USERNAME, PASSWORD, HOST
   - Double-click sync-to-godaddy.bat to upload all changed files

   sync-to-godaddy.txt is gitignored — never commit your password.


EVERY TIME YOU MAKE CHANGES
---------------------------

A) Quick way (script)
   1. Test locally: http://localhost/school_events/
   2. Double-click: deploy\sync-to-godaddy.bat
   3. Hard refresh live site: Ctrl+F5 on https://eventifywlc.com/

B) Manual way (WinSCP window)
   1. Connect to saved session
   2. Left panel:  C:\xamppfinal\htdocs\school_events
   3. Right panel: public_html
   4. Menu: Commands → Synchronize...
   5. Direction: Local → Remote
   6. OK (uploads only changed files)


DO NOT UPLOAD / OVERWRITE ON SERVER
------------------------------------
  .env                          (live BASE_URL and DB settings)
  config/db.local.php
  config/smtp.local.php
  config/paymongo.local.php
  uploads/                      (event photos uploaded by users)

The sync script already skips these.


TROUBLESHOOTING
---------------
  "Login failed"     → Recheck FTP user/password in cPanel
  "Host not found"   → Use exact hostname from cPanel FTP page
  Site still old     → Ctrl+F5 on browser; clear cache on phone
  Password special   → In sync script URL-encode chars like @ # % in password
