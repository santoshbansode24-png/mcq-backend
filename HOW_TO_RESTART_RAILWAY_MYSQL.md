# How to Restart MySQL on Railway

Since your database has stopped, you need to force it to start again.

## Method 1: The "Deployments" Tab (Easiest)

1.  Open your **Railway Dashboard** and click on **MySQL**.
2.  Click on the **Deployments** tab (top menu).
3.  You should see your latest deployment with a status (likely "Crashed" or "Error").
4.  Click the **three dots (⋮)** on the right side of that deployment.
5.  Select **Redeploy**.
6.  Wait for the status to turn **Active** (Green).

## Method 2: The Command Palette

1.  While in the Railway Dashboard (in your project), press **`Ctrl + K`** (or `Cmd + K` on Mac).
2.  Type **"Restart"**.
3.  Select **"Restart Service"**.
4.  Choose **MySQL**.

## Method 3: Settings Tab

1.  Click on **MySQL**.
2.  Click on the **Settings** tab.
3.  Scroll down to the **Danger Zone** (or Service actions).
4.  Look for **Restart Service** or **Redeploy**.

---

## ⚠️ Important: Check Logs

If it crashes again immediately:
1.  Click the **"Open logs"** button (from your screenshot).
2.  Look for error messages like:
    - `Out of memory` (OOM)
    - `Disk space full`
    - `Connection limit reached`

**Let me know once the GREEN dot appears!**
