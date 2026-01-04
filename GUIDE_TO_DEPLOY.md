# 🚀 How to Deploy Veeru to Railway.app

Follow these simple steps to put your project online.

## Step 1: Push Code to GitHub (DONE ✅)
I have successfully pushed the latest code to your repository:
**[https://github.com/santoshbansode24-png/mcq-backend](https://github.com/santoshbansode24-png/mcq-backend)**

You are ready for the next step!

## Step 2: Create Railway Project
1. Go to [Railway.app](https://railway.app/) and log in with GitHub.
2. Click **"New Project"** -> **"Deploy from GitHub repo"**.
3. Select your `veeru` repository.
4. Click **"Deploy Now"**.

## Step 3: Add Database
1. In your project dashboard on Railway, click **"New"** (top right) -> **"Database"** -> **"MySQL"**.
2. Wait for the database to initialize.

## Step 4: Configure Backend
1. Click on your **Web Service** (the repo you deployed).
2. Go to the **"Variables"** tab.
3. You need to add the database credentials. Click **"New Variable"** for each:
   - `DB_HOST`: *[Get this from MySQL Service -> Connect tab]*
   - `DB_NAME`: `railway` (or whatever the default name is, usually `railway`)
   - `DB_USER`: `root`
   - `DB_PASSWORD`: *[Get this from MySQL Service -> Connect tab]*
   - `DB_PORT`: `3306`

   *Tip: Railway often provides a "Reference Variable" feature. You can type `${{MySQL.MYSQL_HOST}}` etc.*

## Step 5: Import Database Schema
1. Download a MySQL client like **Workbench**, **DBeaver**, or **TablePlus**.
2. Connect to your Railway MySQL database using the credentials from the "Connect" tab.
3. Open the file `backend/production_db.sql` (I just created this for you).
4. Run the entire SQL script to create all tables and data.

## Step 6: Update Mobile App
1. After deployment, Railway gives you a public URL (e.g., `https://veeru-production.up.railway.app`).
2. Go to your local code: `student_app/src/api/config.js`.
3. Update the URL:
   ```javascript
   export const API_URL = 'https://YOUR-RAILWAY-URL/backend/api';
   export const BASE_URL = 'https://YOUR-RAILWAY-URL/backend';
   ```
4. Restart your Expo app, and it will connect to the cloud!

## 🎉 Done!
Your app is now live and accessible from anywhere.
