# How to Work Smoothly with AI Agents (For Non-Coders)

It is completely normal for things to break while fixing others. In software, everything is connected like a spider web. When you pull one string (change a feature), it often shakes other parts of the web that you didn't intend to touch.

Here is why it happens and how you can stop it from happening in the future.

## 1. Why does this happen?

*   **The "Blind Spot" Problem:** AI agents are smart, but we don't know your whole app like you do. If you ask us to "Fix the Logout button," we might not know that the Logout button shares logic with the "Profile Save" button. We fix one and accidentally break the other.
*   **The "Fragile Switch" Problem:** As we saw with your "Network Error," simple switches (like `LOCAL` vs `RAILWAY`) affect the *entire* app. If you change it for testing and forget to switch it back, everything breaks.
*   **Hidden Dependencies:** Code is re-used. A "Text Box" component might be used in 10 different screens. If we change the color of the text box for the *Login Screen*, it changes for *all 10 screens*.

## 2. Your New "Safety Protocol" (Checklist)

To prevent this from happening, follow this 3-step process when working with AI:

### A. The "One Thing" Rule
**Never** ask for two unrelated changes at once.
*   ❌ "Fix the login button and also change the color of the home screen."
*   ✅ "Fix the login button." (Wait for it to be done and tested). "Okay, now change the color of the home screen."

### B. The "Context" Context
When you ask for a change, tell the AI what *else* is related.
*   ❌ "Change this list to show 5 items."
*   ✅ "Change this list to show 5 items. Note that this list is also used on the 'Search' screen, so please make sure that one still works too."

### C. The "Regression Check" (Crucial)
After an AI fixes something, **don't just test that one thing.** briefly check the "neighbors."
*   If we fixed **Login**, check **Logout**.
*   If we fixed **Uploading**, check **Deleting**.
*   If we touched **Config**, check the **entire app connection**.

## 3. The "Undo" Button (Your Safety Net)

Since you are not a coder, your best friend is a **Backup**.
Before asking an AI to do something big (like "Refactor the whole app"):

1.  **Copy your folder.** (e.g., Copy `veeru` to `veeru_backup_jan05`).
2.  If the AI breaks everything, you can simply delete the new folder and go back to the backup.
3.  (Advanced): Ask the AI to "Check `git status`" if you are using Git.

## Summary for Next Time
1.  **Small Steps:** One request at a time.
2.  **Over-Explain:** Tell the AI "This might affect X, be careful."
3.  **Test Neighbors:** Check related features after every fix.
