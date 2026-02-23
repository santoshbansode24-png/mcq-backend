# ⚡ Turbo Start Guide for Expo Go

If you find that `npm start` is taking too long, follow these steps to make it start **instantly**.

---

## 1. 🚀 Use the New Fast Commands
I have updated your `package.json` with these faster commands:

| Command | Why it's faster | Use when... |
| :--- | :--- | :--- |
| `npm run start` | Uses **LAN mode** directly (skips Tunnel scanning) | Testing on a real phone (WiFi) |
| `npm run fast` | Uses **Localhost** (no network scanning) | Testing on an **Android Emulator** |
| `npm run offline` | Skips Expo update checks | You have slow internet |

---

## 2. 🛡️ Disable Windows Defender Scanning (CRITICAL)
Windows Defender scans every single file in `node_modules` every time you start the server. This is usually the **#1 reason** for slowness on Windows.

**How to fix:**
1. Open **Windows Security** -> Virus & threat protection.
2. Click **Manage settings**.
3. Scroll down to **Exclusions** -> Add or remove exclusions.
4. Click **Add an exclusion** -> **Folder**.
5. Select your project folder: `C:\xampp\htdocs\veeru\student_app`.

---

## 3. 🧹 Clear Cache ONLY when needed
Many developers use `npx expo start -c` every time. **Don't do this!** 
` -c` (clear) forces Expo to rebuild everything from scratch, which takes a long time. 

- Only use `npm start -- -c` if you see errors or images are not updating.

---

## 4. 📶 Avoid "Tunnel" Mode
By default, Expo sometimes tries to start a "Tunnel" (via ngrok). This is very slow because it routes your data through the internet.

- **Check your terminal:** If you see `Connection: tunnel`, press **`L`** to switch to **LAN**.
- LAN mode is much faster because it uses your local WiFi.

---

## 5. 🛠️ Hardware Acceleration
If you are using an **Android Emulator**, make sure "Intel x86 Emulator Accelerator (HAXM)" or "Hyper-V" is enabled in Android Studio.

---

### **Summary of Quickest Way to Start:**
If you are on your computer and want to just get to work:
```powershell
npm run start
```
(And make sure your phone is on the same WiFi!)
