<?php
/**
 * Quick Revision Reel — Teacher Voiceover Script & Cloudflare R2 Audio Helper
 * 1. Rewrites raw Q&A explanations into warm, natural, step-by-step teacher speech
 *    with natural TTS pauses (...) via Gemini.
 * 2. Generates MP3 in memory via Sarvam AI (or Google TTS fallback) — zero Railway disk.
 * 3. Uploads MP3 directly to Cloudflare R2 (deduplicated by md5 hash).
 * Veeru
 */

require_once __DIR__ . '/ai_config.php';
require_once __DIR__ . '/aws-config.php';

/**
 * Build the Teacher-Style Voiceover Prompt for Quick Revision Reels.
 */
function buildReelTeacherPrompt(array $item, string $lang = 'en', string $subjectName = ''): string {
    $langLabel = $lang === 'mr' ? 'Marathi' : ($lang === 'hi' ? 'Hindi' : 'English');
    $question = trim((string)($item['q'] ?? $item['question'] ?? ''));
    $answer   = trim((string)($item['a'] ?? $item['answer'] ?? ''));
    $optA     = trim((string)($item['opt_a'] ?? ''));
    $optB     = trim((string)($item['opt_b'] ?? ''));
    $correct  = trim((string)($item['correct'] ?? 'A'));
    $rawExpl  = trim((string)($item['e'] ?? $item['explanation'] ?? ''));

    $optionsText = ($optA !== '' && $optB !== '')
        ? "Option A: {$optA} | Option B: {$optB}"
        : "Answer: {$answer}";
    $correctText = ($optA !== '' && $optB !== '')
        ? ($correct === 'B' ? "Option B ({$optB})" : "Option A ({$optA})")
        : $answer;

    $subjectLine = $subjectName !== '' ? "Subject: {$subjectName}\n" : '';

    return <<<PROMPT
You are a warm, encouraging Indian school teacher recording a short 15–25 second audio explanation for a Quick Revision Reel.
The student has ALREADY seen the quiz question and options on screen.

Target Language: {$langLabel}
{$subjectLine}Question: {$question}
Options: {$optionsText}
Correct Answer: {$correctText}
Raw Explanation / Notes: {$rawExpl}

TASK:
Write a natural, spoken-word teacher explanation split into 2 or 3 short Story Slides ("steps") that will be read aloud by a Text-to-Speech (TTS) voice.

STRICT STYLE & FLOW RULES:
1. DO NOT say labels like "Question:", "Option A:", "Correct Answer:", or "Explanation:".
2. STEP 1 (Slide 1) — Frame the Question Naturally: Start by briefly explaining what the question is asking in simple everyday words (e.g., "So here, we need to figure out..." / "या प्रश्नात आपल्याला असे विचारले आहे की...").
3. STEP 2 (Slide 2) — Explain Step-by-Step: Walk the student through the concept/reason step by step so they understand why the answer works, then smoothly land on the correct answer.
4. STEP 3 (Slide 3, optional) — Quick Takeaway: End with a crisp 1-line memory tip or real-world takeaway.
5. NATURAL PAUSES & REAL SPEECH (CRITICAL):
   - Use conversational connectors ("Now think about it...", "That's why...", "म्हणूनच...", "लक्षात ठेवा...").
   - Use commas and ellipses (...) wherever a teacher would naturally pause or breathe between thoughts.
   - Keep sentences short (max 12–16 words per sentence).
6. TTS-SAFE FORMATTING (CRITICAL):
   - NO markdown (*, **, #, bullet points, quotes, or brackets).
   - Expand ALL symbols, abbreviations, and formulas into spoken words in {$langLabel} (e.g., "CO₂" -> "carbon dioxide" / "कार्बन डायऑक्साइड", "m/s²" -> "meters per second square", "%" -> "percent" / "टक्के", "=" -> "equals" / "बरोबर").
   - Keep total length across all steps between 45 and 75 words.
7. If Target Language is Marathi, use natural spoken Marathi (Balbharati terms) and write any unavoidable English science term simply so the Marathi TTS pronounces it smoothly.

Return ONLY valid JSON matching this exact schema:
{
  "steps": [
    "Step 1 spoken text with natural pauses...",
    "Step 2 spoken text with natural pauses...",
    "Step 3 takeaway with natural pauses..."
  ]
}
PROMPT;
}

/**
 * Call Gemini to generate teacher-style spoken steps with natural pauses.
 */
function generateTeacherReelSteps(array $item, string $lang = 'en', string $subjectName = ''): array {
    if (!function_exists('callGeminiAPI')) {
        throw new Exception("Gemini helper 'callGeminiAPI' is not available.");
    }

    $prompt = buildReelTeacherPrompt($item, $lang, $subjectName);
    $raw = callGeminiAPI($prompt, [
        'temperature'      => 0.4,
        'maxOutputTokens'  => 1024,
        'responseMimeType' => 'application/json',
    ]);

    $parsed = json_decode($raw, true);
    if (!is_array($parsed) && preg_match('/\{[\s\S]*\}/', $raw, $m)) {
        $parsed = json_decode($m[0], true);
    }

    $steps = $parsed['steps'] ?? [];
    if (!is_array($steps) || empty($steps)) {
        throw new Exception('AI returned invalid steps for reel voiceover.');
    }

    $cleanSteps = [];
    foreach ($steps as $s) {
        $txt = trim(strip_tags((string)$s));
        if ($txt !== '') {
            $cleanSteps[] = $txt;
        }
    }
    if (empty($cleanSteps)) {
        throw new Exception('AI returned empty spoken steps.');
    }

    return $cleanSteps;
}

/**
 * Synthesize MP3 binary in memory using Sarvam AI (preferred for Indian voices) or Google TTS fallback.
 */
function synthesizeTtsMp3Binary(string $text, string $lang = 'en'): string {
    $hasDevanagari = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    if ($hasDevanagari || $lang === 'mr') {
        $languageCode = 'mr-IN';
        $speaker = 'rupali';
    } elseif ($lang === 'hi') {
        $languageCode = 'hi-IN';
        $speaker = 'ritu';
    } else {
        $languageCode = 'en-IN';
        $speaker = 'kavya';
    }

    $sarvamKey = trim(getenv('SARVAM_API_KEY') ?: (defined('SARVAM_API_KEY') ? SARVAM_API_KEY : ''));
    $googleKey = trim(getenv('GOOGLE_API_KEY') ?: (defined('GOOGLE_API_KEY') ? GOOGLE_API_KEY : ''));

    // 1. Try Sarvam AI first
    if ($sarvamKey !== '') {
        $payload = json_encode([
            'text'                 => mb_substr($text, 0, 500),
            'target_language_code' => $languageCode,
            'speaker'              => $speaker,
            'model'                => 'bulbul:v3',
            'pace'                 => 0.92,
            'output_audio_codec'   => 'mp3',
        ]);
        $ch = curl_init('https://api.sarvam.ai/text-to-speech');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'api-subscription-key: ' . $sarvamKey,
            ],
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $res) {
            $data = json_decode($res, true);
            if (!empty($data['audios'][0])) {
                $bin = base64_decode($data['audios'][0]);
                if ($bin !== false && strlen($bin) > 200) {
                    return $bin;
                }
            }
        }
    }

    // 2. Google Cloud TTS fallback
    if ($googleKey !== '') {
        $gLang = $languageCode === 'mr-IN' ? 'mr-IN' : ($languageCode === 'hi-IN' ? 'hi-IN' : 'en-IN');
        $payload = json_encode([
            'input'       => ['text' => $text],
            'voice'       => ['languageCode' => $gLang, 'ssmlGender' => 'FEMALE'],
            'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => 0.92],
        ]);
        $ch = curl_init('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $googleKey);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $res) {
            $data = json_decode($res, true);
            if (!empty($data['audioContent'])) {
                $bin = base64_decode($data['audioContent']);
                if ($bin !== false && strlen($bin) > 200) {
                    return $bin;
                }
            }
        }
    }

    throw new Exception('No TTS provider (Sarvam AI / Google TTS) succeeded.');
}

