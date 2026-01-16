# Setting up Android Emulator for React Native

To run your app on a virtual Android device on your PC, follow these steps:

## 1. Download & Install Android Studio
1.  Go to the official website: [https://developer.android.com/studio](https://developer.android.com/studio)
2.  Download **Android Studio**.
3.  Run the installer.
    *   Make sure **"Android Virtual Device"** is checked during installation.
    *   Choose "Standard" installation type when asked.
    *   Accept all licenses.

## 2. Create a Virtual Device (Emulator)
1.  Open **Android Studio**.
2.  On the Welcome screen, click **More Actions** (three dots) > **Virtual Device Manager**.
    *   *If you are already inside a project, go to **Tools > Device Manager**.*
3.  Click **Create Device**.
4.  Choose a phone (e.g., **Pixel 6** or **Pixel 7**) and click **Next**.
5.  Select a System Image (Android Version).
    *   Click the **Download** icon next to the latest version (e.g., **Tiramisu** or **UpsideDownCake**).
    *   Wait for the download to finish, then select it and click **Next**.
6.  Click **Finish**.

## 3. Run the Emulator
1.  In the **Device Manager**, click the **Play** button (▶) next to your new device.
2.  The virtual phone should appear on your screen and boot up.

## 4. Connect Expo to Emulator
1.  Make sure the Emulator is running and unlocked.
2.  Go to your terminal where `npx expo start` is running.
3.  Press **`a`** on your keyboard.
4.  Expo will automatically install the "Expo Go" app on the emulator and open your project!

## Troubleshooting
If you get an error saying `adb` is not found:
1.  Open Windows Search and type **"Edit the system environment variables"**.
2.  Click **Environment Variables**.
3.  Under **User variables**, find **Path** and click **Edit**.
4.  Click **New** and add this path (replace `YOUR_USERNAME` with your actual PC username):
    `C:\Users\YOUR_USERNAME\AppData\Local\Android\Sdk\platform-tools`
5.  Click OK on all windows.
6.  Restart your terminal.
