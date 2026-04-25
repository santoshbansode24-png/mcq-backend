<?php
/**
 * Admin — Weekly WhatsApp Report Manager
 * Veeru App
 *
 * Lets admin preview and manually trigger weekly WhatsApp reports.
 */

session_start();
// Basic admin guard (reuse your existing admin auth if available)
// if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }

$secret    = getenv('CRON_SECRET') ?: 'veeru_weekly_2026';
$apiBase   = 'https://api.veeruapp.in/backend/api';
$previewUrl = "{$apiBase}/weekly_report.php?secret={$secret}&dry_run=1";
$sendAllUrl = "{$apiBase}/weekly_report.php?secret={$secret}";
$sendOneUrl = "{$apiBase}/weekly_report.php?secret={$secret}&user_id=";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Weekly WhatsApp Reports — Veeru Admin</title>
<!-- Modern Admin CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin_theme.css">
</head>
<body>

<div class="header">
  <h1>📊 Weekly WhatsApp Report Manager</h1>
  <p>Send personalized weekly progress reports to all Veeru users via WhatsApp</p>
</div>

<div class="container">

  <!-- Sample Message Preview -->
  <div class="card">
    <h2>👁️ Preview Sample Message</h2>
    <p style="color:#94a3b8; font-size:13px; margin-bottom:12px;">
      See exactly what your users will receive before sending. Uses dry_run mode — no messages sent.
    </p>
    <button class="btn btn-blue" onclick="previewAll()">
      <span id="previewSpinner" style="display:none" class="spinner"></span>
      🔍 Preview All Users (Dry Run)
    </button>
    <div id="previewResult" class="preview-box" style="display:none"></div>
  </div>

  <!-- Test Single User -->
  <div class="card">
    <h2>🧪 Test — Send to One User</h2>
    <p style="color:#94a3b8; font-size:13px; margin-bottom:12px;">
      Enter a User ID to send a real WhatsApp message to that one user only.
    </p>
    <div class="user-test">
      <input type="number" id="testUserId" placeholder="Enter User ID (e.g. 1)" min="1">
      <button class="btn btn-yellow" onclick="sendToOne()">
        <span id="oneSpinner" style="display:none" class="spinner"></span>
        📤 Send to This User
      </button>
    </div>
    <div id="oneResult" class="preview-box" style="display:none; margin-top:12px;"></div>
  </div>

  <!-- Send to All -->
  <div class="card">
    <h2>🚀 Send to ALL Users</h2>
    <p style="color:#94a3b8; font-size:13px; margin-bottom:12px;">
      ⚠️ This sends a real WhatsApp message to every user with a mobile number.
      Make sure you've previewed first!
    </p>
    <button class="btn btn-red" onclick="sendAll()">
      <span id="allSpinner" style="display:none" class="spinner"></span>
      📣 Send Weekly Report to ALL Users
    </button>
    <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
      <input type="checkbox" id="forceSend">
      <label for="forceSend" style="font-size: 13px; color: #94a3b8;">Force send (bypass Sunday rule & duplicate check)</label>
    </div>
    <div id="allResult" style="display:none; margin-top:16px;">
      <table>
        <thead>
          <tr><th>User ID</th><th>Name</th><th>Phone</th><th>Status</th></tr>
        </thead>
        <tbody id="allResultBody"></tbody>
      </table>
    </div>
  </div>

  <!-- Cron Setup -->
  <div class="card">
    <h2>⏰ Automated Weekly Schedule Setup</h2>
    <p style="color:#94a3b8; font-size:13px; margin-bottom:12px;">
      Use <strong>cron-job.org</strong> (free) to automatically send reports every Sunday at 7 PM IST.
    </p>
    <div class="cron-info">
      <p style="color:#93c5fd; font-size:13px; font-weight:600;">Step 1 — Sign up free at:</p>
      <code>https://cron-job.org</code>

      <p style="color:#93c5fd; font-size:13px; font-weight:600; margin-top:12px;">Step 2 — Create a new cron job with this URL:</p>
      <code><?php echo htmlspecialchars("{$sendAllUrl}"); ?></code>

      <p style="color:#93c5fd; font-size:13px; font-weight:600; margin-top:12px;">Step 3 — Set schedule to:</p>
      <code>Every Sunday at 13:30 UTC  (= 7:00 PM IST)</code>

      <p style="color:#93c5fd; font-size:13px; font-weight:600; margin-top:12px;">Step 4 — Add CRON_SECRET to Railway env vars:</p>
      <code>CRON_SECRET = veeru_weekly_2026</code>
    </div>
  </div>

</div>

<script>
const apiBase   = '<?php echo $apiBase;   ?>';
const secret    = '<?php echo $secret;    ?>';

function showSpinner(id, show) {
  document.getElementById(id).style.display = show ? 'inline-block' : 'none';
}

async function previewAll() {
  showSpinner('previewSpinner', true);
  const box = document.getElementById('previewResult');
  box.style.display = 'block';
  box.textContent = 'Loading preview...';
  try {
    const res  = await fetch(`${apiBase}/weekly_report.php?secret=${secret}&dry_run=1`);
    const data = await res.json();
    box.textContent = JSON.stringify(data, null, 2);
  } catch(e) {
    box.textContent = 'Error: ' + e.message;
  }
  showSpinner('previewSpinner', false);
}

async function sendToOne() {
  const uid = document.getElementById('testUserId').value;
  if (!uid) { alert('Please enter a User ID'); return; }
  showSpinner('oneSpinner', true);
  const box = document.getElementById('oneResult');
  box.style.display = 'block';
  box.textContent = 'Sending...';
  try {
    const res  = await fetch(`${apiBase}/weekly_report.php?secret=${secret}&user_id=${uid}`);
    const data = await res.json();
    box.textContent = JSON.stringify(data, null, 2);
  } catch(e) {
    box.textContent = 'Error: ' + e.message;
  }
  showSpinner('oneSpinner', false);
}

async function sendAll() {
  if (!confirm('⚠️ This will send WhatsApp messages to ALL users. Are you sure?')) return;
  showSpinner('allSpinner', true);
  document.getElementById('allResult').style.display = 'none';
  const force = document.getElementById('forceSend') && document.getElementById('forceSend').checked ? '&force=1' : '';
  try {
    const res  = await fetch(`${apiBase}/weekly_report.php?secret=${secret}${force}`);
    const data = await res.json();
    const tbody = document.getElementById('allResultBody');
    tbody.innerHTML = '';
    (data.results || []).forEach(r => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${r.user_id}</td>
        <td>${r.name}</td>
        <td>${r.phone || '—'}</td>
        <td class="${r.sent ? 'status-ok' : 'status-err'}">${r.sent ? '✅ Sent' : (r.error ? '❌ ' + r.error : '❌ Failed')}</td>
      `;
      tbody.appendChild(row);
    });
    document.getElementById('allResult').style.display = 'block';
  } catch(e) {
    alert('Error: ' + e.message);
  }
  showSpinner('allSpinner', false);
}
</script>
</body>
</html>
