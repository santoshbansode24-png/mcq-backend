<?php
/**
 * Text Normalization Helper
 * Veeru - MCQ Admin Panel
 * 
 * Purpose: Normalize text inputs to ensure consistency across the database
 * Features:
 * - Convert to UPPERCASE
 * - Trim whitespace
 * - Remove extra spaces
 * - Prevent SQL injection
 */

/**
 * Normalize text to UPPERCASE with trimming
 * 
 * @param string $text The text to normalize
 * @return string Normalized text in UPPERCASE
 */
function normalizeText($text) {
    if (empty($text)) {
        return '';
    }
    
    // Convert to string if not already
    $text = (string) $text;
    
    // Trim leading/trailing whitespace
    $text = trim($text);
    
    // Replace multiple spaces with single space
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Convert to UPPERCASE
    $text = strtoupper($text);
    
    return $text;
}

/**
 * Normalize class name
 * 
 * @param string $className The class name to normalize
 * @return string Normalized class name
 */
function normalizeClassName($className) {
    return normalizeText($className);
}

/**
 * Normalize subject name
 * 
 * @param string $subjectName The subject name to normalize
 * @return string Normalized subject name
 */
function normalizeSubjectName($subjectName) {
    return normalizeText($subjectName);
}

/**
 * Normalize chapter name
 * 
 * @param string $chapterName The chapter name to normalize
 * @return string Normalized chapter name
 */
function normalizeChapterName($chapterName) {
    return normalizeText($chapterName);
}

/**
 * Check if a class name already exists for a given board
 * 
 * @param PDO $pdo Database connection
 * @param string $className Class name to check
 * @param int $boardId Board ID
 * @param int|null $excludeId Class ID to exclude (for updates)
 * @return bool True if duplicate exists, false otherwise
 */
function isDuplicateClass($pdo, $className, $boardId, $excludeId = null) {
    $normalizedName = normalizeClassName($className);
    
    $sql = "SELECT COUNT(*) FROM classes 
            WHERE UPPER(class_name) = ? AND board_id = ?";
    $params = [$normalizedName, $boardId];
    
    if ($excludeId !== null) {
        $sql .= " AND class_id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if a subject name already exists for a given class
 * 
 * @param PDO $pdo Database connection
 * @param string $subjectName Subject name to check
 * @param int $classId Class ID
 * @param int|null $excludeId Subject ID to exclude (for updates)
 * @return bool True if duplicate exists, false otherwise
 */
function isDuplicateSubject($pdo, $subjectName, $classId, $excludeId = null) {
    $normalizedName = normalizeSubjectName($subjectName);
    
    $sql = "SELECT COUNT(*) FROM subjects 
            WHERE UPPER(subject_name) = ? AND class_id = ?";
    $params = [$normalizedName, $classId];
    
    if ($excludeId !== null) {
        $sql .= " AND subject_id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if a chapter name already exists for a given subject
 * 
 * @param PDO $pdo Database connection
 * @param string $chapterName Chapter name to check
 * @param int $subjectId Subject ID
 * @param int|null $excludeId Chapter ID to exclude (for updates)
 * @return bool True if duplicate exists, false otherwise
 */
function isDuplicateChapter($pdo, $chapterName, $subjectId, $excludeId = null) {
    $normalizedName = normalizeChapterName($chapterName);
    
    $sql = "SELECT COUNT(*) FROM chapters 
            WHERE UPPER(chapter_name) = ? AND subject_id = ?";
    $params = [$normalizedName, $subjectId];
    
    if ($excludeId !== null) {
        $sql .= " AND chapter_id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() > 0;
}

?>
