# 🎯 Publishing Commands - Quick Reference

## 🚀 First Time Setup

```bash
# 1. Install EAS CLI globally
npm install -g eas-cli

# 2. Login to Expo account
eas login

# 3. Navigate to project
cd "c:\xampp\htdocs\mcq project2.0\student_app"

# 4. Install dependencies
npm install

# 5. Install expo-updates for OTA
npx expo install expo-updates
```

---

## 📦 Build Commands

### Build APK for Testing/Distribution
```bash
eas build --platform android --profile preview
```

### Build Production APK
```bash
eas build --platform android --profile production
```

### Build AAB for Google Play Store
```bash
eas build --platform android --profile production-aab
```

### Check Build Status
```bash
eas build:list
```

---

## 🔄 Update Commands

### Publish OTA Update (Instant)
```bash
eas update --branch production --message "Bug fixes and improvements"
```

### Publish to Specific Channel
```bash
eas update --branch production --channel production --message "Your message"
```

### View Update History
```bash
eas update:list
```

### Rollback Update
```bash
eas update --branch production --message "Rollback" --republish
```

---

## 🏪 Play Store Commands

### Submit to Google Play
```bash
eas submit --platform android
```

### Submit Specific Build
```bash
eas submit --platform android --latest
```

---

## 🧪 Testing Commands

### Run on Android Device/Emulator
```bash
npx expo run:android
```

### Start Development Server
```bash
npm start
```

### Clear Cache and Start
```bash
npx expo start -c
```

---

## 📊 Project Management

### View Project Info
```bash
eas project:info
```

### Configure Project
```bash
eas build:configure
```

### Update EAS Configuration
```bash
eas update:configure
```

---

## 🔐 Credentials Management

### View Android Credentials
```bash
eas credentials
```

### Generate New Keystore
```bash
eas credentials --platform android
```

---

## 📱 Common Workflows

### Workflow 1: First Time Publishing
```bash
# Step 1: Setup
npm install -g eas-cli
eas login

# Step 2: Configure
cd "c:\xampp\htdocs\mcq project2.0\student_app"
eas build:configure

# Step 3: Build
eas build --platform android --profile preview

# Step 4: Download APK from link provided
# Step 5: Test on device
# Step 6: Distribute to users
```

### Workflow 2: Quick Bug Fix Update
```bash
# Step 1: Fix the bug in your code
# Step 2: Test locally
npm start

# Step 3: Publish OTA update
eas update --branch production --message "Fixed [bug description]"

# Users get update automatically!
```

### Workflow 3: New Feature Release
```bash
# Step 1: Update version in app.json
# Change version from "1.0.0" to "1.1.0"
# Change versionCode from 1 to 2

# Step 2: Build new APK
eas build --platform android --profile preview

# Step 3: Test thoroughly
# Step 4: Distribute new APK
```

### Workflow 4: Emergency Rollback
```bash
# If latest update has issues
eas update --branch production --message "Rollback to stable" --republish
```

---

## 🛠️ Troubleshooting Commands

### Clear Node Modules
```bash
rm -rf node_modules
npm install
```

### Clear Expo Cache
```bash
npx expo start -c
```

### Reset EAS Configuration
```bash
rm eas.json
eas build:configure
```

### View Build Logs
```bash
eas build:view [BUILD_ID]
```

---

## 📋 Pre-Build Checklist

Before running any build command:

```bash
# 1. Check all dependencies installed
npm install

# 2. Verify app.json is correct
cat app.json

# 3. Test locally
npm start

# 4. Check for errors
npx expo-doctor

# 5. Commit changes (if using git)
git add .
git commit -m "Ready for build"

# 6. Build!
eas build --platform android --profile preview
```

---

## 🎨 Environment Variables

If you need to use environment variables:

```bash
# Create .env file
echo "API_URL=https://your-api.com" > .env

# Install dotenv
npm install dotenv

# Build with env
eas build --platform android --profile production
```

---

## 📞 Help Commands

### Get Help
```bash
eas --help
eas build --help
eas update --help
eas submit --help
```

### Check EAS Version
```bash
eas --version
```

### Update EAS CLI
```bash
npm install -g eas-cli@latest
```

---

## 🎯 Most Used Commands (Copy-Paste Ready)

```bash
# Quick build for testing
eas build --platform android --profile preview

# Quick OTA update
eas update --branch production --message "Updates"

# Check build status
eas build:list

# Start dev server
npm start

# Run on Android
npx expo run:android
```

---

## 💡 Pro Tips

1. **Always test locally first:**
   ```bash
   npm start
   ```

2. **Use preview profile for testing:**
   ```bash
   eas build --platform android --profile preview
   ```

3. **Use OTA for quick fixes:**
   ```bash
   eas update --branch production --message "Quick fix"
   ```

4. **Keep version numbers organized:**
   - Bug fix: 1.0.0 → 1.0.1
   - New feature: 1.0.0 → 1.1.0
   - Major update: 1.0.0 → 2.0.0

5. **Always increment versionCode:**
   - Each build must have higher versionCode than previous

---

## 🔗 Useful Links

- **EAS Dashboard:** https://expo.dev/accounts/[your-account]/projects
- **Build Documentation:** https://docs.expo.dev/build/introduction/
- **Update Documentation:** https://docs.expo.dev/eas-update/introduction/
- **Submit Documentation:** https://docs.expo.dev/submit/introduction/

---

## 📝 Notes

- **Build time:** Usually 15-20 minutes
- **OTA update time:** Instant (users get it on next app restart)
- **Free tier limits:** Check https://expo.dev/pricing
- **Build expiry:** Builds expire after 30 days on free tier

---

**Last Updated:** December 2, 2025  
**App Version:** 1.0.0  
**EAS CLI Version:** 13.2.0+
