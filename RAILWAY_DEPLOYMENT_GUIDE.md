# Railway Production Deployment Guide

## ✅ Step 1: Code Deployment (COMPLETED)

All updated PHP files have been pushed to GitHub:
- Commit: `0416047`
- Branch: `main`
- Files: classes.php, subjects.php, chapters.php, text_normalizer.php

**Railway Status:** Auto-deployment in progress...

---

## 🔄 Step 2: Wait for Railway Auto-Deployment

Railway will automatically detect the GitHub push and deploy the updated PHP files.

**How to check:**
1. Go to Railway dashboard: https://railway.app
2. Open your project
3. Check the "Deployments" tab
4. Wait for the deployment to show "Success" ✅

**Expected time:** 2-5 minutes

---

## 🗄️ Step 3: Connect to Railway MySQL Database

You need to execute SQL scripts on the Railway MySQL database.

### Option A: Using Railway CLI (Recommended)

```bash
# Install Railway CLI (if not installed)
npm install -g @railway/cli

# Login to Railway
railway login

# Link to your project
railway link

# Connect to MySQL
railway run mysql -h $MYSQLHOST -u $MYSQLUSER -p$MYSQLPASSWORD $MYSQLDATABASE
```

### Option B: Using MySQL Client with Railway Credentials

**Get your Railway MySQL credentials:**
1. Go to Railway dashboard
2. Click on your MySQL service
3. Go to "Variables" tab
4. Copy these values:
   - `MYSQLHOST`
   - `MYSQLUSER`
   - `MYSQLPASSWORD`
   - `MYSQLDATABASE`
   - `MYSQLPORT`

**Connect using MySQL client:**
```bash
mysql -h <MYSQLHOST> -P <MYSQLPORT> -u <MYSQLUSER> -p<MYSQLPASSWORD> <MYSQLDATABASE>
```

### Option C: Using Railway Web Console

1. Go to Railway dashboard
2. Click on your MySQL service
3. Click "Data" tab
4. Use the built-in SQL console

---

## 📝 Step 4: Execute Database Scripts (IN ORDER!)

> [!CAUTION]
> **CRITICAL:** Execute these scripts IN THE EXACT ORDER shown below!

### Script 1: Cleanup Duplicate Data (REQUIRED FIRST!)

**File:** `backend/cleanup_duplicate_classes.sql`

**Purpose:** Remove existing duplicate entries before adding constraints

**How to execute:**

**Option 1: Copy-paste the SQL**
1. Open `c:\xampp\htdocs\veeru\backend\cleanup_duplicate_classes.sql`
2. Copy the entire content
3. Paste into Railway MySQL console
4. Execute

**Option 2: Use file upload (if Railway supports it)**
```sql
SOURCE /path/to/cleanup_duplicate_classes.sql;
```

**Expected output:**
```
✓ PASS: Exactly 1 Class 3 entry
✓ PASS: Exactly 1 English subject for Class 3
✓ PASS: Subject 13 has 5 chapters
✓ PASS: Subject 33 deleted successfully
=== CLEANUP COMPLETED SUCCESSFULLY ===
```

---

### Script 2: Add UNIQUE Constraints

**File:** `backend/add_unique_constraints.sql`

**Purpose:** Prevent future duplicates at database schema level

**How to execute:**
1. Open `c:\xampp\htdocs\veeru\backend\add_unique_constraints.sql`
2. Copy the entire content
3. Paste into Railway MySQL console
4. Execute

**Expected output:**
```
✓ Added UNIQUE constraint: unique_class_per_board
✓ Added UNIQUE constraint: unique_subject_per_class
✓ Added UNIQUE constraint: unique_chapter_per_subject
```

**If you get an error:**
- Error: "Duplicate entry" → The cleanup script didn't run successfully
- **Solution:** Go back and run Script 1 again

---

### Script 3: Add Database Triggers

**File:** `backend/create_triggers.sql`

