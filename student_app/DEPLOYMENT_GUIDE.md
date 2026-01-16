# 📱 Student App Deployment Guide

## Table of Contents
1. [Publishing Options](#publishing-options)
2. [Option 1: Google Play Store (Recommended)](#option-1-google-play-store-recommended)
3. [Option 2: Direct APK Distribution](#option-2-direct-apk-distribution)
4. [Option 3: Expo Application Services (EAS)](#option-3-expo-application-services-eas)
5. [Managing Updates After Publishing](#managing-updates-after-publishing)
6. [Version Management](#version-management)

---

## Publishing Options

You have **3 main options** to publish your student app:

### 🏆 **Option 1: Google Play Store** (Best for wide distribution)
- Professional and trusted
- Automatic updates for users
- Requires one-time $25 developer fee
- Review process (1-7 days)

### 📦 **Option 2: Direct APK Distribution** (Quick & Free)
- No fees or review process
- Instant distribution
- Manual updates required
- Users need to enable "Install from Unknown Sources"

### ☁️ **Option 3: Expo Application Services (EAS)** (Easiest)
- Simplified build process
- Cloud-based builds
- Free tier available
- Easy OTA (Over-The-Air) updates

---

## Option 1: Google Play Store (Recommended)

### Prerequisites
1. **Google Play Developer Account** ($25 one-time fee)
   - Sign up at: https://play.google.com/console/signup
2. **Prepare App Assets**
3. **Build Production APK/AAB**

### Step 1: Update App Configuration

First, update your `app.json` with production-ready settings:

```json
{
  "expo": {
    "name": "MCQ Student App",
    "slug": "mcq-student-app",
    "version": "1.0.0",
    "orientation": "portrait",
    "icon": "./assets/icon.png",
    "userInterfaceStyle": "light",
    "splash": {
      "image": "./assets/splash-icon.png",
      "resizeMode": "contain",
      "backgroundColor": "#4F46E5"
    },
    "android": {
      "package": "com.mcqedutech.studentapp",
      "versionCode": 1,
      "adaptiveIcon": {
        "foregroundImage": "./assets/adaptive-icon.png",
        "backgroundColor": "#4F46E5"
      },
      "permissions": [
        "CAMERA",
        "READ_EXTERNAL_STORAGE",
        "WRITE_EXTERNAL_STORAGE"
      ]
    },
    "extra": {
      "eas": {
        "projectId": "your-project-id"
      }
    }
  }
}
```

### Step 2: Build Production APK/AAB

#### Using Expo EAS (Recommended)
```bash
# Install EAS CLI
npm install -g eas-cli

# Login to Expo
eas login

# Configure EAS
eas build:configure

# Build for Android (AAB for Play Store)
eas build --platform android --profile production
```

#### Using Local Build
```bash
# Build APK locally
npx expo run:android --variant release

# Or build AAB (Android App Bundle - required for Play Store)
cd android
./gradlew bundleRelease
```

### Step 3: Prepare Store Listing

**Required Assets:**
- **App Icon**: 512x512 px (PNG)
- **Feature Graphic**: 1024x500 px
- **Screenshots**: At least 2 (phone), recommended 4-8
- **Privacy Policy URL** (required)
- **App Description** (short & full)

**Example Description:**
```
MCQ Student App - Your Complete Learning Companion

Features:
✓ Interactive MCQ Practice
✓ AI-Powered Tutor
✓ Homework Solver with Image Recognition
✓ Video Lessons & Notes
✓ Performance Analytics
✓ Leaderboards & Achievements
✓ Offline Access

Perfect for students looking to excel in their studies with personalized AI assistance.
```

### Step 4: Upload to Play Console

1. Go to [Google Play Console](https://play.google.com/console)
2. Create a new app
3. Fill in app details
4. Upload your AAB file
5. Complete content rating questionnaire
6. Set up pricing & distribution
7. Submit for review

**Timeline:** Usually 1-7 days for first review

---

## Option 2: Direct APK Distribution

### Step 1: Build Production APK

```bash
# Navigate to android folder
cd android

# Build release APK
./gradlew assembleRelease

# APK will be at:
# android/app/build/outputs/apk/release/app-release.apk
```

### Step 2: Sign the APK

For security, you should sign your APK:

```bash
# Generate keystore (one-time)
keytool -genkeypair -v -storetype PKCS12 -keystore my-release-key.keystore -alias my-key-alias -keyalg RSA -keysize 2048 -validity 10000

# Sign the APK
jarsigner -verbose -sigalg SHA256withRSA -digestalg SHA-256 -keystore my-release-key.keystore android/app/build/outputs/apk/release/app-release.apk my-key-alias

# Optimize with zipalign
zipalign -v 4 app-release.apk app-release-aligned.apk
```

### Step 3: Distribute

**Distribution Methods:**
1. **Direct Download**: Host on your website
2. **Google Drive**: Share link with users
3. **Firebase App Distribution**: Free hosting & analytics
4. **Email**: Send directly to users

**User Installation Steps:**
1. Download APK file
2. Enable "Install from Unknown Sources" in Settings
3. Open APK file and install

---

## Option 3: Expo Application Services (EAS)

### Step 1: Install EAS CLI

```bash
npm install -g eas-cli
```

### Step 2: Login & Configure

```bash
# Login to Expo account
eas login

# Initialize EAS in your project
eas build:configure
```

This creates `eas.json`:

```json
{
  "build": {
    "development": {
      "developmentClient": true,
      "distribution": "internal"
    },
    "preview": {
      "distribution": "internal",
      "android": {
        "buildType": "apk"
      }
    },
    "production": {
      "android": {
        "buildType": "apk"
      }
    }
  },
  "submit": {
    "production": {}
  }
}
```

### Step 3: Build

```bash
# Build preview APK (for testing)
eas build --platform android --profile preview

# Build production APK
eas build --platform android --profile production

# Build AAB for Play Store
eas build --platform android --profile production
```

### Step 4: Download & Distribute

After build completes:
1. Download APK from Expo dashboard
2. Distribute via your preferred method
3. Or submit directly to Play Store:

```bash
eas submit --platform android
```

---

## Managing Updates After Publishing

### 🔄 Update Methods

#### Method 1: Over-The-Air (OTA) Updates with Expo
**Best for:** JavaScript/React code changes, UI updates, bug fixes

```bash
# Install expo-updates
npx expo install expo-updates

# Publish update
eas update --branch production --message "Bug fixes and improvements"
```

**Advantages:**
- ✅ Instant updates (no app store review)
- ✅ Users get updates automatically
- ✅ No need to reinstall app
- ✅ Can rollback if needed

**Limitations:**
- ❌ Cannot update native code (Java/Kotlin)
- ❌ Cannot change permissions
- ❌ Cannot update app.json configurations

#### Method 2: New Version Release
**Required for:** Native code changes, new permissions, SDK updates

**Steps:**
1. Update version in `app.json`:
```json
{
  "expo": {
    "version": "1.0.1",
    "android": {
      "versionCode": 2
    }
  }
}
```

2. Build new APK/AAB
3. Upload to Play Store or distribute new APK

### Version Numbering Guide

```
Version: X.Y.Z
- X (Major): Breaking changes, major features
- Y (Minor): New features, improvements
- Z (Patch): Bug fixes, small updates

Android versionCode: Increment by 1 for each release
```

**Example:**
- `1.0.0` → `1.0.1` (Bug fix)
- `1.0.1` → `1.1.0` (New feature)
- `1.1.0` → `2.0.0` (Major redesign)

### Setting Up OTA Updates

1. **Install expo-updates:**
```bash
npx expo install expo-updates
```

2. **Configure app.json:**
```json
{
  "expo": {
    "updates": {
      "enabled": true,
      "checkAutomatically": "ON_LOAD",
      "fallbackToCacheTimeout": 0,
      "url": "https://u.expo.dev/your-project-id"
    },
    "runtimeVersion": {
      "policy": "sdkVersion"
    }
  }
}
```

3. **Publish updates:**
```bash
# Create update branch
eas update:configure

# Publish update
eas update --branch production --message "Fixed login issue"
```

4. **Users automatically receive updates** when they restart the app!

### Update Best Practices

1. **Test Before Publishing**
   - Test on multiple devices
   - Check all features work
   - Verify API connections

2. **Communicate Changes**
   - Keep changelog
   - Notify users of major updates
   - Use in-app update notifications

3. **Gradual Rollout**
   - Release to small group first
   - Monitor for issues
   - Full rollout if stable

4. **Emergency Rollback**
```bash
# Rollback to previous update
eas update --branch production --message "Rollback" --republish
```

---

## Quick Start Commands

### For First-Time Publishing:

```bash
# 1. Install EAS CLI
npm install -g eas-cli

# 2. Login
eas login

# 3. Configure
eas build:configure

# 4. Build APK
eas build --platform android --profile production

# 5. Download and distribute
```

### For Updates (OTA):

```bash
# Quick update
eas update --branch production --message "Your update message"
```

### For New Version:

```bash
# 1. Update version in app.json
# 2. Build new version
eas build --platform android --profile production

# 3. Submit to Play Store (if using)
eas submit --platform android
```

---

## Recommended Workflow

### Initial Launch:
1. ✅ Build production APK/AAB using EAS
2. ✅ Test thoroughly
3. ✅ Submit to Google Play Store
4. ✅ Also provide direct APK download

### Regular Updates:
1. ✅ Use OTA updates for bug fixes and UI changes
2. ✅ Release new versions monthly/quarterly for major features
3. ✅ Keep both Play Store and direct APK updated

### Emergency Fixes:
1. ✅ Push OTA update immediately
2. ✅ Follow up with new version if needed

---

## Cost Breakdown

| Method | Initial Cost | Update Cost | Time to Publish |
|--------|-------------|-------------|-----------------|
| **Play Store** | $25 (one-time) | Free | 1-7 days |
| **Direct APK** | Free | Free | Instant |
| **EAS Free** | Free | Free | 30-60 min |
| **EAS Paid** | $29/month | Free | 15-30 min |

---

## Support & Resources

- **Expo Documentation**: https://docs.expo.dev/
- **EAS Build**: https://docs.expo.dev/build/introduction/
- **EAS Update**: https://docs.expo.dev/eas-update/introduction/
- **Play Console Help**: https://support.google.com/googleplay/android-developer/

---

## Next Steps

1. **Choose your publishing method** (I recommend starting with EAS + Direct APK)
2. **Update app.json** with production settings
3. **Build your first production APK**
4. **Test on multiple devices**
5. **Publish and celebrate! 🎉**

Need help with any step? Just ask!
