<?php
require_once 'stedi_ai_config.php';
require_once 'logging.php';

class MarketDataService {
    private $finnhubApiKey;
    private $finnhubApiUrl = 'https://finnhub.io/api/v1';
    private $cacheFile = 'market_data_cache.json';
    private $cacheDuration = 300; // 5 minutes
    private $batchSize = 10; // Adjust based on Finnhub's limits

    public function __construct() {
        $this->finnhubApiKey = FINNHUB_API_KEY;
    }

    public function fetchMarketDataByCategory($category) {
        logMessage("Fetching market data for category: $category");
        if ($category !== 'stocks') {
            throw new Exception("Only 'stocks' category is supported");
        }

        if ($this->isCacheValid()) {
            logMessage("Returning cached market data");
            return $this->getCachedData();
        }

        $symbols = $this->getStockSymbols();
        $batches = array_chunk($symbols, $this->batchSize);
        $marketData = [];

        foreach ($batches as $batch) {
            try {
                logMessage("Fetching batch data for symbols: " . implode(', ', $batch));
                $batchData = $this->fetchBatchStockData($batch);
                $marketData = array_merge($marketData, $batchData);
            } catch (Exception $e) {
                logMessage("Error fetching batch data: " . $e->getMessage());
                // Continue with the next batch instead of breaking the entire process
            }
        }

        $this->cacheData($marketData);
        return $marketData;
    }

    private function getStockSymbols() {
        return [
            'AAPL', 'MSFT', 'AMZN', 'GOOGL', 'META', 
            'TSLA', 'BRK.B', 'JPM', 'JNJ', 'V', 
            'PG', 'UNH', 'MA', 'NVDA', 'HD', 
            'DIS', 'BAC', 'ADBE', 'CRM', 'NFLX'
        ];
    }

    private function fetchBatchStockData($symbols) {
        $symbolString = implode(',', $symbols);
        $quoteUrl = $this->finnhubApiUrl . "/quote?symbol={$symbolString}&token=" . $this->finnhubApiKey;
        $profileUrl = $this->finnhubApiUrl . "/stock/profile2?symbol={$symbolString}&token=" . $this->finnhubApiKey;

        logMessage("Fetching quote data for symbols: $symbolString");
        $quoteResponse = $this->makeApiRequest($quoteUrl);
        logMessage("Fetching profile data for symbols: $symbolString");
        $profileResponse = $this->makeApiRequest($profileUrl);

        $batchData = [];
        foreach ($symbols as $symbol) {
            if (isset($quoteResponse[$symbol]['c']) && isset($profileResponse[$symbol]['name'])) {
                $batchData[] = [
                    'symbol' => $symbol,
                    'name' => $profileResponse[$symbol]['name'],
                    'price' => $quoteResponse[$symbol]['c'],
                    'change' => $quoteResponse[$symbol]['d'],
                    'changePercent' => $quoteResponse[$symbol]['dp'],
                ];
            } else {
                $batchData[] = $this->formatErrorData($symbol, "Data not available");
            }
        }

        return $batchData;
    }

    private function makeApiRequest($url) {
        logMessage("Making API request to: $url");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API request failed with HTTP code $httpCode");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse API response: " . json_last_error_msg());
        }

        logMessage("API request successful");
        return $data;
    }

    private function formatErrorData($symbol, $errorMessage) {
        return [
            'symbol' => $symbol,
            'name' => $this->getStockName($symbol),
            'price' => null,
            'change' => null,
            'changePercent' => null,
            'error' => $errorMessage
        ];
    }

    private function getStockName($symbol) {
        $names = [
            'AAPL' => 'Apple Inc.',
            'MSFT' => 'Microsoft Corporation',
            'AMZN' => 'Amazon.com Inc.',
            'GOOGL' => 'Alphabet Inc.',
            'META' => 'Meta Platforms Inc.',
            'TSLA' => 'Tesla, Inc.',
            'BRK.B' => 'Berkshire Hathaway Inc.',
            'JPM' => 'JPMorgan Chase & Co.',
            'JNJ' => 'Johnson & Johnson',
            'V' => 'Visa Inc.',
            'PG' => 'Procter & Gamble Company',
            'UNH' => 'UnitedHealth Group Incorporated',
            'MA' => 'Mastercard Incorporated',
            'NVDA' => 'NVIDIA Corporation',
            'HD' => 'The Home Depot, Inc.',
            'DIS' => 'The Walt Disney Company',
            'BAC' => 'Bank of America Corporation',
            'ADBE' => 'Adobe Inc.',
            'CRM' => 'Salesforce.com, inc.',
            'NFLX' => 'Netflix, Inc.'
        ];
        return $names[$symbol] ?? $symbol;
    }

    private function isCacheValid() {
        if (!file_exists($this->cacheFile)) {
            return false;
        }
        $cacheTime = filemtime($this->cacheFile);
        return (time() - $cacheTime) < $this->cacheDuration;
    }

    private function getCachedData() {
        return json_decode(file_get_contents($this->cacheFile), true);
    }

    private function cacheData($data) {
        file_put_contents($this->cacheFile, json_encode($data));
        logMessage("Market data cached successfully");
    }
}