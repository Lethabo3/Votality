<?php
require_once 'stedi_ai_config.php';

class VotalityAIService {
    private $apiKey;
    private $apiUrl;
    private $openExchangeRatesApiKey;
    private $benzingaApiKey;
    private $finnhubApiKey;
    private $marketauxApiKey;
    private $nasdaqDataLinkApiKey;
    
    // API URLs
    private $openExchangeRatesApiUrl = 'https://openexchangerates.org/api';
    private $benzingaApiUrl = 'https://api.benzinga.com/api/v2/news';
    private $finnhubApiUrl = 'https://finnhub.io/api/v1';
    private $marketauxApiUrl = 'https://api.marketaux.com/v1';
    private $nasdaqDataLinkApiUrl = 'https://data.nasdaq.com/api/v3/';
    
    private $conversationHistory = [];
    private $cache = [];
    private $cacheDuration = 300; // 5 minutes

    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        $this->apiUrl = GEMINI_API_URL;
        $this->openExchangeRatesApiKey = '269df838ea8c4de68315c97baf07c7b6';
        $this->benzingaApiKey = '685f0ad2fe3f4facb3da0aeacb27b76b';
        $this->finnhubApiKey = 'crnm7tpr01qt44di3q5gcrnm7tpr01qt44di3q60';
        $this->marketauxApiKey = 'o4VnvcRmaBZeK4eBHPJr8KP3xN8gMBTedxHGkCNz';
        $this->nasdaqDataLinkApiKey = 'VGV68j1nV9w9Zn3vwbsG';
    }

    public function generateResponse($message, $chatId) {
        try {
            $this->addToHistory('user', $message);
            
            // Log configuration for debugging
            error_log("API URL: " . $this->apiUrl);
            error_log("Using model: gemini-1.5-flash-latest");
            
            // Prepare the request - Note the structure for Gemini 1.5
            $aiRequest = [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => $this->prepareInstructions(null, null) . "\n\nUser message: " . $message
                            ]
                        ]
                    ]
                ],
                'safetySettings' => [
                    [
                        'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                        'threshold' => 'BLOCK_NONE'
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 300,
                    'stopSequences' => []
                ]
            ];
    
            // Log the request payload for debugging
            error_log("Request payload: " . json_encode($aiRequest, JSON_PRETTY_PRINT));
    
            $ch = curl_init($this->apiUrl . '?key=' . $this->apiKey);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($aiRequest),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_VERBOSE => true
            ]);
    
            // Create a temporary file handle for CURL debugging
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
            // Execute the request and capture response details
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Get verbose debug information
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            fclose($verbose);
    
            // Log detailed debug information
            error_log("HTTP Code: " . $httpCode);
            error_log("Curl Error: " . $curlError);
            error_log("Verbose log: " . $verboseLog);
            error_log("Raw response: " . $response);
    
            curl_close($ch);
    
            if ($curlError) {
                throw new Exception("Curl error: " . $curlError);
            }
    
            if ($httpCode !== 200) {
                throw new Exception("API returned non-200 status code: " . $httpCode . ". Response: " . $response);
            }
    
            if (empty($response)) {
                throw new Exception("Empty response received from API");
            }
    
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error. Response received: " . substr($response, 0, 1000));
                throw new Exception("JSON decode error: " . json_last_error_msg());
            }
    
            // Log the decoded response structure
            error_log("Decoded response structure: " . json_encode($result, JSON_PRETTY_PRINT));
    
            // Updated path for response content in Gemini 1.5
            if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                throw new Exception("Unexpected response structure: " . json_encode($result));
            }
    
            $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'];
            $cleanedResponse = $this->removeAsterisks($aiResponse);
            
            $this->addToHistory('ai', $cleanedResponse);
            
            // Log successful response
            error_log("Successfully generated response: " . substr($cleanedResponse, 0, 100) . "...");
            
            return $cleanedResponse;
    
        } catch (Exception $e) {
            error_log("Error in generateResponse: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return "I apologize, but I encountered an error processing your request. Please try again in a moment. Error details: " . $e->getMessage();
        }
    }

    private function extractFinancialInstrument($message) {
        $patterns = [
            'stock' => '/\b[A-Z]{1,5}\b/',
            'forex' => '/\b[A-Z]{3}\/[A-Z]{3}\b/',
            'crypto' => '/\b[A-Z]{3,5}-USD\b/',
            'index' => '/\b(S&P 500|Dow Jones|NASDAQ|FTSE|Nikkei)\b/i'
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return ['type' => $type, 'symbol' => $matches[0]];
            }
        }

        return null;
    }

    private function fetchMarketData($instrument) {
        $cacheKey = "market_data_{$instrument['symbol']}";
        
        // Try to get from cache first
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }
        
        // If not in cache, fetch fresh data
        $freshData = null;
        switch ($instrument['type']) {
            case 'forex':
                $freshData = $this->fetchForexData($instrument['symbol']);
                break;
            case 'crypto':
                $freshData = $this->fetchCryptoData($instrument['symbol']);
                break;
            case 'stock':
                $freshData = $this->fetchStockData($instrument['symbol']);
                break;
            case 'index':
                $freshData = $this->fetchIndexData($instrument['symbol']);
                break;
        }
        
        // Cache the fresh data with a short TTL to ensure freshness
        if ($freshData !== null) {
            $this->cache->set($cacheKey, $freshData, 300); // 5 minute cache
        }
        
        return $freshData;
    }

    private function prepareMarketContext($marketData, $economicData, $news) {
        $context = [
            'timestamp' => time(),
            'market_data' => $marketData ?? [],
            'economic_indicators' => []
        ];
        
        // Format economic indicators if available
        if ($economicData) {
            $context['economic_indicators'] = [
                'gdp' => [
                    'value' => $economicData['GDP'] ?? null,
                    'unit' => 'Percent Change',
                ],
                'unemployment' => [
                    'value' => $economicData['Unemployment Rate'] ?? null,
                    'unit' => 'Percent',
                ],
                'inflation' => [
                    'value' => $economicData['Inflation Rate'] ?? null,
                    'unit' => 'Percent',
                ],
                'fed_rate' => [
                    'value' => $economicData['Federal Funds Rate'] ?? null,
                    'unit' => 'Percent',
                ]
            ];
        }
        
        // Add relevant news if available
        if ($news && !empty($news)) {
            $context['recent_news'] = array_slice($news, 0, 5); // Only include top 5 stories
        }
        
        return $context;
    }

    private function fetchForexData($symbol) {
        $currencies = explode('/', $symbol);
        if (count($currencies) !== 2) {
            return null;
        }

        $base = $currencies[0];
        $target = $currencies[1];

        // Get latest rates
        $url = "{$this->openExchangeRatesApiUrl}/latest.json?app_id={$this->openExchangeRatesApiKey}&base=USD&symbols={$base},{$target}";
        $response = $this->makeApiRequest($url);

        if (!$response || !isset($response['rates'][$base]) || !isset($response['rates'][$target])) {
            return null;
        }

        // Calculate cross rate
        $baseRate = $response['rates'][$base];
        $targetRate = $response['rates'][$target];
        $crossRate = $targetRate / $baseRate;

        // Get historical data
        $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
        $historicalUrl = "{$this->openExchangeRatesApiUrl}/historical/{$yesterdayDate}.json?app_id={$this->openExchangeRatesApiKey}&base=USD&symbols={$base},{$target}";
        $historicalResponse = $this->makeApiRequest($historicalUrl);

        $yesterdayBaseRate = $historicalResponse['rates'][$base];
        $yesterdayTargetRate = $historicalResponse['rates'][$target];
        $yesterdayCrossRate = $yesterdayTargetRate / $yesterdayBaseRate;

        // Calculate changes
        $change = $crossRate - $yesterdayCrossRate;
        $changePercent = ($change / $yesterdayCrossRate) * 100;

        $result = [
            $symbol => [
                'source' => 'OpenExchangeRates',
                'data' => [
                    'rate' => $crossRate,
                    'change' => $change,
                    'change_percent' => $changePercent,
                    'timestamp' => $response['timestamp'],
                    'base_currency' => $base,
                    'quote_currency' => $target
                ]
            ]
        ];

        $this->cache[$symbol] = [
            'time' => time(),
            'data' => $result
        ];

        return $result;
    }

    private function fetchCryptoData($symbol) {
        // Remove -USD suffix if present
        $cryptoSymbol = str_replace('-USD', '', $symbol);
        
        // Try OpenExchangeRates first
        $url = "{$this->openExchangeRatesApiUrl}/latest.json?app_id={$this->openExchangeRatesApiKey}&base=USD&symbols={$cryptoSymbol}";
        $response = $this->makeApiRequest($url);

        if (!$response || !isset($response['rates'][$cryptoSymbol])) {
            // Fallback to Finnhub for crypto data
            return $this->fetchCryptoDataFromFinnhub($symbol);
        }

        // Get historical data for changes
        $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
        $historicalUrl = "{$this->openExchangeRatesApiUrl}/historical/{$yesterdayDate}.json?app_id={$this->openExchangeRatesApiKey}&base=USD&symbols={$cryptoSymbol}";
        $historicalResponse = $this->makeApiRequest($historicalUrl);

        $currentRate = 1 / $response['rates'][$cryptoSymbol];
        $yesterdayRate = 1 / $historicalResponse['rates'][$cryptoSymbol];
        $change = $currentRate - $yesterdayRate;
        $changePercent = ($change / $yesterdayRate) * 100;

        $result = [
            $symbol => [
                'source' => 'OpenExchangeRates',
                'data' => [
                    'price' => $currentRate,
                    'change' => $change,
                    'change_percent' => $changePercent,
                    'timestamp' => $response['timestamp']
                ]
            ]
        ];

        $this->cache[$symbol] = [
            'time' => time(),
            'data' => $result
        ];

        return $result;
    }

    private function fetchCryptoDataFromFinnhub($symbol) {
        $url = "{$this->finnhubApiUrl}/crypto/candle?symbol=BINANCE:{$symbol}&resolution=D&count=2&token={$this->finnhubApiKey}";
        $response = $this->makeApiRequest($url);

        if (!$response || !isset($response['c'])) {
            return null;
        }

        $result = [
            $symbol => [
                'source' => 'Finnhub',
                'data' => [
                    'price' => end($response['c']),
                    'change' => end($response['c']) - $response['c'][0],
                    'change_percent' => ((end($response['c']) - $response['c'][0]) / $response['c'][0]) * 100,
                    'timestamp' => end($response['t'])
                ]
            ]
        ];

        $this->cache[$symbol] = [
            'time' => time(),
            'data' => $result
        ];

        return $result;
    }

    private function fetchStockData($symbol) {
        $url = "{$this->finnhubApiUrl}/quote?symbol={$symbol}&token={$this->finnhubApiKey}";
        $response = $this->makeApiRequest($url);

        if (!$response || !isset($response['c'])) {
            return null;
        }

        $result = [
            $symbol => [
                'source' => 'Finnhub',
                'data' => [
                    'current_price' => $response['c'],
                    'change' => $response['d'],
                    'percent_change' => $response['dp'],
                    'high' => $response['h'],
                    'low' => $response['l'],
                    'open' => $response['o'],
                    'previous_close' => $response['pc'],
                    'timestamp' => time()
                ]
            ]
        ];

        $this->cache[$symbol] = [
            'time' => time(),
            'data' => $result
        ];

        return $result;
    }

    private function fetchIndexData($symbol) {
        return $this->fetchStockData($symbol);
    }

    public function fetchTopStories($limit = 10) {
        $sources = [
            [$this, 'fetchBenzingaNews'],
            [$this, 'fetchFinnhubNews'],
            [$this, 'fetchMarketauxNews'],
        ];

        $allStories = [];

        foreach ($sources as $source) {
            $stories = $source($limit);
            $allStories = array_merge($allStories, $stories);
            if (count($allStories) >= $limit) {
                break;
            }
        }

        usort($allStories, function($a, $b) {
            return strtotime($b['time_published']) - strtotime($a['time_published']);
        });

        return array_slice($allStories, 0, $limit);
    }

    private function fetchBenzingaNews($limit) {
        $url = "{$this->benzingaApiUrl}?token={$this->benzingaApiKey}&pageSize={$limit}";
        $response = $this->makeApiRequest($url);
        $stories = [];

        if ($response && is_array($response)) {
            foreach ($response as $item) {
                $stories[] = [
                    'title' => $item['title'],
                    'summary' => $item['teaser'],
                    'source' => 'Benzinga',
                    'url' => $item['url'],
                    'time_published' => date('YmdHis', strtotime($item['created'])),
                    'overall_sentiment_score' => 0,
                    'overall_sentiment_label' => 'Neutral'
                ];
            }
        }

        return $stories;
    }

    private function fetchFinnhubNews($limit) {
        $url = "{$this->finnhubApiUrl}/news?category=general&token={$this->finnhubApiKey}";
        $response = $this->makeApiRequest($url);
        $stories = [];

        if ($response && is_array($response)) {
            foreach (array_slice($response, 0, $limit) as $item) {
                $stories[] = [
                    'title' => $item['headline'],
                    'summary' => $item['summary'],
                    'source' => $item['source'],
                    'url' => $item['url'],
                    'time_published' => date('YmdHis', $item['datetime']),
                    'overall_sentiment_score' => 0,
                    'overall_sentiment_label' => 'Neutral'
                ];
            }
        }

        return $stories;
    }

    private function fetchMarketauxNews($limit) {
        $url = "{$this->marketauxApiUrl}/news/all?api_token={$this->marketauxApiKey}&limit={$limit}";
        $response = $this->makeApiRequest($url);
        $stories = [];

        if ($response && isset($response['data'])) {
            foreach ($response['data'] as $item) {
                $stories[] = [
                    'title' => $item['title'],
                    'summary' => $item['description'],
                    'source' => $item['source'],
                    'url' => $item['url'],
                    'time_published' => date('YmdHis', strtotime($item['published_at'])),
                    'overall_sentiment_score' => $item['sentiment_score'],
                    'overall_sentiment_label' => $this->getSentimentLabel($item['sentiment_score'])
                ];
            }
        }

        return $stories;
    }

    private function makeApiRequest($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            error_log("API request failed: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("API request failed with HTTP code: " . $httpCode);
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to parse API response: " . json_last_error_msg());
            return null;
        }

        return $data;
    }

    private function getSentimentLabel($score) {
        if ($score > 0.2) return 'Bullish';
        if ($score < -0.2) return 'Bearish';
        return 'Neutral';
    }

    private function removeAsterisks($text) {
        return trim(preg_replace('/\*+/', '', $text));
    }

    private function addToHistory($role, $message) {
        $this->conversationHistory[] = ['role' => $role, 'content' => $message];
        if (count($this->conversationHistory) > 10) { // Keep last 5 exchanges (10 messages)
            array_shift($this->conversationHistory);
        }
    }

    private function getConversationHistoryForAI() {
        $aiHistory = [];
        foreach ($this->conversationHistory as $message) {
            $aiHistory[] = [
                'role' => $message['role'],
                'parts' => [['text' => $message['content']]]
            ];
        }
        return $aiHistory;
    }

    private function prepareInstructions($marketData, $economicData) {
        $instructions = "You are Votality, a knowledgeable and detailed AI assistant for the Votality app. Provide comprehensive and insightful financial information with a focus on specific statistics and numerical data. Guidelines:
        1. Uncover hidden market narratives that connect seemingly unrelated events .
    2. No basic greetings - start with your most compelling insight.
    3. Reveal institutional trading patterns and dark pool movements that retail traders rarely see.
    4. Instead of surface-level price analysis, expose in depth data
    6. Highlight divergences between public narratives and actual market behavior.
    7. No emojis or basic analysis.
    8. Expose intermarket relationships that mainstream analysis misses.
    9. Rather than generic advice, reveal institutional positioning and liquidity flows.
    10. Every response must include at least one non-obvious market insight.
    11. Match your depth to the user's knowledge level.
    12. Focus on forward-looking catalysts rather than backward-looking data.
    13. Speak in simple language, simple diction, make it easy for users to understand you, use easy going diction
    14. Do not mention when anything about your data provider
    15. Never give a response with any of these {},[], or with a response that [something not found]!! Never
    16. Use strictly formal language, do not use methaphors and examples
    17. You only have a 300 tokens for each response, so make the content you output enough 


         Format your response as follows:
        [Your detailed main response here, structured in multiple paragraphs, rich with specific statistics and numerical data]
    
        Related Topics:
        1. [First related topic or question]
        2. [Second related topic or question]
        3. [Third related topic or question]";
    
        if ($marketData) {
            $instructions .= "\n\nLatest market data: " . json_encode($marketData);
        }
        if ($economicData) {
            $instructions .= "\n\nEconomic indicators: " . json_encode($economicData);
        }
    
        $instructions .= "\n\nRemember to incorporate the provided market data and economic indicators into your main response, using the exact figures when relevant.";
    
        return $instructions;
    }

    private function fetchEconomicData() {
        $indicators = [
            'GDP' => 'FRED/GDP',
            'Unemployment Rate' => 'FRED/UNRATE',
            'Inflation Rate' => 'FRED/CPIAUCSL',
            'Federal Funds Rate' => 'FRED/FEDFUNDS'
        ];

        $economicData = [];
        
        foreach ($indicators as $name => $code) {
            $cacheKey = "economic_{$code}";
            
            // Check cache first
            if (isset($this->cache[$cacheKey]) && 
                (time() - $this->cache[$cacheKey]['time'] < $this->cacheDuration)) {
                $economicData[$name] = $this->cache[$cacheKey]['data'];
                continue;
            }

            $url = "{$this->nasdaqDataLinkApiUrl}datasets/{$code}.json?api_key={$this->nasdaqDataLinkApiKey}&rows=1";
            $data = $this->makeApiRequest($url);
            
            if ($data && isset($data['dataset']['data'][0][1])) {
                $economicData[$name] = $data['dataset']['data'][0][1];
                
                // Cache the result
                $this->cache[$cacheKey] = [
                    'time' => time(),
                    'data' => $data['dataset']['data'][0][1]
                ];
            }
        }

        return $economicData;
    }

    public function clearCache() {
        $this->cache = [];
    }

    public function setCacheDuration($seconds) {
        $this->cacheDuration = max(60, min(3600, $seconds)); // Limit between 1 minute and 1 hour
    }
}

?>