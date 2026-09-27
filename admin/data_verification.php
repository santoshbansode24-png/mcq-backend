<?php
/**
 * Data Verification Center - Veeru Admin Panel
 * On-Demand Verification of MCQs, Flashcards, and Quick Revisions
 * Uses PHP & MySQL Exact and Fuzzy String Matching Algorithms
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit();
}

// Check for Board Selection
if (!isset($_SESSION['admin_selected_board'])) {
    header('Location: select_board.php');
    exit();
}
$selected_board = $_SESSION['admin_selected_board'];
$board_name = $_SESSION['board_name'];

require_once __DIR__ . '/../config/db.php';

$message = '';
$messageType = '';

// -------------------------------------------------------------
// HANDLE SINGLE DELETE ACTION
// -------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] == 'delete_single') {
    $item_type = $_GET['type'] ?? '';
    $item_id = intval($_GET['id'] ?? 0);

    if ($item_id > 0) {
        try {
            if ($item_type === 'mcq') {
                $stmt = $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?");
                $stmt->execute([$item_id]);
                $message = "MCQ #{$item_id} deleted successfully.";
            } elseif ($item_type === 'flashcard') {
                $stmt = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
                $stmt->execute([$item_id]);
                $message = "Flashcard #{$item_id} deleted successfully.";
            } elseif ($item_type === 'quick_revision') {
                $stmt = $pdo->prepare("DELETE FROM quick_revision WHERE revision_id = ?");
                $stmt->execute([$item_id]);
                $message = "Quick Revision #{$item_id} deleted successfully.";
            }
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = "Deletion failed: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// -------------------------------------------------------------
// HANDLE BULK DELETE ACTION
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $selected_items = $_POST['selected_items'] ?? []; // format: ["mcq_12", "flashcard_5"]
    $deleted_count = 0;

    if (!empty($selected_items)) {
        foreach ($selected_items as $item_str) {
            $parts = explode('_', $item_str, 2);
            if (count($parts) === 2) {
                $type = $parts[0];
                $id = intval($parts[1]);

                if ($type === 'mcq') {
                    $pdo->prepare("DELETE FROM mcqs WHERE mcq_id = ?")->execute([$id]);
                    $deleted_count++;
                } elseif ($type === 'flashcard') {
                    $pdo->prepare("DELETE FROM flashcards WHERE id = ?")->execute([$id]);
                    $deleted_count++;
                } elseif ($type === 'quickrevision' || $type === 'quick_revision') {
                    $pdo->prepare("DELETE FROM quick_revision WHERE revision_id = ?")->execute([$id]);
                    $deleted_count++;
                }
            }
        }
        $message = "Bulk Delete completed! Removed {$deleted_count} item(s).";
        $messageType = 'success';
    } else {
        $message = "No items selected for deletion.";
        $messageType = 'error';
    }
}

// -------------------------------------------------------------
// FETCH CLASSES & SUBJECTS FOR DROPDOWNS
// -------------------------------------------------------------
$classes_query = $pdo->prepare("SELECT * FROM classes WHERE board_type = ? ORDER BY class_name ASC");
$classes_query->execute([$selected_board]);
$classes = $classes_query->fetchAll();

$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$selected_content_type = isset($_GET['content_type']) ? $_GET['content_type'] : 'all';

$subjects = [];
if ($selected_class_id > 0) {
    $stmtSub = $pdo->prepare("SELECT * FROM subjects WHERE class_id = ? ORDER BY subject_name ASC");
    $stmtSub->execute([$selected_class_id]);
    $subjects = $stmtSub->fetchAll();
}

// -------------------------------------------------------------
// ON-DEMAND VERIFICATION ENGINE
// -------------------------------------------------------------
$verification_results = [
    'total_analyzed' => 0,
    'duplicates_count' => 0,
    'incorrect_count' => 0,
    'irrelevant_count' => 0,
    'items' => [] // Array of flagged items
];

$has_run = isset($_GET['run']) && $_GET['run'] == '1';

if ($has_run && $selected_class_id > 0 && $selected_subject_id > 0) {

    // Helper text normalizer for semantic comparison (supports English, Marathi, Hindi)
    function normalizeText($str) {
        $str = mb_strtolower(trim(strip_tags($str)), 'UTF-8');
        $str = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', '', $str); // Preserves letters, numbers, and Devanagari matras
        return preg_replace('/\s+/', ' ', $str);
    }

    // Helper: Extract core key entities after stripping question templates and stop-words (Approach 2)
    function extractCoreEntities($str) {
        if (empty($str)) return [];
        $str = mb_strtolower(strip_tags($str), 'UTF-8');
        
        // Remove common question templates / boilerplate prefixes in English, Marathi, Hindi
        $templates = [
            '/\b(which\s+(one\s+)?of\s+(the\s+)?(following|these)\b)/i',
            '/\b(which\s+among\s+(the\s+)?following\b)/i',
            '/\b(what\s+(is|are)\s+(the\s+)?(meaning\s+of|definition\s+of)?\b)/i',
            '/\b(what\s+do\s+you\s+(mean|understand)\s+by\b)/i',
            '/\b(who\s+(was|is)\s+(the\s+)?\b)/i',
            '/\b(who\s+among\s+(the\s+)?following\b)/i',
            '/\b(who\s+(discovered|invented|founded|wrote)\b)/i',
            '/\b(in\s+which\s+(year|place|state|country|city)\b)/i',
            '/\b(where\s+(is|was|are|were)\s+(the\s+)?\b)/i',
            '/\b(when\s+(was|is|did)\s+(the\s+)?\b)/i',
            '/\b(why\s+(is|are|does|do)\s+(the\s+)?\b)/i',
            '/\b(how\s+(many|much|does|is|are)\s+(the\s+)?\b)/i',
            '/\b(name\s+the\s+following|name\s+the\b)/i',
            '/\b(identify\s+the\s+following|identify\s+the\b)/i',
            '/\b(is\s+(called|known\s+as|defined\s+as|termed\s+as)\b)/i',
            '/\b(choose\s+the\s+correct\s+option\b)/i',
            '/\b(fill\s+in\s+the\s+blank(s)?\b)/i',
            '/\b(state\s+whether\s+true\s+or\s+false\b)/i',
            '/\b(खालीलपैकी\s+(कोणता|कोणती|कोणते|कोणत्या|कोणाला)\b)/u',
            '/\b(म्हणजे\s+काय\b)/u',
            '/\b(असे\s+म्हणतात\b)/u',
            '/\b(नावे\s+लिहा|ओळखा|सांगा|स्पष्ट\s+करा\b)/u',
            '/\b(निम्नलिखित\s+में\s+से\s+(कौन|किसे|किस)\b)/u',
            '/\b(किसे\s+कहते\s+हैं|क्या\s+कहलाता\s+है\b)/u',
            '/\b(पहचानिए|बताइए|लिखिए\b)/u'
        ];

        foreach ($templates as $pattern) {
            $str = preg_replace($pattern, ' ', $str);
        }

        $str = preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', $str);

        $stop_words = [
            'what', 'which', 'who', 'where', 'when', 'why', 'how', 'whose', 'whom',
            'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'in', 'on', 'at', 'to', 'for', 'of', 'by', 'from', 'with', 'about',
            'and', 'or', 'not', 'no', 'but', 'that', 'this', 'these', 'those',
            'it', 'its', 'they', 'them', 'their', 'we', 'us', 'our', 'you', 'your',
            'he', 'him', 'his', 'she', 'her', 'can', 'could', 'would', 'should',
            'may', 'might', 'must', 'has', 'have', 'had', 'do', 'does', 'did',
            'comes', 'come', 'get', 'gets', 'given', 'following', 'true', 'false',
            'option', 'options', 'answer', 'correct', 'type', 'types', 'example',
            // Regional question words & auxiliary verbs
            'कोणता', 'कोणती', 'कोणते', 'कोणत्या', 'कोणाला', 'कशापासून', 'कशाने',
            'आहे', 'नाही', 'होते', 'आणि', 'किंवा', 'च्या', 'चे', 'ची', 'ला', 'ने',
            'मिळतो', 'मिळते', 'मिळतात', 'मिळवला', 'मिळवले', 'जातो', 'जाते', 'जातात',
            'झाले', 'झाला', 'झाली', 'येतो', 'येते', 'येतात',
            'कौन', 'किसे', 'किस', 'क्या', 'कहाँ', 'कब',
            'है', 'हैं', 'था', 'थी', 'और', 'या', 'का', 'के', 'की', 'में', 'से', 'को', 'जाता', 'जाती', 'मिलता', 'मिलती'
        ];

        $tokens = preg_split('/\s+/', $str, -1, PREG_SPLIT_NO_EMPTY);
        $entities = [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if (mb_strlen($token, 'UTF-8') < 2) continue;
            if (in_array($token, $stop_words)) continue;
            $entities[] = $token;
        }

        return array_values(array_unique($entities));
    }

    // Helper: Match individual token variations (plurals, postpositions)
    function tokenMatches($t1, $t2) {
        if ($t1 === $t2) return true;
        // Handle English plural / suffix (fibre vs fibres, element vs elements)
        if (rtrim($t1, 's') === rtrim($t2, 's')) return true;
        if (rtrim($t1, 'es') === rtrim($t2, 'es')) return true;
        // Marathi postpositions (मेंढी vs मेंढीपासून, धागा vs धागे)
        $clean1 = preg_replace('/(पासून|मध्ये|तून|साठी|ने|ला|चा|ची|चे|तील)$/u', '', $t1);
        $clean2 = preg_replace('/(पासून|मध्ये|तून|साठी|ने|ला|चा|ची|चे|तील)$/u', '', $t2);
        if (!empty($clean1) && !empty($clean2) && $clean1 === $clean2) return true;
        
        similar_text($t1, $t2, $sim);
        return $sim >= 85.0;
    }

    // Combined Duplicate Detector (Approach 1: Answer-Aware + Approach 2: Key Entity Focus)
    function checkSemanticMCQDuplicate($q1_text, $q1_answer, $q2_text, $q2_answer) {
        $q1_norm = normalizeText($q1_text);
        $q2_norm = normalizeText($q2_text);
        $ans1_norm = normalizeText($q1_answer);
        $ans2_norm = normalizeText($q2_answer);

        // Rule 0: Exact match on both question and answer
        if ($q1_norm === $q2_norm && $ans1_norm === $ans2_norm) {
            return "Exact Duplicate (100% match on question and answer).";
        }

        // Approach 1: Answer-Aware Check
        // If the correct answers are completely different, they CANNOT be duplicates!
        $answers_match = false;
        if (!empty($ans1_norm) && !empty($ans2_norm)) {
            if ($ans1_norm === $ans2_norm) {
                $answers_match = true;
            } else {
                similar_text($ans1_norm, $ans2_norm, $ans_similarity);
                if ($ans_similarity >= 75.0 || str_contains($ans1_norm, $ans2_norm) || str_contains($ans2_norm, $ans1_norm)) {
                    $answers_match = true;
                }
            }
        }

        // If answers do NOT match, it CANNOT be a duplicate question!
        if (!$answers_match) {
            return false;
        }

        // Approach 2: Key Entity Focus (Compare core entities after stripping templates)
        $entities1 = extractCoreEntities($q1_text);
        $entities2 = extractCoreEntities($q2_text);

        if (empty($entities1) || empty($entities2)) {
            similar_text($q1_norm, $q2_norm, $full_similarity);
            if ($full_similarity >= 95.0) {
                return "Near Duplicate (" . round($full_similarity, 1) . "% match with identical answer).";
            }
            return false;
        }

        // Check entity coverage
        $smaller_list = count($entities1) <= count($entities2) ? $entities1 : $entities2;
        $larger_list = count($entities1) <= count($entities2) ? $entities2 : $entities1;

        $matched_count = 0;
        $matched_tokens = [];
        foreach ($smaller_list as $e1) {
            foreach ($larger_list as $e2) {
                if (tokenMatches($e1, $e2)) {
                    $matched_count++;
                    $matched_tokens[] = $e1;
                    break;
                }
            }
        }

        $coverage = count($smaller_list) > 0 ? ($matched_count / count($smaller_list)) : 0;

        // If at least 65% of core entities in the question are identical AND the answer is the same:
        if ($coverage >= 0.65) {
            return "Semantic Duplicate: Both questions target the same concept ('" . implode(', ', array_unique($matched_tokens)) . "') with the same correct answer ('{$q1_answer}').";
        }

        return false;
    }

    function checkSemanticFlashcardDuplicate($f1_front, $f1_back, $f2_front, $f2_back) {
        return checkSemanticMCQDuplicate($f1_front, $f1_back, $f2_front, $f2_back);
    }

    function checkSubjectIrrelevance($text, $subject_name) {
        $subj = mb_strtolower($subject_name);
        $text_lower = mb_strtolower($text);

        $domain_keywords = [
            'history' => ['revolution', 'dynasty', 'emperor', 'treaty', 'viceroy', 'empire', 'mughal', 'freedom fighter', 'ancient india', 'british rule'],
            'civics' => ['constitution', 'parliament', 'lok sabha', 'rajya sabha', 'prime minister', 'president of india', 'supreme court', 'fundamental rights', 'democracy', 'amendment'],
            'geography' => ['latitude', 'longitude', 'equator', 'monsoon', 'tributary', 'plateau', 'himalayas', 'biosphere', 'topography', 'sedimentary'],
            'biology' => ['photosynthesis', 'chlorophyll', 'mitochondria', 'dna', 'rna', 'chromosome', 'stomata', 'xylem', 'phloem', 'rbc', 'wbc', 'hemoglobin', 'digestive system'],
            'physics' => ['refraction', 'reflection', 'newton', 'momentum', 'velocity', 'acceleration', 'resistance', 'voltage', 'gravitational', 'kinetic energy'],
            'chemistry' => ['chemical reaction', 'periodic table', 'atomic number', 'valency', 'isotope', 'covalent', 'ionic bond', 'oxidation', 'reduction', 'h2o', 'nacl'],
            'math' => ['pythagoras', 'hypotenuse', 'quadratic equation', 'trigonometry', 'sin theta', 'cos theta', 'logarithm', 'polynomial', 'derivative', 'integration', 'fraction'],
            'english' => ['synonym', 'antonym', 'noun', 'pronoun', 'verb', 'adjective', 'adverb', 'preposition', 'conjunction', 'past tense', 'passive voice', 'metaphor']
        ];

        $current_domain = 'other';
        if (str_contains($subj, 'physic')) $current_domain = 'physics';
        elseif (str_contains($subj, 'chem')) $current_domain = 'chemistry';
        elseif (str_contains($subj, 'bio') || str_contains($subj, 'sci')) $current_domain = 'science';
        elseif (str_contains($subj, 'math')) $current_domain = 'math';
        elseif (str_contains($subj, 'hist')) $current_domain = 'history';
        elseif (str_contains($subj, 'pol') || str_contains($subj, 'civic')) $current_domain = 'civics';
        elseif (str_contains($subj, 'geog')) $current_domain = 'geography';
        elseif (str_contains($subj, 'eng')) $current_domain = 'english';

        foreach ($domain_keywords as $domain => $keywords) {
            if ($domain === $current_domain) continue;
            if (($current_domain === 'science' || $current_domain === 'physics' || $current_domain === 'chemistry') && in_array($domain, ['physics', 'chemistry', 'biology'])) continue;
            if ($current_domain === 'history' && in_array($domain, ['civics', 'geography'])) continue;

            foreach ($keywords as $kw) {
                if (mb_strpos($text_lower, $kw) !== false) {
                    return "Topic Mismatch: Question contains '{$kw}' which belongs to " . ucfirst($domain) . " (not {$subject_name}).";
                }
            }
        }
        return false;
    }

    // Helper: Detect questions referencing missing pictures, diagrams, figures or "match the pair of things"
    function checkVisualMediaIrrelevance($text) {
        if (empty($text)) return false;
        $t = mb_strtolower($text, 'UTF-8');

        // 1. Regular expression patterns for English visual media references
        $english_patterns = [
            '/\b(in\s+(the\s+)?(that|this|given|following|above|below)?\s*picture)\b/i',
            '/\b(in\s+picture\b)/i',
            '/\b(look\s+at\s+(the\s+)?(this|that|given|following|above|below)?\s*picture)\b/i',
            '/\b(see\s+(the\s+)?(this|that|given|following|above|below)?\s*picture)\b/i',
            '/\b(shown\s+in\s+(the\s+)?(this|that|given|following|above|below)?\s*picture)\b/i',
            '/\b(from\s+(the\s+)?(this|that|given|following|above|below)?\s*picture)\b/i',
            '/\b(as\s+shown\s+in\s+(the\s+)?(that|this|given|following|above|below)?\s*(picture|figure|diagram|image|illustration))\b/i',
            '/\b(match\s+(the\s+)?(pair|pairs)\s+(of\s+things\s+)?in\s+(that|the|this|given)\s+picture)\b/i',
            '/\b(match\s+(the\s+)?pair\s+of\s+things)\b/i',
            '/\b(which\s+(of\s+the\s+following\s+)?picture)\b/i',
            '/\b(identify\s+(from\s+)?(the\s+)?picture)\b/i',
            '/\b(in\s+(the\s+)?(that|this|given|following|above|below)?\s*figure)\b/i',
            '/\b(refer\s+to\s+(the\s+)?(this|that|given|following|above|below)?\s*figure)\b/i',
            '/\b(look\s+at\s+(the\s+)?(this|that|given|following|above|below)?\s*figure)\b/i',
            '/\b(in\s+(the\s+)?(that|this|given|following|above|below)?\s*diagram)\b/i',
            '/\b(shown\s+in\s+(the\s+)?(this|that|given|following|above|below)?\s*diagram)\b/i',
            '/\b(look\s+at\s+(the\s+)?(this|that|given|following|above|below)?\s*diagram)\b/i',
            '/\b(refer\s+to\s+(the\s+)?(this|that|given|following|above|below)?\s*diagram)\b/i',
            '/\b(in\s+(the\s+)?(that|this|given|following|above|below)?\s*image)\b/i',
            '/\b(look\s+at\s+(the\s+)?(this|that|given|following|above|below)?\s*image)\b/i',
            '/\b(shown\s+in\s+(the\s+)?(this|that|given|following|above|below)?\s*image)\b/i',
            '/\b(in\s+(the\s+)?(that|this|given|following|above|below)?\s*illustration)\b/i',
            '/\b(in\s+(the\s+)?(that|this|given|following|above|below)?\s*chart)\b/i',
            '/\b(in\s+(the\s+)?(that|this|given|following|above|below)?\s*map)\b/i',
            '/\b(shown\s+on\s+(the\s+)?(this|that|given|following|above|below)?\s*map)\b/i',
            '/\b(mark\s+on\s+(the\s+)?(this|that|given|following|above|below)?\s*map)\b/i',
            '/\b(given\s+graph|from\s+the\s+graph\s+below)\b/i'
        ];

        foreach ($english_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return "Missing Visual Media: Question refers to visual content ('" . trim($matches[0]) . "'), but no picture/diagram is provided in the app.";
            }
        }

        // 2. Multilingual keywords (Marathi & Hindi)
        $regional_keywords = [
            // Marathi
            'चित्रात' => 'चित्रात (Picture reference)',
            'दिलेल्या चित्रात' => 'दिलेल्या चित्रात (Given picture reference)',
            'चित्रातील' => 'चित्रातील (In the picture)',
            'चित्र पाहून' => 'चित्र पाहून (Look at picture)',
            'खालील चित्रात' => 'खालील चित्रात (Picture below)',
            'चित्रांच्या जोड्या' => 'चित्रांच्या जोड्या (Match pairs in picture)',
            'आकृतीमध्ये' => 'आकृतीमध्ये (In figure/diagram)',
            'दिलेल्या आकृतीत' => 'दिलेल्या आकृतीत (In given figure)',
            'आकृतीवरून' => 'आकृतीवरून (From the figure)',
            'नकाशात' => 'नकाशात (In the map)',
            // Hindi
            'चित्र में' => 'चित्र में (In the picture)',
            'दिए गए चित्र में' => 'दिए गए चित्र में (In given picture)',
            'चित्र देखकर' => 'चित्र देखकर (Look at picture)',
            'चित्र में दर्शाया' => 'चित्र में दर्शाया (Shown in picture)',
            'चित्र का मिलान' => 'चित्र का मिलान (Match picture)',
            'दी गई आकृति में' => 'दी गई आकृति में (In given figure)',
            'आकृति में' => 'आकृति में (In the figure)',
            'मानचित्र में' => 'मानचित्र में (In the map)'
        ];

        foreach ($regional_keywords as $keyword => $desc) {
            if (mb_strpos($t, $keyword) !== false) {
                return "Missing Visual Media: Question refers to visual content ('" . $desc . "'), but no picture/diagram is provided in the app.";
            }
        }

        return false;
    }

    $flagged_items = [];
    $total_analyzed = 0;

    // ---------------------------------------------------------
    // 1. VERIFY MCQs
    // ---------------------------------------------------------
    if ($selected_content_type === 'all' || $selected_content_type === 'mcq') {
        $stmtMCQs = $pdo->prepare("
            SELECT m.*, ch.chapter_name, s.subject_name, c.class_name
            FROM mcqs m
            JOIN chapters ch ON m.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            WHERE s.subject_id = ? AND c.board_type = ?
            ORDER BY m.mcq_id ASC
        ");
        $stmtMCQs->execute([$selected_subject_id, $selected_board]);
        $mcq_list = $stmtMCQs->fetchAll();
        $total_analyzed += count($mcq_list);

        // Fetch cross-subject MCQs for relevance / cross-subject copy check
        $stmtOtherMCQs = $pdo->prepare("
            SELECT m.mcq_id, m.question, s.subject_name
            FROM mcqs m
            JOIN chapters ch ON m.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            WHERE s.subject_id != ?
        ");
        $stmtOtherMCQs->execute([$selected_subject_id]);
        $other_mcqs = $stmtOtherMCQs->fetchAll();

        $seen_mcqs = [];

        foreach ($mcq_list as $index => $mcq) {
            $mcq_id = $mcq['mcq_id'];
            $q_raw = $mcq['question'] ?? '';
            $q_norm = normalizeText($q_raw);
            $opt_a = trim($mcq['option_a'] ?? '');
            $opt_b = trim($mcq['option_b'] ?? '');
            $opt_c = trim($mcq['option_c'] ?? '');
            $opt_d = trim($mcq['option_d'] ?? '');
            $correct = strtolower(trim($mcq['correct_answer'] ?? ''));

            $is_flagged = false;
            $issues = [];

            // A. Incorrect / Malformed Data Check
            $dummy_patterns = ['/^\s*test\s*$/i', '/^\s*asdf\s*$/i', '/^\s*qwerty\s*$/i', '/^\s*sample\s*$/i', '/^\s*\?+\s*$/i', '/^\s*xyz\s*$/i', '/^\s*1234\s*$/i'];
            $is_dummy = false;
            foreach ($dummy_patterns as $pattern) {
                if (preg_match($pattern, $q_raw)) { $is_dummy = true; break; }
            }

            if (empty($q_norm) || strlen($q_norm) < 6) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Question text is empty or too short (< 6 chars).'];
            } elseif ($is_dummy) {
                $issues[] = ['type' => 'incorrect', 'reason' => "Question contains dummy test placeholder text ('{$q_raw}')."];
            }

            if ($opt_a === '' || $opt_b === '') {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Missing basic options (Option A or Option B is blank).'];
            }

            if (!in_array($correct, ['a', 'b', 'c', 'd', 'option_a', 'option_b', 'option_c', 'option_d'])) {
                $issues[] = ['type' => 'incorrect', 'reason' => "Invalid correct_answer key: '{$mcq['correct_answer']}'. Must be a, b, c, or d."];
            } else {
                $correct_letter = str_replace('option_', '', $correct);
                $target_option_val = $mcq["option_" . $correct_letter] ?? '';
                if (empty(trim($target_option_val))) {
                    $issues[] = ['type' => 'incorrect', 'reason' => "Correct answer option '(" . strtoupper($correct_letter) . ")' is empty or blank!"];
                }
            }

            $options_array = array_filter([$opt_a, $opt_b, $opt_c, $opt_d]);
            if (count($options_array) !== count(array_unique($options_array))) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Duplicate option choices found within this question.'];
            }

            // B. Duplicate Check (Combined Approach 1: Answer-Aware & Approach 2: Key Entity Focus)
            if (!empty($q_norm)) {
                $dup_found = false;
                foreach ($seen_mcqs as $prev_id => $prev_item) {
                    $dup_reason = checkSemanticMCQDuplicate($q_raw, $target_option_val, $prev_item['question'], $prev_item['answer']);
                    if ($dup_reason) {
                        $issues[] = ['type' => 'duplicate', 'reason' => "MCQ #{$prev_id}: " . $dup_reason];
                        $dup_found = true;
                        break;
                    }
                }
                if (!$dup_found) {
                    $seen_mcqs[$mcq_id] = [
                        'question' => $q_raw,
                        'answer' => $target_option_val
                    ];
                }
            }

            // C. Irrelevant / Cross-Subject, Domain Mismatch & Missing Picture Check
            if (!empty($q_norm)) {
                // 1. Missing Picture / Visual Reference Check (Pictures, diagrams, "match the pair of things")
                $visual_issue = checkVisualMediaIrrelevance($q_raw);
                if (!$visual_issue) {
                    $opts_combined = "$opt_a $opt_b $opt_c $opt_d";
                    $visual_opt_issue = checkVisualMediaIrrelevance($opts_combined);
                    if ($visual_opt_issue) {
                        $visual_issue = "Missing Visual Media: Option choices refer to visual content (pictures/diagrams) which are not shown in the app.";
                    }
                }
                if ($visual_issue) {
                    $issues[] = ['type' => 'irrelevant', 'reason' => $visual_issue];
                }

                // 2. Cross-subject matching
                foreach ($other_mcqs as $other) {
                    $other_norm = normalizeText($other['question']);
                    if ($q_norm === $other_norm) {
                        $issues[] = ['type' => 'irrelevant', 'reason' => "Cross-Subject Duplicate: Matches MCQ #{$other['mcq_id']} in '{$other['subject_name']}'."];
                        break;
                    } else {
                        similar_text($q_norm, $other_norm, $other_percent);
                        if ($other_percent >= 80.0) {
                            $issues[] = ['type' => 'irrelevant', 'reason' => "Cross-Subject Copy: " . round($other_percent, 1) . "% match with MCQ #{$other['mcq_id']} in '{$other['subject_name']}'."];
                            break;
                        }
                    }
                }

                // 3. Subject Domain Keyword Mismatch
                if (empty($issues)) {
                    $subj_name = $mcq['subject_name'] ?? '';
                    $domain_issue = checkSubjectIrrelevance($q_raw, $subj_name);
                    if ($domain_issue) {
                        $issues[] = ['type' => 'irrelevant', 'reason' => $domain_issue];
                    }
                }
            }

            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $flagged_items[] = [
                        'type' => 'MCQ',
                        'item_key' => 'mcq_' . $mcq_id,
                        'db_type' => 'mcq',
                        'id' => $mcq_id,
                        'preview' => $mcq['question'],
                        'details' => "Options: (A) {$opt_a} | (B) {$opt_b} | Correct: " . strtoupper($correct),
                        'chapter' => $mcq['chapter_name'],
                        'flag_category' => $issue['type'],
                        'reason' => $issue['reason']
                    ];
                }
            }
        }
    }

    // ---------------------------------------------------------
    // 2. VERIFY FLASHCARDS
    // ---------------------------------------------------------
    if ($selected_content_type === 'all' || $selected_content_type === 'flashcard') {
        $stmtFC = $pdo->prepare("
            SELECT f.*, ch.chapter_name, s.subject_name, c.class_name
            FROM flashcards f
            JOIN chapters ch ON f.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            WHERE s.subject_id = ? AND c.board_type = ?
            ORDER BY f.id ASC
        ");
        $stmtFC->execute([$selected_subject_id, $selected_board]);
        $fc_list = $stmtFC->fetchAll();
        $total_analyzed += count($fc_list);

        $seen_fcs = [];

        foreach ($fc_list as $fc) {
            $fc_id = $fc['id'];
            $front = trim($fc['question_front'] ?? '');
            $back = trim($fc['answer_back'] ?? '');
            $front_norm = normalizeText($front);
            $back_norm = normalizeText($back);

            $issues = [];

            // A. Incorrect / Malformed Check
            if (empty($front_norm)) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Question Front is empty.'];
            }
            if (empty($back_norm)) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Answer Back is empty.'];
            }
            if (!empty($front_norm) && $front_norm === $back_norm) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Question Front and Answer Back are identical.'];
            }

            // B. Duplicate Check (Combined Approach 1: Answer-Aware & Approach 2: Key Entity Focus)
            if (!empty($front_norm)) {
                $dup_found = false;
                foreach ($seen_fcs as $prev_id => $prev_item) {
                    $dup_reason = checkSemanticFlashcardDuplicate($front, $back, $prev_item['front'], $prev_item['back']);
                    if ($dup_reason) {
                        $issues[] = ['type' => 'duplicate', 'reason' => "Flashcard #{$prev_id}: " . $dup_reason];
                        $dup_found = true;
                        break;
                    }
                }
                if (!$dup_found) {
                    $seen_fcs[$fc_id] = [
                        'front' => $front,
                        'back' => $back
                    ];
                }
            }

            // C. Irrelevant / Missing Visual Reference Check
            $fc_visual_issue = checkVisualMediaIrrelevance("$front $back");
            if ($fc_visual_issue) {
                $issues[] = ['type' => 'irrelevant', 'reason' => $fc_visual_issue];
            }

            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $flagged_items[] = [
                        'type' => 'Flashcard',
                        'item_key' => 'flashcard_' . $fc_id,
                        'db_type' => 'flashcard',
                        'id' => $fc_id,
                        'preview' => "Front: " . $front,
                        'details' => "Back: " . $back,
                        'chapter' => $fc['chapter_name'],
                        'flag_category' => $issue['type'],
                        'reason' => $issue['reason']
                    ];
                }
            }
        }
    }

    // ---------------------------------------------------------
    // 3. VERIFY QUICK REVISIONS
    // ---------------------------------------------------------
    if ($selected_content_type === 'all' || $selected_content_type === 'quick_revision') {
        $stmtQR = $pdo->prepare("
            SELECT qr.*, ch.chapter_name, s.subject_name, c.class_name
            FROM quick_revision qr
            JOIN chapters ch ON qr.chapter_id = ch.chapter_id
            JOIN subjects s ON ch.subject_id = s.subject_id
            JOIN classes c ON s.class_id = c.class_id
            WHERE s.subject_id = ? AND c.board_type = ?
            ORDER BY qr.revision_id ASC
        ");
        $stmtQR->execute([$selected_subject_id, $selected_board]);
        $qr_list = $stmtQR->fetchAll();
        $total_analyzed += count($qr_list);

        $seen_qr_titles = [];

        foreach ($qr_list as $qr) {
            $qr_id = $qr['revision_id'];
            $title = trim($qr['title'] ?? '');
            $summary = trim($qr['summary'] ?? '');
            $title_norm = normalizeText($title);

            $issues = [];

            // A. Incorrect / Malformed Check
            if (empty($title_norm)) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Quick Revision title is empty.'];
            }
            if (empty(trim($summary)) && empty($qr['key_points'])) {
                $issues[] = ['type' => 'incorrect', 'reason' => 'Summary content and key points are both empty.'];
            }

            // B. Duplicate Check
            if (!empty($title_norm)) {
                if (isset($seen_qr_titles[$title_norm])) {
                    $orig_id = $seen_qr_titles[$title_norm];
                    $issues[] = ['type' => 'duplicate', 'reason' => "Exact Duplicate Title of Quick Revision #{$orig_id}."];
                } else {
                    $seen_qr_titles[$title_norm] = $qr_id;
                }
            }

            // C. Irrelevant / Missing Visual Reference Check
            $qr_visual_issue = checkVisualMediaIrrelevance("$title $summary " . ($qr['key_points'] ?? ''));
            if ($qr_visual_issue) {
                $issues[] = ['type' => 'irrelevant', 'reason' => $qr_visual_issue];
            }

            if (!empty($issues)) {
                foreach ($issues as $issue) {
                    $flagged_items[] = [
                        'type' => 'Quick Revision',
                        'item_key' => 'quickrevision_' . $qr_id,
                        'db_type' => 'quick_revision',
                        'id' => $qr_id,
                        'preview' => "Title: " . $title,
                        'details' => "Summary: " . (strlen($summary) > 100 ? substr($summary, 0, 100) . '...' : $summary),
                        'chapter' => $qr['chapter_name'],
                        'flag_category' => $issue['type'],
                        'reason' => $issue['reason']
                    ];
                }
            }
        }
    }

    // Populate result counts
    $verification_results['total_analyzed'] = $total_analyzed;
    $verification_results['items'] = $flagged_items;

    foreach ($flagged_items as $item) {
        if ($item['flag_category'] === 'duplicate') $verification_results['duplicates_count']++;
        elseif ($item['flag_category'] === 'incorrect') $verification_results['incorrect_count']++;
        elseif ($item['flag_category'] === 'irrelevant') $verification_results['irrelevant_count']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Verification Center - MCQ Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; color: #333; }
        
        /* Header & Navigation */
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; position: relative; }
        .header h1 { font-size: 24px; font-weight: 600; }
        .center-actions { position: absolute; left: 50%; transform: translateX(-50%); }
        .btn-switch-board { background: #ff9f43; color: white; padding: 10px 25px; border-radius: 50px; text-decoration: none; font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s; display: flex; align-items: center; gap: 8px; border: 2px solid white; font-size: 14px; text-transform: uppercase; }
        .btn-switch-board:hover { background: #ffcd19; color: #333; transform: translateY(-2px) scale(1.05); }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .admin-info { text-align: right; }
        .btn-logout { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; text-decoration: none; font-size: 14px; }
        
        .nav { background: white; padding: 0 40px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .nav ul { list-style: none; display: flex; gap: 5px; overflow-x: auto; }
        .nav li a { display: block; padding: 18px 25px; color: #666; text-decoration: none; font-weight: 500; border-bottom: 3px solid transparent; white-space: nowrap; }
        .nav li a:hover, .nav li a.active { color: #667eea; border-bottom-color: #667eea; font-weight: 600; }

        .container { max-width: 1400px; margin: 30px auto; padding: 0 40px; }
        
        .card { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-title { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        
        /* Filter Form */
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; align-items: end; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 14px; font-weight: 600; color: #555; }
        select, input { padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; outline: none; background: white; }
        select:focus { border-color: #667eea; }
        .btn-verify { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-verify:hover { opacity: 0.95; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102,126,234,0.3); }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 5px solid #ccc; }
        .stat-card.blue { border-left-color: #3b82f6; }
        .stat-card.red { border-left-color: #ef4444; }
        .stat-card.orange { border-left-color: #f97316; }
        .stat-card.yellow { border-left-color: #eab308; }
        .stat-card .label { font-size: 13px; color: #666; font-weight: 500; margin-bottom: 5px; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #111; }

        /* Badges */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 50px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-incorrect { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .badge-irrelevant { background: #ffedd5; color: #ea580c; border: 1px solid #fed7aa; }
        .badge-duplicate { background: #fef9c3; color: #ca8a04; border: 1px solid #fef08a; }
        .badge-type { background: #e0e7ff; color: #4338ca; font-size: 11px; padding: 3px 8px; border-radius: 4px; }

        /* Table & Actions */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; vertical-align: top; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        tr:hover { background: #f8fafc; }
        .btn-delete-single { color: #dc2626; text-decoration: none; font-weight: 600; padding: 6px 12px; background: #fee2e2; border-radius: 6px; font-size: 12px; transition: background 0.2s; }
        .btn-delete-single:hover { background: #fca5a5; }
        .btn-bulk-delete { background: #dc2626; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-bulk-delete:hover { background: #b91c1c; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .filter-tabs { display: flex; gap: 10px; margin-top: 20px; border-bottom: 2px solid #e2e8f0; }
        .tab-link { padding: 10px 20px; font-weight: 600; color: #64748b; cursor: pointer; text-decoration: none; border-bottom: 3px solid transparent; }
        .tab-link.active { color: #4f46e5; border-bottom-color: #4f46e5; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <h1>🔍 Data Verification Center</h1>
        <div class="center-actions">
            <a href="select_board.php" class="btn-switch-board">🔁 Switch Board</a>
        </div>
        <div class="header-right">
            <div class="admin-info">
                <div style="font-weight: 600; margin-bottom: 2px;">
                    <span style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                        <?php echo htmlspecialchars($board_name); ?>
                    </span>
                    &nbsp; <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                </div>
            </div>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="users.php">Users</a></li>
            <li><a href="classes.php">Classes</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="chapters.php">Chapters</a></li>
            <li><a href="mcqs.php">MCQs</a></li>
            <li><a href="videos.php">Videos</a></li>
            <li><a href="notes.php">Notes</a></li>
            <li><a href="flashcards.php">Flashcards</a></li>
            <li><a href="quick_revision.php">Quick Revision</a></li>
            <li><a href="data_verification.php" class="active">Verify Data</a></li>
            <li><a href="content_manager.php">Content Manager</a></li>
            <li><a href="audit_center.php">Audit Center</a></li>
        </ul>
    </nav>

    <div class="container">

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Selection Card -->
        <div class="card">
            <div class="card-title">
                <span>Select Class & Subject to Run Audit</span>
            </div>
            <form method="GET" action="data_verification.php">
                <input type="hidden" name="run" value="1">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="class_id">Target Class</label>
                        <select name="class_id" id="class_id" required onchange="this.form.submit()">
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['class_id']; ?>" <?php echo ($selected_class_id == $c['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['class_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject_id">Target Subject</label>
                        <select name="subject_id" id="subject_id" required <?php echo empty($subjects) ? 'disabled' : ''; ?>>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['subject_id']; ?>" <?php echo ($selected_subject_id == $s['subject_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="content_type">Content Type</label>
                        <select name="content_type" id="content_type">
                            <option value="all" <?php echo ($selected_content_type === 'all') ? 'selected' : ''; ?>>All Content Types</option>
                            <option value="mcq" <?php echo ($selected_content_type === 'mcq') ? 'selected' : ''; ?>>MCQs Only</option>
                            <option value="flashcard" <?php echo ($selected_content_type === 'flashcard') ? 'selected' : ''; ?>>Flashcards Only</option>
                            <option value="quick_revision" <?php echo ($selected_content_type === 'quick_revision') ? 'selected' : ''; ?>>Quick Revisions Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn-verify">⚡ Run Verification Check</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($has_run): ?>
            <!-- Statistics Summary -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="label">Total Items Analyzed</div>
                    <div class="value"><?php echo $verification_results['total_analyzed']; ?></div>
                </div>
                <div class="stat-card red">
                    <div class="label">🔴 Incorrect Data</div>
                    <div class="value"><?php echo $verification_results['incorrect_count']; ?></div>
                </div>
                <div class="stat-card orange">
                    <div class="label">🟠 Irrelevant / Misassigned</div>
                    <div class="value"><?php echo $verification_results['irrelevant_count']; ?></div>
                </div>
                <div class="stat-card yellow">
                    <div class="label">🟡 Duplicate Data</div>
                    <div class="value"><?php echo $verification_results['duplicates_count']; ?></div>
                </div>
            </div>

            <!-- Flagged Items List -->
            <div class="card">
                <div class="card-title">
                    <span>Audit Results & Issue Flags</span>
                    <?php if (!empty($verification_results['items'])): ?>
                        <span style="font-size: 14px; font-weight: normal; color: #64748b;">
                            Found <strong><?php echo count($verification_results['items']); ?></strong> flagged item(s)
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($verification_results['items'])): ?>
                    <div style="text-align: center; padding: 40px; color: #16a34a; font-weight: 600; font-size: 16px;">
                        🎉 Excellent! No duplicates, incorrect options, or irrelevant items found in this Subject!
                    </div>
                <?php else: ?>

                    <!-- Bulk Actions Form -->
                    <form method="POST" action="data_verification.php?run=1&class_id=<?php echo $selected_class_id; ?>&subject_id=<?php echo $selected_subject_id; ?>&content_type=<?php echo $selected_content_type; ?>" onsubmit="return confirm('Are you sure you want to delete all selected flagged items?');">
                        <input type="hidden" name="action" value="bulk_delete">
                        
                        <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                <label for="selectAll" style="font-weight: 600; font-size: 14px; cursor: pointer; margin-left: 5px;">Select All Flagged Items</label>
                            </div>
                            <button type="submit" class="btn-bulk-delete">🗑️ Delete Selected Items</button>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Select</th>
                                    <th style="width: 110px;">Type</th>
                                    <th>Content Preview</th>
                                    <th>Issue Category & Reason</th>
                                    <th style="width: 140px;">Chapter</th>
                                    <th style="width: 90px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($verification_results['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_items[]" value="<?php echo $item['item_key']; ?>" class="item-checkbox">
                                        </td>
                                        <td>
                                            <span class="badge-type"><?php echo htmlspecialchars($item['type']); ?></span>
                                            <br><small style="color: #94a3b8;">#<?php echo $item['id']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['preview']); ?></strong>
                                            <br><small style="color: #64748b; font-size: 12px;"><?php echo htmlspecialchars($item['details']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($item['flag_category'] === 'incorrect'): ?>
                                                <span class="badge badge-incorrect">🔴 Incorrect Data</span>
                                            <?php elseif ($item['flag_category'] === 'irrelevant'): ?>
                                                <span class="badge badge-irrelevant">🟠 Irrelevant</span>
                                            <?php elseif ($item['flag_category'] === 'duplicate'): ?>
                                                <span class="badge badge-duplicate">🟡 Duplicate</span>
                                            <?php endif; ?>
                                            <div style="margin-top: 5px; font-size: 13px; color: #334155;">
                                                <?php echo htmlspecialchars($item['reason']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small style="font-weight: 500; color: #475569;"><?php echo htmlspecialchars($item['chapter']); ?></small>
                                        </td>
                                        <td>
                                            <a href="data_verification.php?run=1&class_id=<?php echo $selected_class_id; ?>&subject_id=<?php echo $selected_subject_id; ?>&content_type=<?php echo $selected_content_type; ?>&action=delete_single&type=<?php echo $item['db_type']; ?>&id=<?php echo $item['id']; ?>" 
                                               class="btn-delete-single"
                                               onclick="return confirm('Delete this <?php echo $item['type']; ?> permanently?');">
                                               Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>

                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function toggleSelectAll(master) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = master.checked);
        }
    </script>
</body>
</html>
