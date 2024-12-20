<?php
require_once 'stedi_ai_config.php';
require_once 'logging.php';

class QdrantService {
    private $pythonScript = 'python qdrant_service.py';

    public function createCollection() {
        $output = shell_exec($this->pythonScript . ' create_collection 2>&1');
        logMessage("Collection creation output: " . $output);
        return $output;
    }

    public function addDocument($text, $metadata = []) {
        $escapedText = escapeshellarg($text);
        $output = shell_exec($this->pythonScript . " add_document $escapedText 2>&1");
        logMessage("Document addition output: " . $output);
        return $output;
    }

    public function searchSimilarDocuments($query, $limit = 5) {
        $escapedQuery = escapeshellarg($query);
        $output = shell_exec($this->pythonScript . " search $escapedQuery 2>&1");
        logMessage("Raw search output: " . $output);
        
        if (empty($output)) {
            logMessage("Empty output from Python script");
            return [];
        }
        
        $results = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("JSON decode error: " . json_last_error_msg());
            return [];
        }
        
        logMessage("Decoded search results: " . print_r($results, true));
        return $results;
    }
    public function collectionExists() {
        $output = shell_exec($this->pythonScript . ' collection_exists 2>&1');
        logMessage("Collection existence check output: " . $output);
        return trim($output) === 'True';
    }

    public function getCollectionInfo() {
        $output = shell_exec($this->pythonScript . ' get_collection_info 2>&1');
        logMessage("Collection info output: " . $output);
        return json_decode($output, true);
    }
}   