/**
 * Upload MP3 binary directly to Cloudflare R2 (deduplicated by text+lang hash).
 * Returns public R2 URL.
 */
function uploadReelMp3ToR2(string $text, string $lang = 'en'): string {
    if (!function_exists('isR2Configured') || !isR2Configured()) {
        throw new Exception('Cloudflare R2 is not configured.');
    }

    $hash = md5($lang . '|' . trim($text));
    $objectKey = "reels/{$hash}.mp3";
    $publicUrl = rtrim(R2_PUBLIC_URL, '/') . '/' . $objectKey;

    $s3 = getS3Client();

    // Skip synthesis & upload if already cached on R2
    if ($s3->doesObjectExist(R2_BUCKET_NAME, $objectKey)) {
        return $publicUrl;
    }

    $mp3Binary = synthesizeTtsMp3Binary($text, $lang);

    $s3->putObject([
        'Bucket'      => R2_BUCKET_NAME,
        'Key'         => $objectKey,
        'Body'        => $mp3Binary,
        'ContentType' => 'audio/mpeg',
        'CacheControl'=> 'public, max-age=31536000',
    ]);

    return $publicUrl;
}

/**
 * Enrich a single Quick Revision item with natural teacher steps + R2 MP3 URLs.
 */
function enrichQuickRevisionItemWithVoiceover(
    array $item,
    string $lang = 'en',
    string $subjectName = '',
    bool $generateAudio = true
): array {
    $steps = generateTeacherReelSteps($item, $lang, $subjectName);
    $item['e'] = implode(' | ', $steps);

    if ($generateAudio && function_exists('isR2Configured') && isR2Configured()) {
        $stepUrls = [];
        foreach ($steps as $stepText) {
            try {
                $stepUrls[] = uploadReelMp3ToR2($stepText, $lang);
            } catch (Throwable $e) {
                $stepUrls[] = '';
                error_log('R2 Reel audio upload failed: ' . $e->getMessage());
            }
        }
        $item['step_audio_urls'] = $stepUrls;
    }

    return $item;
}
