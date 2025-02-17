<?php
require_once 'stedi_ai_config.php';
require_once 'logging.php';
require_once 'qdrant_service.php';

class StediAIService {
    private $apiKey;
    private $apiUrl;
    private $conversationHistory = [];
    private $qdrantService;

    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        $this->apiUrl = GEMINI_API_URL;
        $this->qdrantService = new QdrantService();
        $this->ensurePortfolioDataPopulated();
    }

    private function ensurePortfolioDataPopulated() {
        // Check if the collection exists and has data
        if (!$this->qdrantService->collectionExists() || $this->qdrantService->getCollectionInfo()['points_count'] == 0) {
            $this->populateQdrant();
        }
    }

    public function populateQdrant() {
        // Create the collection if it doesn't exist
        if (!$this->qdrantService->collectionExists()) {
            $this->qdrantService->createCollection();
        }

        // Add Lethabo's portfolio data
        $portfolioData = [
            "Lethabo Sekoto is a Full Stack & AI Developer specializing in creating intelligent web applications.",
            "Lethabo has experience with JavaScript, including React, Vue.js, and Node.js.",
            "Lethabo's Python skills include working with Django and TensorFlow for AI applications.",
            "Lethabo has developed mobile applications using React Native.",
            "Lethabo is proficient in both SQL and NoSQL database management.",
            "Lethabo has experience with DevOps and cloud services, particularly AWS and Docker.",
            "One of Lethabo's key projects is an AI-powered chat application that includes sentiment analysis and language translation.",
            "Lethabo has also built a full-featured e-commerce platform with product recommendations and inventory management.",
            "Another significant project by Lethabo is a mobile fitness tracker with AI-powered workout recommendations.",
            "Lethabo has worked as a Senior Full Stack Developer at TechCorp Inc., leading high-traffic web application development.",
            "Prior to that, Lethabo was an AI Engineer at InnovateAI, developing machine learning models for NLP and computer vision."
        ];

        foreach ($portfolioData as $data) {
            $this->qdrantService->addDocument($data);
        }

        logMessage("Lethabo's portfolio data has been successfully added to Qdrant.");
        return "Lethabo's portfolio data has been successfully added to Qdrant.";
    }
}

echo "Lethabo's portfolio data has been successfully added to Qdrant.";