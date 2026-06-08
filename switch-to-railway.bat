@echo off
echo =======================================================
echo Switching to RAILWAY Production Server...
echo =======================================================
echo.

node switch_env.js railway

echo.
echo Server URLs:
echo - Admin Portal: https://api.veeruapp.in/backend/admin
echo - Student App:  https://api.veeruapp.in/backend/api
echo - Teacher App:  https://api.veeruapp.in/backend/api
echo.
echo ⚠️  Make sure Railway deployment is active!
echo.
pause