**Purpose:** Auto-capitalize text and prevent duplicates automatically

**How to execute:**
1. Open `c:\xampp\htdocs\veeru\backend\create_triggers.sql`
2. Copy the entire content
3. Paste into Railway MySQL console
4. Execute

**Expected output:**
```
✓ Created trigger: before_insert_class
✓ Created trigger: before_insert_subject
✓ Created trigger: before_insert_chapter
=== TRIGGERS CREATED SUCCESSFULLY ===
```

---

## ✅ Step 5: Verify Deployment

### Test 1: Check Admin Panel

1. Go to: https://api.veeruapp.in/admin/chapters.php
2. Try to add a chapter with lowercase text (e.g., "test chapter")
3. **Expected:** Success message shows "Auto-capitalized to: TEST CHAPTER"

### Test 2: Try Adding Duplicate

1. Go to admin panel
2. Try to add "CLASS 3" again
3. **Expected:** Error message "⚠️ Duplicate Class: A class with this name already exists for this board!"

### Test 3: Verify Database

```sql
-- Check if constraints exist
SELECT CONSTRAINT_NAME, TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = 'railway' 
  AND CONSTRAINT_TYPE = 'UNIQUE';

-- Should show:
-- unique_class_per_board | classes
-- unique_subject_per_class | subjects
-- unique_chapter_per_subject | chapters

-- Check if triggers exist
SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE 
FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE TRIGGER_SCHEMA = 'railway';

-- Should show:
-- before_insert_class | classes
-- before_insert_subject | subjects
-- before_insert_chapter | chapters
```

### Test 4: Check Mobile App

1. Open the student app
2. Go to CBSE → Class 3 → English
3. **Expected:** Shows "5 Chapters" (not 4)
4. Click on English
5. **Expected:** All 5 chapters are displayed

---

## 🚨 Troubleshooting

### Issue: "Duplicate entry" error when adding constraints

**Cause:** Cleanup script didn't remove all duplicates

**Solution:**
```sql
-- Manually check for duplicates
SELECT class_name, board_type, COUNT(*) 
FROM classes 
GROUP BY class_name, board_type 
HAVING COUNT(*) > 1;

-- If found, run cleanup script again
```

### Issue: Triggers not working

**Cause:** Trigger creation failed

**Solution:**
```sql
-- Drop and recreate triggers
DROP TRIGGER IF EXISTS before_insert_class;
DROP TRIGGER IF EXISTS before_insert_subject;
DROP TRIGGER IF EXISTS before_insert_chapter;

-- Then run create_triggers.sql again
```

### Issue: Admin panel not showing auto-capitalization

**Cause:** Railway hasn't deployed the new PHP files yet

**Solution:**
- Wait for Railway deployment to complete
- Check Railway dashboard for deployment status
- Force redeploy if needed

---

## 📊 Deployment Checklist

- [x] Push code to GitHub
- [ ] Wait for Railway auto-deployment (check dashboard)
- [ ] Connect to Railway MySQL
- [ ] Run cleanup script (Script 1)
- [ ] Add UNIQUE constraints (Script 2)
- [ ] Add database triggers (Script 3)
- [ ] Test admin panel auto-capitalization
- [ ] Test duplicate prevention
- [ ] Verify in mobile app
- [ ] Confirm all 5 chapters show for Class 3 English

---

## 🎯 Success Criteria

✅ Railway deployment shows "Success"
✅ Admin panel shows auto-capitalization messages
✅ Duplicate entries are blocked with error messages
✅ Database has UNIQUE constraints
✅ Database has triggers
✅ Mobile app shows correct chapter count (5 chapters)
✅ All chapters are accessible in the app

---

## 💡 Important Notes

1. **Order matters:** Always run cleanup script BEFORE adding constraints
2. **Backup:** Railway should have automatic backups, but verify
3. **Testing:** Test thoroughly on production before announcing to users
4. **Monitoring:** Watch for any errors in Railway logs after deployment
