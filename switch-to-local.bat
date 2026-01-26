@echo off
echo Switching to LOCAL configuration...

powershell -Command "(Get-Content student_app\src\api\config.js) -replace 'const config = RAILWAY_CONFIG', '// const config = RAILWAY_CONFIG' -replace '// const config = LOCAL_CONFIG', 'const config = LOCAL_CONFIG' | Set-Content student_app\src\api\config.js"

echo Done. App is now pointing to LOCALHOST.
pause
