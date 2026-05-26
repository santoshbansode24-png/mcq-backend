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

echo 3. Pushing to GitHub (master)...
git push origin main:master

echo 4. Waiting for Railway to Build and Deploy (120s)...
echo ⚠️  DO NOT press any key to skip! Railway needs time to deploy the new container.
timeout /t 120

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
