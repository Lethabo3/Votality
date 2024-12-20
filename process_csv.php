<?php
require_once 'document_uploader.php';

$uploader = new DocumentUploader();

// The name of your CSV file
$csvFileName = 'stedi_data.csv';

try {
    $uploader->uploadCSV($csvFileName);
    echo "CSV file processed and stored successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}