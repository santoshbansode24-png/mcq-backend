@echo off
echo ========================================
2: echo 🚀 Quick Push to Railway
3: echo ========================================
4: echo.
5: 
6: echo 1. Adding changes...
7: git add .
8: 
9: set /p msg="Enter commit message (default: 'update'): "
10: if "%msg%"=="" set msg=update
11: 
12: echo 2. Committing...
13: git commit -m "%msg%"
14: 
15: echo 3. Pushing to GitHub...
16: git push origin main
17: 
18: echo 4. Waiting for Railway (60s)...
19: timeout /t 60
20: 
21: echo 5. Triggering Schema Update...
22: curl -s https://api.veeruapp.in/backend/api/update_schema_study.php
23: 
24: echo.
25: echo ✅ Done! Check Railway dashboard for logs.
26: echo ========================================
27: pause
