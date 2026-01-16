# 🌐 Network Error Fix Guide

## ❌ Problem

Your Student App shows "Network Error" because it's trying to connect to:
```
http://192.168.124.239/mcq%20project2.0/backend/api
```

This is a **local network IP** that only works when:
- ✅ Phone is on the **same WiFi** as your computer
- ✅ Computer's firewall allows connections
- ✅ XAMPP Apache is running

---

## ✅ Solutions

### **Solution 1: Same WiFi (Quick Test) - 5 minutes**

**Best for:** Testing with a few students on same WiFi

**Steps:**

1. **Ensure XAMPP is running:**
   - Open XAMPP Control Panel
   - Start Apache
   - Start MySQL

2. **Connect your phone to the same WiFi:**
   - WiFi name: [Your WiFi network name]
   - Same network as your computer

3. **Allow firewall access:**
   ```powershell
   # Run in PowerShell as Administrator
   New-NetFirewallRule -DisplayName "XAMPP Apache" -Direction Inbound -Program "C:\xampp\apache\bin\httpd.exe" -Action Allow
   ```

4. **Test from phone browser:**
   - Open browser on phone
   - Go to: `http://192.168.124.239/mcq%20project2.0/backend/api/get_classes.php`
   - Should see JSON data

5. **Install and test APK**

**✅ Pros:**
- Quick and easy
- No additional setup

**❌ Cons:**
- Only works on same WiFi
- Won't work with mobile data
- IP might change

---

### **Solution 2: ngrok (Public URL) - 10 minutes**

**Best for:** Testing from anywhere (mobile data, different WiFi)

**What is ngrok?**
- Free tool that creates a public URL for your local server
- Works from anywhere in the world
- Perfect for testing

**Steps:**

1. **Download ngrok:**
   - Go to: https://ngrok.com/download
   - Download Windows version
   - Extract to `C:\ngrok`

2. **Sign up (free):**
   - Go to: https://dashboard.ngrok.com/signup
   - Create free account
   - Copy your authtoken

3. **Configure ngrok:**
   ```powershell
   cd C:\ngrok
   .\ngrok config add-authtoken YOUR_AUTH_TOKEN_HERE
   ```

4. **Start ngrok tunnel:**
   ```powershell
   cd C:\ngrok
   .\ngrok http 80
   ```

5. **You'll see output like:**
   ```
   Forwarding  https://abc123.ngrok-free.app -> http://localhost:80
   ```

6. **Update app config:**
   - Your public URL: `https://abc123.ngrok-free.app`
   - Update `src/api/config.js`:
   ```javascript
   export const API_URL = 'https://abc123.ngrok-free.app/mcq%20project2.0/backend/api';
   export const BASE_URL = 'https://abc123.ngrok-free.app/mcq%20project2.0/backend';
   ```

7. **Rebuild APK:**
   ```powershell
   cd "c:\xampp\htdocs\mcq project2.0\student_app"
   eas build --platform android --profile production
   ```

8. **Install new APK and test!**

**✅ Pros:**
- Works from anywhere
- Works with mobile data
- Easy to set up

**❌ Cons:**
- URL changes every time you restart ngrok (free version)
- Need to keep ngrok running
- Need to rebuild APK when URL changes

---

### **Solution 3: Cloud Hosting (Production) - 1-2 hours**

**Best for:** Final production deployment

**Options:**

#### **Option A: Free Hosting (InfinityFree)**

1. **Sign up:**
   - Go to: https://infinityfree.net
   - Create free account

2. **Upload backend:**
   - Upload all files from `c:\xampp\htdocs\mcq project2.0\backend`
   - Via FTP or File Manager

3. **Create MySQL database:**
   - Create database in cPanel
   - Import your database SQL

4. **Update database config:**
   - Edit `backend/config/database.php` with new credentials

5. **Get your URL:**
   - Example: `http://yoursite.infinityfreeapp.com`

6. **Update app config:**
   ```javascript
   export const API_URL = 'http://yoursite.infinityfreeapp.com/api';
   export const BASE_URL = 'http://yoursite.infinityfreeapp.com';
   ```

7. **Rebuild APK**

#### **Option B: Paid Hosting (Recommended)**

**Hostinger/Bluehost/SiteGround:**
- Cost: $2-5/month
- Better performance
- SSL certificate included
- 24/7 support

**Steps:**
1. Purchase hosting plan
2. Upload backend files
3. Import database
4. Update config
5. Get your domain (e.g., `https://mcqapp.com`)
6. Update app config
7. Rebuild APK

**✅ Pros:**
- Permanent solution
- Works from anywhere
- Professional
- No need to keep computer running

