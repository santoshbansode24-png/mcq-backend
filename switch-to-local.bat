@echo off
echo ========================================
echo Switching to LOCAL XAMPP Server
echo ========================================
echo.

REM Update config.js to use local server
powershell -Command "(Get-Content 'student_app\src\api\config.js') -replace 'const USE_LOCAL = false;', 'const USE_LOCAL = true;' | Set-Content 'student_app\src\api\config.js'"

echo ✅ Configuration updated!
echo.
echo Server URLs:
echo - Admin Portal: http://localhost/veeru/backend/admin
echo - Student App: http://10.123.5.239/veeru/backend/api
echo.
echo ⚠️  Make sure XAMPP Apache and MySQL are running!
echo.
pause
