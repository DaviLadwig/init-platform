@echo off
setlocal

cd /d C:\xampp\htdocs\init-platform

if not exist storage\logs (
    mkdir storage\logs
)

C:\xampp\php\php.exe bin\processar-inadimplencia.php >> storage\logs\cron-inadimplencia.log 2>&1

exit /b %ERRORLEVEL%