**❌ Cons:**
- Costs money (paid hosting)
- Takes time to set up
- Need to manage hosting

---

## 🚀 Recommended Approach

### **For Testing (Now):**
Use **Solution 1 (Same WiFi)** or **Solution 2 (ngrok)**

### **For Production (Later):**
Use **Solution 3 (Cloud Hosting)**

---

## 📋 Step-by-Step: ngrok Setup (Recommended for Testing)

### **1. Download and Install ngrok**

```powershell
# Create directory
New-Item -ItemType Directory -Force -Path "C:\ngrok"

# Download ngrok (you'll need to do this manually)
# Go to: https://ngrok.com/download
# Extract ngrok.exe to C:\ngrok
```

### **2. Sign Up and Get Auth Token**

1. Go to: https://dashboard.ngrok.com/signup
2. Sign up (free)
3. Copy your authtoken from: https://dashboard.ngrok.com/get-started/your-authtoken

### **3. Configure ngrok**

```powershell
cd C:\ngrok
.\ngrok config add-authtoken YOUR_TOKEN_HERE
```

### **4. Start ngrok**

```powershell
cd C:\ngrok
.\ngrok http 80
```

**Keep this window open!**

### **5. Copy Your Public URL**

You'll see something like:
```
Forwarding  https://1234-abc-def.ngrok-free.app -> http://localhost:80
```

Copy the URL: `https://1234-abc-def.ngrok-free.app`

### **6. Update App Config**

Edit: `c:\xampp\htdocs\mcq project2.0\student_app\src\api\config.js`

```javascript
export const API_URL = 'https://1234-abc-def.ngrok-free.app/mcq%20project2.0/backend/api';
export const BASE_URL = 'https://1234-abc-def.ngrok-free.app/mcq%20project2.0/backend';
```

### **7. Test in Browser**

Open on your phone:
```
https://1234-abc-def.ngrok-free.app/mcq%20project2.0/backend/api/get_classes.php
```

Should see JSON data!

### **8. Rebuild APK**

```powershell
cd "c:\xampp\htdocs\mcq project2.0\student_app"
eas build --platform android --profile production
```

### **9. Install New APK**

Download and install the new APK on your phone.

**Now it will work from anywhere!** 🎉

---

## 🔍 Troubleshooting

### **Issue: "Network Error" still appears**

**Check:**
1. ✅ XAMPP Apache is running
2. ✅ ngrok is running (if using ngrok)
3. ✅ Phone has internet connection
4. ✅ API URL in config.js is correct
5. ✅ APK was rebuilt after changing config

**Test API manually:**
- Open phone browser
- Go to your API URL + `/get_classes.php`
- Should see JSON data

### **Issue: ngrok URL changes**

**Problem:** Free ngrok URLs change every restart

**Solutions:**
1. **Keep ngrok running** - Don't close it
2. **Upgrade to ngrok paid** ($8/month) - Get permanent URL
3. **Use cloud hosting** - Permanent solution

### **Issue: Firewall blocking**

**Windows Firewall:**
```powershell
# Run as Administrator
New-NetFirewallRule -DisplayName "XAMPP Apache" -Direction Inbound -Program "C:\xampp\apache\bin\httpd.exe" -Action Allow
```

**Antivirus:**
- Add XAMPP folder to exceptions

---

## 📊 Comparison

| Solution | Cost | Setup Time | Works Anywhere | Permanent |
|----------|------|------------|----------------|-----------|
| Same WiFi | Free | 5 min | ❌ No | ✅ Yes |
| ngrok Free | Free | 10 min | ✅ Yes | ❌ No |
| ngrok Paid | $8/mo | 10 min | ✅ Yes | ✅ Yes |
| Free Hosting | Free | 1-2 hrs | ✅ Yes | ✅ Yes |
| Paid Hosting | $2-5/mo | 1-2 hrs | ✅ Yes | ✅ Yes |

---

## 🎯 My Recommendation

### **Right Now (Testing):**

1. **Use ngrok** (Solution 2)
   - Quick setup
   - Works from anywhere
   - Perfect for testing

### **Before Final Release:**

1. **Get cloud hosting** (Solution 3)
   - Professional
   - Permanent
   - Better for students

---

## 📞 Need Help?

**Choose your solution and let me know:**
1. Want to use **Same WiFi** (quickest test)
2. Want to use **ngrok** (works anywhere)
3. Want to deploy to **cloud hosting** (production)

I'll guide you through the specific steps!

---

**Current Status:**
- ✅ XAMPP running on: `192.168.124.239`
- ✅ Apache accessible on port 80
- ❌ Only works on local WiFi
- 🎯 Need to make it publicly accessible

**Next Step:** Choose a solution above and I'll help you implement it! 🚀
