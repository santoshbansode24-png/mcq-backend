# 📱 USB Debugging Setup Guide

## Step-by-Step Instructions

### 1️⃣ Enable Developer Options on Your Phone

1. Open **Settings** on your Android phone
2. Scroll down to **About Phone** (or **About Device**)
3. Find **Build Number**
4. **Tap Build Number 7 times** rapidly
5. You'll see a message: "You are now a developer!"

---

### 2️⃣ Enable USB Debugging

1. Go back to **Settings**
2. Look for **Developer Options** (usually near the bottom)
   - If you don't see it, look in **System** → **Advanced** → **Developer Options**
3. Turn on **Developer Options** (toggle at top)
4. Scroll down and enable **USB Debugging**
5. Confirm when prompted

---

### 3️⃣ Connect Your Phone

1. Connect your phone to computer via **USB cable**
2. On your phone, you'll see a popup: **"Allow USB debugging?"**
3. Check **"Always allow from this computer"**
4. Tap **OK**

---

### 4️⃣ Verify Connection

Run this command to check if your device is detected:

```bash
cd "c:\xampp\htdocs\mcq project2.0\student_app"
npx react-native doctor
```

Or check with ADB:
```bash
adb devices
```

You should see your device listed.

---

### 5️⃣ Build and Install

Once connected, run:

```bash
cd "c:\xampp\htdocs\mcq project2.0\student_app"
npx expo run:android
```

---

## ⚠️ Troubleshooting

### Phone Not Detected?

1. **Try different USB cable** (some cables are charge-only)
2. **Change USB mode** on phone:
   - Swipe down notification
   - Tap USB notification
   - Select "File Transfer" or "MTP"
3. **Install USB drivers** (if on Windows)
4. **Restart ADB**:
   ```bash
   adb kill-server
   adb start-server
   adb devices
   ```

### Build Fails?

1. Make sure you have **Android Studio** installed
2. Check **ANDROID_HOME** environment variable is set
3. Accept **Android SDK licenses**:
   ```bash
   cd %ANDROID_HOME%\tools\bin
   sdkmanager --licenses
   ```

---

## ✅ What to Expect

1. **First build**: 5-10 minutes
2. **Gradle downloads**: May take time
3. **Automatic installation**: APK installs on your phone
4. **App opens**: With new UI!

---

**Ready? Let me know when your phone is connected!**
