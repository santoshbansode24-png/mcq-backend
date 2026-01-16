# 📱 Rebuild Student App with New UI

## Quick Rebuild Guide

### Option 1: Local Build (Faster - 5-10 minutes)

```bash
# Navigate to student app
cd "c:\xampp\htdocs\mcq project2.0\student_app"

# Build for Android
npx expo run:android
```

**This will:**
- Build a new APK with the updated UI
- Install it automatically on your connected device
- Replace the old version

**Requirements:**
- Android device connected via USB
- USB debugging enabled
- Or Android emulator running

---

### Option 2: EAS Build (Cloud Build - 10-20 minutes)

```bash
# Navigate to student app
cd "c:\xampp\htdocs\mcq project2.0\student_app"

# Build APK with EAS
npx eas build --platform android --profile preview
```

**This will:**
- Build APK on Expo's servers
- Give you a download link
- You can install the APK manually

---

## 🎯 Recommended: Local Build

Since you already have the app installed, the fastest way is:

1. **Connect your phone via USB**
2. **Enable USB debugging** on your phone
3. **Run the build command**
4. **Wait 5-10 minutes**
5. **New app installs automatically**

---

## ⚡ Quick Steps:

### 1. Enable USB Debugging on Phone:
- Go to **Settings** → **About Phone**
- Tap **Build Number** 7 times (Developer mode)
- Go to **Settings** → **Developer Options**
- Enable **USB Debugging**

### 2. Connect Phone to Computer:
- Connect via USB cable
- Allow USB debugging when prompted

### 3. Run Build Command:
```bash
cd "c:\xampp\htdocs\mcq project2.0\student_app"
npx expo run:android
```

### 4. Wait for Build:
- Takes 5-10 minutes first time
- Faster on subsequent builds
- App installs automatically

---

## 📝 What Gets Updated:

✅ New bottom navigation design
✅ Glassmorphism effects
✅ Smooth animations
✅ All UI improvements
✅ Local backend configuration

---

## 🚀 After Installation:

The new APK will have:
- Modern floating bottom navigation
- All the UI improvements
- Working AI tools (with local backend)

---

**Ready to rebuild?** Let me know and I'll help you through the process!
