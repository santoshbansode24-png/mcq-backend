<?php
/**
 * AI Global Helpers
 */

use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

if (!function_exists('extractTextFromPdf')) {
    function extractTextFromPdf($filePath) {
        if (!class_exists('Smalot\PdfParser\Parser')) {
            throw new Exception("PDF Parser library missing.");
        }
        try {
            $parser = new Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $text = '';
            $pageCount = min(count($pages), 10);
            for ($i = 0; $i < $pageCount; $i++) {
                $text .= $pages[$i]->getText() . " ";
            }
            return preg_replace('/\s+/', ' ', $text);
        } catch (Throwable $e) {
            return "";
        }
    }
}

if (!function_exists('extractTextFromWord')) {
    function extractTextFromWord($filePath) {
        try {
            $phpWord = IOFactory::load($filePath);
            $text = '';
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . " ";
                    }
                }
            }
            return $text;
        } catch (Exception $e) {
            return "";
        }
    }
}
?>
