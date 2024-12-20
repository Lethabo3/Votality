<?php
require_once 'stedi_ai_config.php';
require_once 'qdrant_service.php';

class DocumentUploader {
    private $qdrantService;
    private $geminiApiKey;
    private $geminiApiUrl;

    public function __construct() {
        $this->qdrantService = new QdrantService();
        $this->geminiApiKey = GEMINI_API_KEY;
        $this->geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/embedding-001:embedText';
    }

    public function uploadCSV($filePath) {
        if (($handle = fopen($filePath, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $text = implode(" ", $data);
                $this->processAndStoreText($text);
            }
            fclose($handle);
        }
    }

    public function uploadTextDocument($filePath) {
        $text = file_get_contents($filePath);
        $this->processAndStoreText($text);
    }

    private function processAndStoreText($text) {
        // Generate vector embedding
        $vector = $this->generateVector($text);
        
        // Store in Qdrant
        $this->qdrantService->addPoint(uniqid(), $text, $vector);
    }

    private function generateVector($text) {
        $url = $this->geminiApiUrl . '?key=' . $this->geminiApiKey;

        $data = json_encode([
            'text' => $text
        ]);

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => $data
            ]
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === FALSE) { 
            throw new Exception("Failed to generate embedding");
        }
        
        $response = json_decode($result, true);
        return $response['embedding']['value'];
    }

    public function uploadPDF($filePath) {
        // You'll need to install a PDF parser library
        // composer require smalot/pdfparser
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        $this->processAndStoreText($text);
    }
}

// Usage example
$uploader = new DocumentUploader();

// Upload a CSV file
$uploader->uploadCSV('/path/to/your/file.csv');

// Upload a text document
$uploader->uploadTextDocument('/path/to/your/document.txt');

// Upload a PDF document
$uploader->uploadPDF('/path/to/your/document.pdf');