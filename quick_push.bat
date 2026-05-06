@echo off
echo ========================================
echo 🚀 Quick Push to Railway
echo ========================================
echo.

echo 1. Adding changes...
git add .

set /p msg="Enter commit message (default: 'update'): "
if "%msg%"=="" set msg=update

echo 2. Committing...
git commit -m "%msg%"

echo 3. Pushing to GitHub...
git push origin main

echo 4. Waiting for Railway (60s)...
timeout /t 60

echo 5. Triggering Schema Updates...
echo - Study Tracker...
curl -s https://api.veeruapp.in/backend/api/update_schema_study.php
echo.
echo - Mental Math Hub...
curl -s https://api.veeruapp.in/backend/api/update_schema_maths.php
echo.
echo - Teacher Portal Fix...
curl -s https://api.veeruapp.in/api/fix_teacher_schema.php
echo.

echo ✅ Done! Check Railway dashboard for logs.
echo ========================================
pause
