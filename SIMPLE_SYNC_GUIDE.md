# The "Two Worlds" Guide: Moving from Local to Remote

Since you are not a coder, think of your project as living in two separate houses.

## 🏠 House 1: Your PC ("Localhost")
*   **What it is:** The `c:\xampp\htdocs\veeru` folder on your computer.
*   **What happens here:** You create new features, add new buttons, and test new ideas.
*   **The Rule:** Changes you make here (adding a file, changing a setting) **stay here** until you move them.

## ☁️ House 2: The Cloud ("Railway Server")
*   **What it is:** A computer somewhere in the world rented by Railway.
*   **What happens here:** Your students connect to this house. They cannot see "House 1" (your PC).
*   **The Rule:** This house **does not know** what you did in House 1 until you send a "Package" (Deploy).

---

## 🚀 How to Move Changes (The Checklist)

Every time you make changes on your PC and want them on the real app, you must do these **3 Moves**. You don't need to code, just **ask the AI** to do these specific steps:

### Move 1: The Code Sync (For new features/buttons)
*   **Why:** If you added a "Quick Revision" tab on your PC, Railway doesn't have the code for it yet.
*   **Command to AI:** _"I have finished testing on local. Please deploy the latest code to Railway."_
*   **What happens:** The AI will send your files to GitHub, and Railway will automatically pick them up.

### Move 2: The Data Sync (One-Click Method)
*   **Why:** To send your new Chapters/MCQs to the cloud.
*   **How to do it:**
    1.  Make sure your local XAMPP Apache/MySQL is running.
    2.  **Click this link:** [Sync Database Now](http://localhost/veeru/force_import_to_railway.php)
    3.  Wait for the green **"SUCCESS!"** message.

> **Note:** This tool (`force_import_to_railway.php`) allows you to push data without typing any commands!

### Move 3: The Config Switch (The "Bridge")
*   **Why:** Your mobile app needs to know which house to visit.
*   **Command to AI:** _"Switch the app configuration to Production (Railway) mode."_
*   **What happens:** The AI changes `src/api/config.js` so the app stops looking at your PC and starts looking at the Cloud.

---

## 🛑 Summary of Instructions for You
Next time you finish working on your PC, send this message to the AI:

> "I am done with my local changes. Please perform the **Full Deployment Routine**:
> 1. Push the latest code to GitHub.
> 2. Help me sync the database to Railway.
> 3. Switch the App Config to Production mode."

If you follow this routine, you will never have "missing features" on the live server again!
