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
} elseif (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    require_once __DIR__ . '/../../config/db.php';
}

if (file_exists(__DIR__ . '/config/ai_config.php')) {
    require_once __DIR__ . '/config/ai_config.php';
} elseif (file_exists(__DIR__ . '/../config/ai_config.php')) {
    require_once __DIR__ . '/../config/ai_config.php';
} else {
    require_once __DIR__ . '/../../config/ai_config.php';
}

$jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($jobId <= 0) {
    die("Invalid Job ID");
}

function parseNotesStructure($inputData, &$definitions, &$keyFacts, &$coreConcepts) {
    if (empty($inputData)) return;

    if (is_string($inputData)) {
        $clean = trim($inputData);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $decoded = @json_decode($clean, true);
        if (is_string($decoded)) {
            $decoded = @json_decode($decoded, true);
        }
        if (is_array($decoded)) {
            $inputData = $decoded;
        }
    }

    if (!is_array($inputData)) return;

    if (isset($inputData[0]) && is_array($inputData[0])) {
        foreach ($inputData as $item) {
            parseNotesStructure($item, $definitions, $keyFacts, $coreConcepts);
        }
        return;
    }

    $notesObj = $inputData['notes'] ?? ($inputData['Notes'] ?? ($inputData['smart_notes'] ?? ($inputData['SmartNotes'] ?? null)));
    if ($notesObj && is_array($notesObj)) {
        parseNotesStructure($notesObj, $definitions, $keyFacts, $coreConcepts);
        return;
    }

    $defList = $inputData['definitions'] ?? ($inputData['Definitions'] ?? ($inputData['definitions_list'] ?? []));
    $factList = $inputData['key_facts'] ?? ($inputData['keyFacts'] ?? ($inputData['Key_facts'] ?? ($inputData['key_points'] ?? [])));
    $conceptList = $inputData['core_concepts'] ?? ($inputData['coreConcepts'] ?? ($inputData['Core_concepts'] ?? ($inputData['concepts'] ?? [])));

    if (is_array($defList)) {
        foreach ($defList as $d) {
            if (is_string($d) && strlen(trim($d)) > 0) $definitions[] = trim($d);
        }
    }
    if (is_array($factList)) {
        foreach ($factList as $f) {
            if (is_string($f) && strlen(trim($f)) > 0) $keyFacts[] = trim($f);
        }
    }
    if (is_array($conceptList)) {
        foreach ($conceptList as $c) {
            if (is_string($c) && strlen(trim($c)) > 0) $coreConcepts[] = trim($c);
        }
    }

    if (empty($definitions) && empty($keyFacts) && empty($coreConcepts)) {
        foreach ($inputData as $k => $v) {
            if (is_string($v) && strlen(trim($v)) > 0) {
                $keyFacts[] = trim($v);
            } elseif (is_array($v)) {
                foreach ($v as $subV) {
                    if (is_string($subV) && strlen(trim($subV)) > 0) {
                        if (stripos((string)$k, 'def') !== false) {
                            $definitions[] = trim($subV);
                        } elseif (stripos((string)$k, 'concept') !== false) {
                            $coreConcepts[] = trim($subV);
                        } else {
                            $keyFacts[] = trim($subV);
                        }
                    }
                }
            }
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT file_name, study_content, extracted_text, pdf_base64 FROM pdf_study_jobs WHERE job_id = ? LIMIT 1");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        die("Study document not found");
    }

    $fileName = htmlspecialchars($job['file_name'] ?? 'Study Notes');
    $contentRaw = $job['study_content'] ?? '';

    // Primary Store Check: Fetch from pdf_study_content table
    $stmtContent = $pdo->prepare("SELECT study_pack_json FROM pdf_study_content WHERE job_id = ? LIMIT 1");
    $stmtContent->execute([$jobId]);
    $contentRow = $stmtContent->fetch(PDO::FETCH_ASSOC);

    if (!empty($contentRow['study_pack_json'])) {
        $contentRaw = $contentRow['study_pack_json'];
    }

    $definitions = [];
    $keyFacts = [];
    $coreConcepts = [];

    // Parse existing notes content
    parseNotesStructure($contentRaw, $definitions, $keyFacts, $coreConcepts);

    // Deduplicate
    $definitions = array_values(array_unique($definitions));
    $keyFacts = array_values(array_unique($keyFacts));
    $coreConcepts = array_values(array_unique($coreConcepts));

    // Dynamic On-The-Fly Fallback Generation if DB notes empty
    if (empty($definitions) && empty($keyFacts) && empty($coreConcepts)) {
        $extractedText = $job['extracted_text'] ?? '';
        $pdfBase64 = $job['pdf_base64'] ?? '';

        if (!empty($extractedText) || !empty($pdfBase64)) {
            $prompt = "You are the Veeru Smart Revision Notes Engine.\nSTRICT PDF GROUND TRUTH DIRECTIVE: Every Note point MUST be derived 100% STRICTLY AND EXCLUSIVELY from the provided document text.\nCRITICAL NATIVE LANGUAGE MANDATE: You MUST detect the language of the provided document text. If written in Marathi (मराठी), EVERY SINGLE note entry (definitions, key_facts, core_concepts) MUST BE WRITTEN ENTIRELY IN MARATHI (मराठी). If English, generate in English. If Hindi, generate in Hindi. Match native language 100%.\nGenerate comprehensive, highly scannable Revision Notes across definitions, key_facts, and core_concepts.\nOutput strict JSON format ONLY:\n{\n  \"notes\": {\n    \"definitions\": [\"Term: Meaning\"],\n    \"key_facts\": [\"Fact statement\"],\n    \"core_concepts\": [\"Concept explanation\"]\n  }\n}";
            $aiText = "";
            if (!empty($extractedText) && function_exists('callGeminiAPI')) {
                $fullPrompt = "SOURCE TEXT DOCUMENT:\n" . substr($extractedText, 0, 35000) . "\n\nINSTRUCTION:\n" . $prompt;
                $aiText = callGeminiAPI($fullPrompt, ['temperature' => 0.2, 'maxOutputTokens' => 8192, 'responseMimeType' => 'application/json']);
            } elseif (!empty($pdfBase64) && function_exists('callGeminiPDF')) {
                $aiText = callGeminiPDF($prompt, $pdfBase64, ['temperature' => 0.2, 'maxOutputTokens' => 8192, 'responseMimeType' => 'application/json']);
            }

            if (!empty($aiText)) {
                parseNotesStructure($aiText, $definitions, $keyFacts, $coreConcepts);
                $definitions = array_values(array_unique($definitions));
                $keyFacts = array_values(array_unique($keyFacts));
                $coreConcepts = array_values(array_unique($coreConcepts));

                if (!empty($definitions) || !empty($keyFacts) || !empty($coreConcepts)) {
                    $saveData = [
                        'notes' => [
                            'definitions' => $definitions,
                            'key_facts' => $keyFacts,
                            'core_concepts' => $coreConcepts
                        ]
                    ];
                    $saveJson = json_encode($saveData, JSON_UNESCAPED_UNICODE);
                    $pdo->prepare("REPLACE INTO pdf_study_content (job_id, user_id, study_pack_json) VALUES (?, ?, ?)")
                        ->execute([$jobId, $userId, $saveJson]);
                }
            }
        }
    }

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
            margin-bottom: 30px;
        }

        .section-header {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header.blue { color: #2563eb; }
        .section-header.amber { color: #d97706; }
        .section-header.purple { color: #7c3aed; }

        .card-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .bullet-card {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 15px;
            line-height: 1.6;
        }

        .bullet-card.blue {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }

        .bullet-card.amber {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .bullet-card.purple {
            background: #f5f3ff;
            border-left: 4px solid #8b5cf6;
            color: #5b21b6;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
        }

        @media print {
            body {
                background: white;
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
