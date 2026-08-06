<?php
/**
 * Export PDF Notes Endpoint
 * Generates a clean, printable PDF study document for Veeru Lens AI Notes
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
} else {
    require_once __DIR__ . '/../config/db.php';
}

$jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($jobId <= 0) {
    die("Invalid Job ID");
}

try {
    $stmt = $pdo->prepare("SELECT file_name, study_content FROM pdf_study_jobs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        die("Study document not found");
    }

    $fileName = htmlspecialchars($job['file_name'] ?? 'Study Notes');
    $contentRaw = $job['study_content'] ?? '';

    $notes = [];
    if (!empty($contentRaw)) {
        $decoded = json_decode($contentRaw, true);
        if (is_array($decoded)) {
            $notes = $decoded['notes'] ?? ($decoded['Notes'] ?? ($decoded['smart_notes'] ?? []));
        }
    }

    $definitions = $notes['definitions'] ?? ($notes['Definitions'] ?? []);
    $keyFacts = $notes['key_facts'] ?? ($notes['keyFacts'] ?? ($notes['Key_facts'] ?? []));
    $coreConcepts = $notes['core_concepts'] ?? ($notes['coreConcepts'] ?? ($notes['Core_concepts'] ?? []));

} catch (Exception $e) {
    die("Error retrieving notes: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veeru Notes - <?php echo $fileName; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
            padding: 30px 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            padding: 40px;
            border: 1px solid #e2e8f0;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-badge {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: #ffffff;
            font-weight: 800;
            font-size: 18px;
            padding: 6px 14px;
            border-radius: 10px;
            letter-spacing: 0.5px;
        }

        .doc-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 5px;
        }

        .print-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
            transition: all 0.2s ease;
        }

        .print-btn:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }

        .section {
            margin-bottom: 35px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-header.blue { color: #0284c7; }
        .section-header.amber { color: #d97706; }
        .section-header.purple { color: #7e22ce; }

        .card-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .bullet-card {
            background: #f8fafc;
            border-left: 4px solid #cbd5e1;
            padding: 14px 18px;
            border-radius: 0 10px 10px 0;
            font-size: 15px;
            color: #334155;
            font-weight: 500;
        }

        .bullet-card.blue { border-left-color: #38bdf8; background: #f0f9ff; }
        .bullet-card.amber { border-left-color: #f59e0b; background: #fffbeb; }
        .bullet-card.purple { border-left-color: #a855f7; background: #faf5ff; }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .container {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <div>
                <div class="brand">
                    <span class="brand-badge">VEERU LENS</span>
                    <span style="font-size: 13px; color: #64748b; font-weight: 600;">AI Revision Notes</span>
                </div>
                <h1 class="doc-title"><?php echo $fileName; ?></h1>
            </div>
            <button class="print-btn" onclick="window.print()">📥 Download / Print PDF</button>
        </div>

        <?php if (!empty($definitions)): ?>
            <div class="section">
                <h2 class="section-header blue">📖 Key Definitions</h2>
                <div class="card-list">
                    <?php foreach ($definitions as $item): ?>
                        <div class="bullet-card blue"><?php echo htmlspecialchars($item); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($keyFacts)): ?>
            <div class="section">
                <h2 class="section-header amber">⚡ Essential Facts</h2>
                <div class="card-list">
                    <?php foreach ($keyFacts as $item): ?>
                        <div class="bullet-card amber"><?php echo htmlspecialchars($item); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($coreConcepts)): ?>
            <div class="section">
                <h2 class="section-header purple">🧠 Core Concepts</h2>
                <div class="card-list">
                    <?php foreach ($coreConcepts as $item): ?>
                        <div class="bullet-card purple"><?php echo htmlspecialchars($item); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($definitions) && empty($keyFacts) && empty($coreConcepts)): ?>
            <div style="text-align: center; padding: 40px; color: #64748b;">
                <p>No revision notes available for this document yet.</p>
            </div>
        <?php endif; ?>

        <div class="footer">
            Generated by Veeru AI Learning Platform • www.veeruapp.in
        </div>
    </div>

</body>
</html>
