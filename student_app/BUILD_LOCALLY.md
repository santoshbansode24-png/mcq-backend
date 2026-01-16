# 🔨 Build APK Locally (No Expo Account Required)

## Alternative Method - Build Without EAS

If you're having trouble with Expo login, you can build the APK locally on your computer.

---

## ✅ Prerequisites

You need:
- ✅ Android Studio installed
- ✅ Java JDK 17 or higher
- ✅ Android SDK configured

---

## 📋 Step-by-Step Instructions

### **Step 1: Install Android Studio**

If not already installed:

1. Download from: https://developer.android.com/studio
2. Install with default settings
3. Open Android Studio
4. Go to: Tools → SDK Manager
5. Install:
   - Android SDK Platform 34
   - Android SDK Build-Tools 34.0.0
   - Android SDK Command-line Tools

---

### **Step 2: Set Environment Variables**

Add these to your system environment variables:

```
ANDROID_HOME = C:\Users\ADMIN\AppData\Local\Android\Sdk
JAVA_HOME = C:\Program Files\Android\Android Studio\jbr
```

Add to PATH:
```
%ANDROID_HOME%\platform-tools
%ANDROID_HOME%\tools
%JAVA_HOME%\bin
```

---

### **Step 3: Accept SDK Licenses**

```powershell
cd %ANDROID_HOME%\tools\bin
sdkmanager --licenses
# Press 'y' for all licenses
```

---

### **Step 4: Generate Android Project**

```powershell
cd "c:\xampp\htdocs\mcq project2.0\student_app"

# Generate native Android code
npx expo prebuild --platform android
```

This creates the `android` folder with native code.

---

### **Step 5: Build APK**

```powershell
# Navigate to android folder
cd android

# Build debug APK
.\gradlew assembleDebug

# Or build release APK
.\gradlew assembleRelease
```

---

### **Step 6: Find Your APK**

**Debug APK:**
```
android\app\build\outputs\apk\debug\app-debug.apk
```

**Release APK:**
```
android\app\build\outputs\apk\release\app-release.apk
```

---

## 🚀 Quick Commands

```powershell
# Full build process
cd "c:\xampp\htdocs\mcq project2.0\student_app"
npx expo prebuild --platform android
cd android
.\gradlew assembleRelease
```

---

## ⚠️ Important Notes

1. **First build takes 10-30 minutes** (downloads dependencies)
2. **Subsequent builds are faster** (5-10 minutes)
3. **Release APK needs signing** for production
4. **Debug APK works fine** for testing and distribution

---

## 🔐 Signing Release APK (Optional)

For a properly signed release APK:

### **1. Generate Keystore**

```powershell
keytool -genkeypair -v -storetype PKCS12 -keystore my-release-key.keystore -alias my-key-alias -keyalg RSA -keysize 2048 -validity 10000
```

**Save the password!** You'll need it.

### **2. Configure Gradle**

Create `android/gradle.properties`:

```properties
MYAPP_RELEASE_STORE_FILE=my-release-key.keystore
MYAPP_RELEASE_KEY_ALIAS=my-key-alias
MYAPP_RELEASE_STORE_PASSWORD=your_password
MYAPP_RELEASE_KEY_PASSWORD=your_password
```

### **3. Update build.gradle**

Edit `android/app/build.gradle`:

```gradle
android {
    signingConfigs {
        release {
            storeFile file(MYAPP_RELEASE_STORE_FILE)
            storePassword MYAPP_RELEASE_STORE_PASSWORD
            keyAlias MYAPP_RELEASE_KEY_ALIAS
            keyPassword MYAPP_RELEASE_KEY_PASSWORD
        }
    }
    buildTypes {
        release {
            signingConfig signingConfigs.release
        }
    }
}
```

### **4. Build Signed APK**

```powershell
cd android
.\gradlew assembleRelease
```

---

## 🔄 Updates Without Expo

Without EAS, you'll need to:

1. **Make code changes**
2. **Build new APK**
3. **Distribute new APK to users**

No OTA updates available with this method.

---

## 📊 Comparison

| Feature | EAS Build | Local Build |
|---------|-----------|-------------|
| **Setup Time** | 5 min | 1-2 hours |
| **Build Time** | 15-20 min | 10-30 min |
| **Requires** | Expo account | Android Studio |
| **OTA Updates** | ✅ Yes | ❌ No |
| **Difficulty** | Easy | Medium |

---

## 🆘 Troubleshooting

**"gradlew not found"**
```powershell
# Run prebuild first
npx expo prebuild --platform android
```

**"SDK not found"**
- Install Android Studio
- Set ANDROID_HOME environment variable

**"Build failed"**
- Check Java version: `java -version` (need 17+)
- Accept licenses: `sdkmanager --licenses`

**"Out of memory"**
Edit `android/gradle.properties`:
```properties
org.gradle.jvmargs=-Xmx4096m
```

---

## ✅ Recommended Approach

**For now:** Use local build to get started quickly

**Later:** Set up Expo account for easier updates

---

Need help with local build? Just ask!
