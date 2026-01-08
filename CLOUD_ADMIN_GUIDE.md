# ☁️ The "Cloud Admin" Upgrade (Future Project)

You asked: *"Can we work from anywhere / purchase a domain?"*

## The Short Answer
**Yes, but it requires a "Phase 2" upgrade.**
Right now, you should **NOT** do this because you will lose data (images/PDFs) if you try it with the current code.

## The Problem: "The Amnesia Effect"
Railway (and most modern clouds) has a rule: **"I forget everything new when I restart."**

1.  **Current Setup:**
    *   You upload an image (`cat.png`) ➡️ It saves to `backend/uploads/cat.png`.
    *   On your Laptop: It stays there forever. Safe. ✅
    *   On Cloud: It stays there for 1-2 hours. When Railway restarts (which it does daily), **poof!** `cat.png` is deleted. ❌

2.  **The Result:**
    *   You add a chapter from a cafe. It works.
    *   Next day, students see "Image Not Found".

## The Solution: "Cloud Storage" (S3)
To fix this, we need to change the code to not save files to the "Disk", but save them to a "Storage Bucket" (like AWS S3, Cloudinary, or Google Cloud Storage).

**Requirements for Cloud Admin:**
1.  **Domain Name:** Buy `veeru-admin.com` (~$12/year).
2.  **Storage Account:** Sign up for AWS S3 or Cloudinary.
3.  **Code Change:** Rewrite `upload.php` to send files to S3 instead of the `uploads` folder.

## My Recommendation
**Finish "Phase 1" (The APK) first.**
1.  Launch the App.
2.  Get students using it.
3.  Manage data from your flexible "Home Base" (Laptop) for now.

Once the app is successful, we can start "Phase 2" to rewrite the upload system for the Cloud.
