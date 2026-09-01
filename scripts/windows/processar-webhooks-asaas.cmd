@echo off
setlocal

cd /d C:\xampp\htdocs\init-platform

if not exist storage\logs (
    mkdir storage\logs
)

C:\xampp\php\php.exe bin\processar-webhooks-asaas.php >> storage\logs\webhooks-asaas.log 2>&1

exit /b %ERRORLEVEL%
