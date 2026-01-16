# How to Build an APK Locally (The "Hard Way")

Since you chose to build the APK yourself, you need to set up your computer as a development environment. Follow these steps exactly.

## Step 1: Install Java Development Kit (JDK 17)
React Native requires **Java 17**. Newer versions (like Java 21) might cause errors.

1.  **Download**: Go to [Adoptium OpenJDK 17](https://adoptium.net/temurin/releases/?version=17).
2.  **Install**: Run the installer.
    *   **IMPORTANT**: During installation, look for an option that says **"Set JAVA_HOME variable"** or **"Add to PATH"** and make sure it is **SELECTED** (Red X means not selected, change it to "Will be installed on local hard drive").

## Step 2: Install Android Studio
This installs the Android SDK, which is required to build the app.

1.  **Download**: Go to [Android Studio](https://developer.android.com/studio).
2.  **Install**: Run the installer. Keep all default settings.
3.  **Setup**:
    *   Open Android Studio after installation.
    *   Follow the setup wizard.
    *   It will ask to download "SDK Components". **Allow it to download everything**. This may take a while.

## Step 3: Configure Environment Variables
Windows needs to know where these tools are.

1.  Press **Windows Key**, type **"Edit the system environment variables"**, and press Enter.
2.  Click the **"Environment Variables..."** button at the bottom right.
3.  **Check JAVA_HOME**:
    *   Look in the "System variables" (bottom box).
    *   You should see `JAVA_HOME`. If not, create it:
        *   Variable name: `JAVA_HOME`
        *   Variable value: `C:\Program Files\Eclipse Adoptium\jdk-17...` (browse to where you installed Java).
4.  **Add Android SDK to Path**:
    *   In "User variables" (top box), find **"Path"** and click **Edit**.
    *   Click **New** and add these two lines (replace `YOUR_USERNAME` with your actual username):
        *   `C:\Users\YOUR_USERNAME\AppData\Local\Android\Sdk\platform-tools`
        *   `C:\Users\YOUR_USERNAME\AppData\Local\Android\Sdk\emulator`
    *   Click OK on all windows.

## Step 4: Verify Installation
Open a **new** terminal (close the old one) and run:
```powershell
javac -version
adb version
```
If both commands return version numbers, you are ready to build!

## Step 5: Build the APK
Once the above is done, come back here and tell me "Ready to build", and I will run the build command for you.
