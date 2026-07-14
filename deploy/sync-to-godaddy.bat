@echo off
setlocal
set "DIR=%~dp0"
set "SCRIPT=%DIR%sync-to-godaddy.txt"

if not exist "%SCRIPT%" (
    echo.
    echo  First-time setup:
    echo    1. Copy deploy\sync-to-godaddy.example.txt to deploy\sync-to-godaddy.txt
    echo    2. Edit sync-to-godaddy.txt — set FTP username, password, and host
    echo    3. Run this bat file again
    echo.
    pause
    exit /b 1
)

set "WINSCP="
if exist "C:\Program Files (x86)\WinSCP\WinSCP.com" set "WINSCP=C:\Program Files (x86)\WinSCP\WinSCP.com"
if exist "C:\Program Files\WinSCP\WinSCP.com" set "WINSCP=C:\Program Files\WinSCP\WinSCP.com"

if "%WINSCP%"=="" (
    echo WinSCP not found. Install from https://winscp.net/eng/download.php
    echo Then run this script again.
    pause
    exit /b 1
)

echo Uploading changed files to GoDaddy public_html...
"%WINSCP%" /script="%SCRIPT%"
set "ERR=%ERRORLEVEL%"
echo.
if "%ERR%"=="0" (
    echo Done. Open https://eventifywlc.com/ and press Ctrl+F5 to test.
) else (
    echo Upload failed ^(exit code %ERR%^). Check FTP username, password, and host in sync-to-godaddy.txt
)
pause
exit /b %ERR%
