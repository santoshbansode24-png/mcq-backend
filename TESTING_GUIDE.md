# 🧪 Veeru App: Manual Testing Guide (Smoke Test)

You asked: *"Is there any way to look for bugs?"*
Since we don't have automated robots to test the app, **YOU** are the best tester.

Follow this **"Smoke Test"** (to see if anything smokes/crashes).
If you pass all 5 levels, your app is solid.

---

### 🟢 Level 1: The "New Student" Flow
**Goal:** Can a stranger join the app?
1.  **Logout** (if logged in).
2.  Click **Register**.
3.  Create a user: `bugtester@test.com` / `password123`.
4.  **Check:** Did it go to the "Class Selection" screen?
    *   ✅ YES: Registration & Database Write works.
    *   ❌ NO: Database permission error.

### 🟡 Level 2: The "Content" Flow
**Goal:** Does data load from the cloud?
1.  Go to **Home Screen**.
2.  Click **"Class 10"** (or any class).
3.  Click **"Science"**.
4.  Click **"Chapter 1"**.
5.  **Check:** Do you see the Tabs (Notes, Videos, MCQ)?
    *   ✅ YES: Database Read works.
    *   ❌ NO: API Connection error.

### 🟠 Level 3: The "Assets" Flow
**Goal:** Do images/PDFs load?
1.  Open a **Note (PDF)**. Does it open?
2.  Look at a **Subject Icon**. Is it visible?
3.  **Check:** Are there any broken image icons?
    *   ✅ YES (Images visible): File storage works.
    *   ❌ NO (Broken/Blank): URL path error.

### 🔴 Level 4: The "Feature" Flow (Vocab, Revision, Custom Test)
**Goal:** Do the special tools work?
1.  Open **Vocab Booster**.
    *   Play one round.
    *   **Check:** Is the Marathi text readable? (We just fixed this).
2.  Open **Quick Revision**.
    *   **Check:** Does the voice (TTS) speak when you press play?
3.  Open **My Exam** (Custom Test).
    *   Create a test and start it.
    *   **Check:** Do questions load?

### ⚫ Level 5: The "Airplane" Mode (Stress Test)
**Goal:** Does the app crash if internet dies?
1.  Turn on **Airplane Mode** on your phone.
2.  Try to click a Chapter.
3.  **Check:** Does it show a "No Internet" warning, or does the app just close/crash?
    *   ✅ Good: "Please check internet".
    *   ❌ Bad: App closes suddenly (Crash).

---

### 📝 Report Card
*   **5/5 Passed:** Ready for Play Store! 🚀
*   **Any Fail:** Tell me which level failed, and I will fix it.
