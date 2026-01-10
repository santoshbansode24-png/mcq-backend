@echo off
echo ========================================
echo Switching to RAILWAY Production Server
echo ========================================
echo.

REM Update config.js to use Railway server
powershell -Command "(Get-Content 'student_app\src\api\config.js') -replace 'const USE_LOCAL = true;', 'const USE_LOCAL = false;' | Set-Content 'student_app\src\api\config.js'"

echo ✅ Configuration updated!
echo.
echo Server URLs:
echo - Admin Portal: https://mcq-backend-production-91e1.up.railway.app/admin
echo - Student App: https://mcq-backend-production-91e1.up.railway.app/api
echo.
echo ⚠️  Make sure Railway deployment is active!
echo.
pause
