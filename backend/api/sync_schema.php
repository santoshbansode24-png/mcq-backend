<?php
/**
 * Master Database Sync for Railway
 * Automatically creates all missing tables for progress tracking, sync, and more.
 * 
 * Instructions: Visit https://api.veeruapp.in/backend/api/sync_schema.php in your browser.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== VEERU DATABASE SCHEMA SYNC ===\n\n";

$sql_chunks = [
    "content_progress" => "CREATE TABLE IF NOT EXISTS `content_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `chapter_id` INT NOT NULL,
        `content_type` ENUM('mcq', 'flashcard') NOT NULL,
        `set_index` INT NOT NULL DEFAULT 0,
        `status` ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
        `score` INT DEFAULT 0,
        `total` INT DEFAULT 0,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_user_content_set` (`user_id`, `chapter_id`, `content_type`, `set_index`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "mcq_attempts" => "CREATE TABLE IF NOT EXISTS mcq_attempts (
        attempt_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        mcq_id INT NOT NULL,
        chapter_id INT NOT NULL,
        selected_answer VARCHAR(1),
        correct_answer VARCHAR(1),
        is_correct BOOLEAN,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_chapter (user_id, chapter_id),
        UNIQUE KEY unique_attempt (user_id, mcq_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "student_progress" => "CREATE TABLE IF NOT EXISTS student_progress (
        progress_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chapter_id INT NOT NULL,
        completed_mcqs INT DEFAULT 0,
        total_mcqs INT DEFAULT 0,
        percentage DECIMAL(5,2) DEFAULT 0.00,
        last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_chapter (user_id, chapter_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "app_content_updates" => "CREATE TABLE IF NOT EXISTS app_content_updates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        version_timestamp BIGINT NOT NULL,
        update_type VARCHAR(20) NOT NULL,
        item_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_class_sync (class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "flashcard_progress" => "CREATE TABLE IF NOT EXISTS flashcard_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        chapter_id INT NOT NULL,
        set_index INT NOT NULL,
        completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attempt (user_id, chapter_id, set_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "pdf_study_jobs" => "CREATE TABLE IF NOT EXISTS `pdf_study_jobs` (
        `job_id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `folder_id` INT DEFAULT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(512) NOT NULL,
        `pdf_base64` LONGTEXT DEFAULT NULL,
        `study_content` LONGTEXT DEFAULT NULL,
        `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        `progress` INT DEFAULT 0,
        `total_pages` INT DEFAULT 0,
        `processed_pages` INT DEFAULT 0,
        `last_processed_chunk` INT DEFAULT 0,
        `total_chunks` INT DEFAULT 1,
        `error_message` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`folder_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "pdf_study_content" => "CREATE TABLE IF NOT EXISTS `pdf_study_content` (
        `content_id` INT AUTO_INCREMENT PRIMARY KEY,
        `job_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `study_pack_json` LONGTEXT NOT NULL,
        `is_synced` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`job_id`) REFERENCES `pdf_study_jobs`(`job_id`) ON DELETE CASCADE,
        INDEX (`user_id`),
        INDEX (`is_synced`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($sql_chunks as $table_name => $sql) {
    echo "Syncing $table_name... ";
    try {
        $pdo->exec($sql);
        echo "✅ OK\n";
    } catch (PDOException $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

// --- SURGICAL REPAIR (Fixing missing columns in existing tables) ---
echo "\n=== RUNNING SURGICAL REPAIRS ===\n";

$repairs = [
    "pdf_study_jobs" => [
        "folder_id" => "ALTER TABLE `pdf_study_jobs` ADD COLUMN `folder_id` INT DEFAULT NULL AFTER `user_id`",
        "pdf_base64" => "ALTER TABLE `pdf_study_jobs` ADD COLUMN `pdf_base64` LONGTEXT DEFAULT NULL AFTER `file_path`",
        "study_content" => "ALTER TABLE `pdf_study_jobs` ADD COLUMN `study_content` LONGTEXT DEFAULT NULL AFTER `pdf_base64`",
        "error_message" => "ALTER TABLE `pdf_study_jobs` ADD COLUMN `error_message` TEXT AFTER `processed_pages`",
        "last_processed_chunk" => "ALTER TABLE `pdf_study_jobs` ADD COLUMN `last_processed_chunk` INT DEFAULT 0 AFTER `processed_pages`",
        "total_chunks" => "ALTER TABLE `pdf_study_jobs` ADD COLUMN `total_chunks` INT DEFAULT 1 AFTER `last_processed_chunk`"
    ]
];

foreach ($repairs as $table => $columns) {
    foreach ($columns as $col => $alter_sql) {
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
        if (!$check) {
            echo "   Adding missing column $col to $table... ";
            try {
                $pdo->exec($alter_sql);
                echo "✅ Fixed\n";
            } catch (Exception $e) {
                echo "❌ Fail: " . $e->getMessage() . "\n";
            }
        }
    }
}

// --- STUCK JOB CLEANUP ---
echo "   Checking for stuck jobs... ";
$stuck = $pdo->exec("UPDATE `pdf_study_jobs` SET `status` = 'failed', `error_message` = 'Job timed out' WHERE `status` = 'processing' AND `updated_at` < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
echo "✅ $stuck job(s) cleared.\n";

// --- QUICK REVISION TABLE & SAMPLE DATA SEEDING ---
echo "\n=== QUICK REVISION SETUP & SAMPLE SEEDING ===\n";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `quick_revision` (
        `revision_id` INT AUTO_INCREMENT PRIMARY KEY,
        `chapter_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `key_points` JSON NOT NULL,
        `summary` TEXT,
        `title_hi` VARCHAR(255) DEFAULT NULL,
        `title_mr` VARCHAR(255) DEFAULT NULL,
        `key_points_hi` JSON DEFAULT NULL,
        `key_points_mr` JSON DEFAULT NULL,
        `summary_hi` TEXT DEFAULT NULL,
        `summary_mr` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_chapter_created` (`chapter_id`, `created_at` DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "✅ Table quick_revision verified.\n";

    // Sample revision points for Chapter 1 (Real Numbers)
    $sampleEN = [
        [
            "q" => "What is Euclid's Division Lemma?",
            "a" => "For any two positive integers a and b, there exist unique integers q and r such that a = bq + r, where 0 <= r < b.",
            "e" => "This lemma forms the foundation of finding the Highest Common Factor (HCF) of two numbers."
        ],
        [
            "q" => "What is the Fundamental Theorem of Arithmetic?",
            "a" => "Every composite number can be uniquely expressed as a product of prime numbers, regardless of order.",
            "e" => "This guarantees that every composite number has a unique prime factorization fingerprint."
        ],
        [
            "q" => "Is √2 a rational or irrational number?",
            "a" => "√2 is an irrational number because it cannot be expressed in the form p/q where p and q are co-prime integers.",
            "e" => "We prove this by the method of contradiction; its decimal expansion is non-terminating and non-repeating."
        ],
        [
            "q" => "How are HCF and LCM related for two positive integers a and b?",
            "a" => "HCF(a, b) × LCM(a, b) = a × b.",
            "e" => "The product of any two numbers is always equal to the product of their HCF and LCM."
        ],
        [
            "q" => "What condition must denominator q satisfy for p/q to have a terminating decimal?",
            "a" => "The prime factorization of q must be of the form 2^n × 5^m, where n and m are non-negative integers.",
            "e" => "If the denominator contains any prime factor other than 2 or 5, the decimal expansion will be non-terminating repeating."
        ]
    ];

    $sampleMR = [
        [
            "q" => "युक्लिडचा भागाकार सिद्धांत काय आहे?",
            "a" => "कोणत्याही दोन धन पूर्णांक a आणि b साठी, a = bq + r (जेथे 0 <= r < b) असे पूर्णांक q आणि r अस्तित्वात असतात.",
            "e" => "हा सिद्धांत दोन संख्यांचा मसावि (HCF) शोधण्यासाठी अत्यंत महत्त्वाचा पाया आहे."
        ],
        [
            "q" => "अंकगणिताचे मूलभूत प्रमेय काय सांगते?",
            "a" => "प्रत्येक संयुक्त संख्या ही मूळ संख्यांचा गुणाकार म्हणून अद्वितीय पद्धतीने मांडता येते.",
            "e" => "या प्रमेयानुसार प्रत्येक संयुक्त संख्येचे मूळ अवयव एकमेव असतात."
        ],
        [
            "q" => "√2 ही परिमेय संख्या आहे की अपरिमेय?",
            "a" => "√2 ही अपरिमेय संख्या आहे कारण ती p/q रूपात मांडता येत नाही.",
            "e" => "याचा दशांश विस्तार अखंड आणि अनावर्ती असतो."
        ],
        [
            "q" => "दोन संख्या a आणि b यांच्या मसावि आणि लसाविमधील संबंध काय आहे?",
            "a" => "मसावि(a, b) × लसावि(a, b) = a × b.",
            "e" => "कोणत्याही दोन संख्यांचा गुणाकार त्यांच्या मसावि व लसाविच्या गुणाकाराएवढा असतो."
        ],
        [
            "q" => "p/q या परिमेय संख्येचे दशांश रूप शांत असण्यासाठी छेद q ची अट काय आहे?",
            "a" => "छेद q चे मूळ अवयव 2^n × 5^m या स्वरूपात असावे लागतात.",
            "e" => "जर छेदात 2 आणि 5 व्यतिरिक्त इतर कोणताही मूळ अवयव आला, तर रूप अखंड आवर्ती होते."
        ]
    ];

    $sampleHI = [
        [
            "q" => "यूक्लिड विभाजन प्रमेयिका क्या है?",
            "a" => "किन्हीं दो धनात्मक पूर्णांकों a और b के लिए, a = bq + r (जहाँ 0 <= r < b) संतुष्ट करने वाले अद्वितीय पूर्णांक q और r होते हैं.",
            "e" => "यह प्रमेयिका दो संख्याओं का महत्तम समापवर्तक (HCF) ज्ञात करने का मुख्य आधार है."
        ],
        [
            "q" => "अंकगणित की आधारभूत प्रमेय क्या है?",
            "a" => "प्रत्येक भाज्य संख्या को अभाज्य संख्याओं के एक अद्वितीय गुणनफल के रूप में व्यक्त किया जा सकता है.",
            "e" => "यह सिद्ध करता है कि प्रत्येक भाज्य संख्या का अभाज्य गुणनखंड अद्वितीय होता है."
        ],
        [
            "q" => "क्या √2 एक परिमेय संख्या है या अपरिमेय?",
            "a" => "√2 एक अपरिमेय संख्या है क्योंकि इसे p/q के रूप में व्यक्त नहीं किया जा सकता.",
            "e" => "इसका दशमलव प्रसार अशांत और अनावर्ती होता है."
        ],
        [
            "q" => "दो धनात्मक पूर्णांकों a और b के HCF और LCM में क्या संबंध है?",
            "a" => "HCF(a, b) × LCM(a, b) = a × b.",
            "e" => "किन्हीं दो संख्याओं का गुणनफल हमेशा उनके HCF और LCM के गुणनफल के बराबर होता है."
        ],
        [
            "q" => "परिमेय संख्या p/q का दशमलव प्रसार शांत होने के लिए हर q की शर्त क्या है?",
            "a" => "हर q का अभाज्य गुणनखंड 2^n × 5^m के रूप का होना चाहिए.",
            "e" => "यदि हर में 2 या 5 के अलावा कोई अन्य अभाज्य गुणनखंड आता है, तो प्रसार अशांत आवर्ती होगा."
        ]
    ];

    $targetChapterId = 1;
    $checkQ = $pdo->prepare("SELECT revision_id FROM quick_revision WHERE chapter_id = ?");
    $checkQ->execute([$targetChapterId]);
    $existingRev = $checkQ->fetch(PDO::FETCH_ASSOC);

    $jsonEN = json_encode($sampleEN, JSON_UNESCAPED_UNICODE);
    $jsonMR = json_encode($sampleMR, JSON_UNESCAPED_UNICODE);
    $jsonHI = json_encode($sampleHI, JSON_UNESCAPED_UNICODE);

    if ($existingRev) {
        $stmtUpdate = $pdo->prepare("UPDATE quick_revision SET 
            title = 'Real Numbers - Quick Revision',
            key_points = ?,
            summary = 'Master the key concepts of Real Numbers, Euclid Lemma, Fundamental Theorem, and Decimals.',
            title_mr = 'वास्तविक संख्या - जलद पुनरावलोकन',
            key_points_mr = ?,
            summary_mr = 'युक्लिडचा भागाकार सिद्धांत, मूलभूत प्रमेय आणि परिमेय संख्यांची महत्त्वाची सूत्रे.',
            title_hi = 'वास्तविक संख्याएँ - त्वरित पुनरावलोकन',
            key_points_hi = ?,
            summary_hi = 'यूक्लिड विभाजन प्रमेयिका, अंकगणित की आधारभूत प्रमेय और महत्वपूर्ण सूत्र.',
            created_at = NOW()
            WHERE chapter_id = ?");
        $stmtUpdate->execute([$jsonEN, $jsonMR, $jsonHI, $targetChapterId]);
        echo "✅ Chapter 1 sample quick revision updated successfully!\n";
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO quick_revision (
            chapter_id, title, key_points, summary,
            title_mr, key_points_mr, summary_mr,
            title_hi, key_points_hi, summary_hi
        ) VALUES (?, 'Real Numbers - Quick Revision', ?, 'Master the key concepts of Real Numbers, Euclid Lemma, Fundamental Theorem, and Decimals.',
            'वास्तविक संख्या - जलद पुनरावलोकन', ?, 'युक्लिडचा भागाकार सिद्धांत, मूलभूत प्रमेय आणि परिमेय संख्यांची महत्त्वाची सूत्रे.',
            'वास्तविक संख्याएँ - त्वरित पुनरावलोकन', ?, 'यूक्लिड विभाजन प्रमेयिका, अंकगणित की आधारभूत प्रमेय और महत्वपूर्ण सूत्र.'
        )");
        $stmtInsert->execute([$targetChapterId, $jsonEN, $jsonMR, $jsonHI]);
        echo "✅ Chapter 1 sample quick revision inserted successfully!\n";
    }
} catch (Exception $e) {
    echo "❌ Quick Revision Seed Error: " . $e->getMessage() . "\n";
}

echo "\nSchema sync completed!";
?>
