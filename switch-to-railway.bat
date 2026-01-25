@echo off
echo ========================================
echo Switching to RAILWAY Production Server
echo ========================================
echo.

REM Update config.js to use Railway server
powershell -Command "(Get-Content 'student_app\src\api\config.js') -replace 'const config = LOCAL_CONFIG;', 'const config = RAILWAY_CONFIG;' | Set-Content 'student_app\src\api\config.js'"

echo ✅ Configuration updated!
echo.
echo Server URLs:
echo - Admin Portal: https://api.veeruapp.in/backend/admin
echo - Student App: https://api.veeruapp.in/backend/api
echo.
echo ⚠️  Make sure Railway deployment is active!
echo.
pause
