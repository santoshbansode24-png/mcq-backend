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

echo 3. Pushing to GitHub (main)...
git push origin main

echo 4. Waiting for Railway to Build and Deploy (45s)...
echo ⚠️  DO NOT press any key! Railway is deploying your changes.
timeout /t 45

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

echo ✅ Done! Your code is pushed to main and deployed live on Railway!
echo ========================================
pause
