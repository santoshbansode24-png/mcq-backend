# Step-by-Step Guide: Fix Resend DKIM (Hostinger)

Based on your screenshot, your DKIM record is "Failed". Follow these exact steps to fix it in Hostinger:

### 1. Delete the Old DKIM Record
1. Log in to your **Hostinger hPanel**.
2. Go to **Domains** -> **veeruapp.in** -> **DNS / Nameservers**.
3. Find any existing TXT records with the name `resend._domainkey`.
4. **Delete them** to start fresh.

### 2. Add the Correct DKIM Record
In the "Manage DNS Records" section, add a new record with these exact values:

- **Type**: `TXT`
- **Name / Host**: `resend._domainkey` 
  - *Wait!* Do **NOT** type `resend._domainkey.veeruapp.in`. Just type `resend._domainkey`.
- **TXT Value**: Copy the long string from your Resend dashboard. It looks like:
  `p=MIGfMA0GCSqGSIb3DQEBA...`
- **TTL**: Leave as default (usually 14400 or Auto).

### 3. Verify in Resend
1. Go back to your [Resend Dashboard](https://resend.com/domains).
2. Wait about 3-5 minutes for Hostinger to update.
3. Click the **"Verify"** or **"Refresh"** button.
4. The status next to DKIM should change from "Failed" to **"Success"** (Green).

---

### Why did it fail?
Usually, it fails if you accidentally type the domain name twice in the Host field, or if there is a typo in the long TXT value string. This fresh start will fix it!
