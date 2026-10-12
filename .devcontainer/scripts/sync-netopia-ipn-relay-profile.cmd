@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync-netopia-ipn-relay-profile.ps1"
exit /b %ERRORLEVEL%
