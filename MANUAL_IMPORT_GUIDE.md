# How to Manually Import Your Database to Railway

Since the automatic tool couldn't reach the server (likely due to a changing address), you need to do this manually. It only takes about 5 minutes.

## Method 1: The "Magic Terminal Command" (Might fail on older XAMPP)
**⚠️ NOTE:** If you see an error like `Plugin caching_sha2_password could not be loaded`, skip this and go to **Method 2** immediately.

```powershell
cmd /c "c:\xampp\mysql\bin\mysql -h yamanote.proxy.rlwy.net -P 24540 -u root -pNvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf railway < c:\xampp\htdocs\veeru\railway_export.sql"
```
1.  Log in to [Railway.app](https://railway.app).
2.  Click on your **MySQL Service**.
3.  Click the **"Connect"** tab.
4.  Keep this tab open. You will need: **Host**, **Port**, **User**, **Password**, and **Database Name**.

### Step 2: Build Your Command
I have prepared the command for you. You just need to fill in the blanks (replace the `[...]` parts) with the info from the Railway website.

```powershell
c:\xampp\mysql\bin\mysql -h [HOST] -P [PORT] -u [USER] -p[PASSWORD] [DB_NAME] < railway_export.sql
```

**Example of what it should look like (Don't copy this, use YOURS):**
`cmd /c "c:\xampp\mysql\bin\mysql -h yamanote.proxy.rlwy.net -P 24540 -u root -pNvVlnnYmCEUTnMhcVHJVbDyYhqdcTuuf railway < c:\xampp\htdocs\veeru\railway_export.sql"`

> **✅ FIXED (PowerShell Error):** I wrapped the command so it works in PowerShell and added the full path to your file.

### Step 3: Run It
1.  Copy your specific command.
2.  Paste it into the terminal in VS Code (where you see `PS C:\xampp\htdocs\veeru>`).
3.  Press **Enter**.
4.  If it runs silently and returns to the prompt, **IT WORKED!** 🎉

---

## Method 2: Using a Visual Tool (HeidiSQL)
If the command line is too confusing, use a visual app.

1.  **Download & Install:** [HeidiSQL](https://www.heidisql.com/download.php) (It is free and very common for XAMPP users).
2.  **Open HeidiSQL** and click **"New"** (bottom left).
3.  **Fill in the details** from your Railway "Connect" tab:
    *   **Hostname / IP:** (The Host from Railway)
    *   **User:** `root`
    *   **Password:** (The Password from Railway)
    *   **Port:** (The Port from Railway)
4.  Click **"Open"**.
5.  Once connected:
    *   Go to **File** (top menu) -> **Load SQL file...**
    *   Browse to `c:\xampp\htdocs\veeru\railway_export.sql`.
    *   Click **Open**.
    *   Press **F9** (or the "Play" triangle icon) to run the file.

---

### Which one should I do?
Try **Method 1** first. It involves no downloads and is very fast if you copy the password correctly. If you get stuck, use Method 2.
