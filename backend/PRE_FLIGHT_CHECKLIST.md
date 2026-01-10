# 🛫 APK Pre-Flight Checklist

Before you build your Release APK, confirm these 5 items:

## 1. Server Configuration (CRITICAL) ✅
*   **Check:** Open `src/api/config.js`.
*   **Requirement:** It must say `const config = RAILWAY_CONFIG;`.
*   **Status:** **GOOD**. (I checked this, it is set correctly).

## 2. Network Security (HTTPS) ✅
*   **Check:** Does your app allow connection to Railway?
*   **Requirement:** In `app.json`, the `networkSecurityConfig` and `domain` entries must include your Railway URL.
*   **Status:** **GOOD**. I see `mcq-backend-production-91e1.up.railway.app` is whitelisted.

## 3. App Version ⚠️
*   **Check:** Open `app.json`.
*   **Current Version:** `1.0.0` (Build 1).
*   **Advice:** If you have released an APK before, you **MUST** increase the `versionCode` (e.g., to 2) or phones will refuse to update.
*   **Action:** If this is your very first APK, leave it. If it's an update, change `"versionCode": 1` to `"versionCode": 2`.

## 4. User Login Test
*   **Action:** Before building, open the app in Expo Go (on your phone) and ensure you can **Login** using a real account.
*   **Why:** If login fails now, it will fail in the APK too.

## 5. Asset Check
*   **Action:** Check one "Image" and one "PDF" in the app.
*   **Why:** Sometimes images break in Production if they are HTTP instead of HTTPS.

---

### 🚀 Ready to Build?
If you confirmed the above, run your build command:
`eas build -p android --profile preview` (for testing)
OR
`eas build -p android --profile production` (for Play Store)
