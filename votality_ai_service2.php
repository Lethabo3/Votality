    <?php
    require_once 'stedi_ai_config.php';

    class VotalityAIService {
        private $apiKey;
        private $apiUrl;
        private $openExchangeRatesApiKey;
        private $benzingaApiKey;
        private $finnhubApiKey;
        private $tavilyApiKey;
        private $tavilyApiUrl;
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
            $this->tavilyApiKey = TAVILY_API_KEY;
            $this->tavilyApiUrl = TAVILY_API_URL;
            $this->openExchangeRatesApiKey = '269df838ea8c4de68315c97baf07c7b6';
            $this->benzingaApiKey = '685f0ad2fe3f4facb3da0aeacb27b76b';
            $this->finnhubApiKey = 'crnm7tpr01qt44di3q5gcrnm7tpr01qt44di3q60';
            $this->marketauxApiKey = 'o4VnvcRmaBZeK4eBHPJr8KP3xN8gMBTedxHGkCNz';
            $this->nasdaqDataLinkApiKey = 'VGV68j1nV9w9Zn3vwbsG';
        }

        public function generateResponse($message, $chatId) {
            try {
                // Step 1: Start conversation tracking and logging
                $this->addToHistory('user', $message);
                error_log("Starting response generation for chatId: " . $chatId . " with message: " . $message);
        
                // Step 2: Get market and news data through Tavily search
                $contextData = $this->getRelevantTavilyData($message);
                error_log("Tavily context data retrieved: " . json_encode($contextData));
        
                // Step 3: Prepare enhanced instructions with the gathered data
                $enhancedPrompt = $this->prepareInstructions($contextData, $message);
                error_log("Enhanced prompt prepared with length: " . strlen($enhancedPrompt));
        
                // Step 4: Construct the Gemini API request
                $aiRequest = [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'text' => $enhancedPrompt . "\n\nUser message: " . $message
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
                        'maxOutputTokens' => 400,
                        'stopSequences' => []
                    ]
                ];
        
                // Step 5: Make the API call to Gemini
                $ch = curl_init($this->apiUrl . '?key=' . $this->apiKey);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($aiRequest),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ],
                    CURLOPT_TIMEOUT => 30
                ]);
        
                // Step 6: Handle the API response
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
        
                // Step 7: Validate and process the response
                if ($curlError || $httpCode !== 200 || empty($response)) {
                    throw new Exception("API call failed: " . ($curlError ?: "HTTP $httpCode"));
                }
        
                $result = json_decode($response, true);
                if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    throw new Exception("Invalid response structure from Gemini API");
                }
        
                // Step 8: Clean and format the response
                $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'];
                $cleanedResponse = $this->removeAsterisks($aiResponse);
                
                // Step 9: Update conversation history and return
                $this->addToHistory('ai', $cleanedResponse);
                return $cleanedResponse;
        
            } catch (Exception $e) {
                error_log("Error in generateResponse: " . $e->getMessage());
                return "I apologize, but I encountered an error processing your request. Please try again in a moment.";
            }
        }

        private function getRelevantTavilyData($userQuery) {
            try {
                error_log("Starting Tavily API request for query: " . $userQuery);
                
                // Prepare search parameters for financial data
                $searchParams = [
                    'api_key' => TAVILY_API_KEY,
                    'query' => $userQuery,
                    'search_depth' => 'advanced',  // Get more detailed results
                    'include_answer' => true,      // Get AI-generated summary
                    'max_results' => 5,            // Get top 5 most relevant results
                    'topic' => 'news',            // Focus on news content
                    'time_range' => 'day',        // Get very recent information
                    'include_domains' => [
                        'finance.yahoo.com',
                        'bloomberg.com',
                        'reuters.com',
                        'ft.com',
                        'wsj.com',
                        'marketwatch.com',
                        'cnbc.com'
                    ]
                ];
        
                $ch = curl_init(TAVILY_API_URL . '/search');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($searchParams),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json'
                    ],
                    CURLOPT_TIMEOUT => 30
                ]);
        
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
        
                if ($httpCode !== 200) {
                    throw new Exception("Tavily API returned status code: " . $httpCode);
                }
        
                $searchResults = json_decode($response, true);
                
                // Log the structure of results for debugging
                error_log("Tavily Response Structure: " . json_encode([
                    'has_answer' => isset($searchResults['answer']),
                    'result_count' => count($searchResults['results'] ?? []),
                    'response_time' => $searchResults['response_time'] ?? null
                ]));
        
                // Validate and return results
                if ($searchResults && isset($searchResults['results'])) {
                    return [
                        'answer' => $searchResults['answer'] ?? null,
                        'results' => array_map(function($result) {
                            return [
                                'title' => $result['title'],
                                'content' => $result['content'],
                                'url' => $result['url'],
                                'score' => $result['score'],
                                'published_date' => $result['published_date'] ?? null
                            ];
                        }, $searchResults['results'])
                    ];
                }
        
                return null;
        
            } catch (Exception $e) {
                error_log("Tavily fetch error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                return null;
            }
        }
        
        // This function builds an optimized search query to find market data
        private function buildFinancialSearchQuery($userQuery) {
            // Extract potential stock symbols or company names
            $symbols = $this->extractFinancialInstrument($userQuery);
            
            if ($symbols) {
                // If we found a symbol, create a targeted search
                return "latest stock price market data " . $symbols['symbol'] . 
                       " current price change percentage " . 
                       date('Y-m-d') . " trading information";
            }
            
            // If no specific symbol, create a general market search
            return $userQuery . " latest market price trading data " . date('Y-m-d');
        }
        
        // This function extracts market data from Tavily search results
        private function extractMarketDataFromResults($results, $userQuery) {
            $marketData = [
                'price' => null,
                'change' => null,
                'change_percentage' => null,
                'company_name' => null,
                'symbol' => null,
                'timestamp' => null,
                'source_url' => null
            ];
        
            foreach ($results as $result) {
                $content = $result['content'];
                
                // Look for price patterns like "$123.45" or "123.45 USD"
                if (preg_match('/\$(\d+(\.\d{2})?)|(\d+(\.\d{2})?)\s*(USD|dollars)/i', $content, $matches)) {
                    $marketData['price'] = floatval($matches[1]);
                }
                
                // Look for percentage changes like "+2.34%" or "-1.23%"
                if (preg_match('/([+-]?\d+(\.\d{2})?%)/i', $content, $matches)) {
                    $marketData['change_percentage'] = floatval($matches[1]);
                }
                
                // If we found both price and change, we can consider this data valid
                if ($marketData['price'] !== null && $marketData['change_percentage'] !== null) {
                    $marketData['timestamp'] = time();
                    $marketData['source_url'] = $result['url'];
                    break;
                }
            }
        
            return $marketData;
        }

        private function processTavilyResults($response) {
            $processedResults = [];
            
            foreach ($response['results'] as $result) {
                $processedResults[] = [
                    'title' => $result['title'],
                    'content' => $result['content'],
                    'url' => $result['url'],
                    'score' => $result['score'],
                    'published_date' => $result['published_date'] ?? null
                ];
            }

            return [
                'results' => $processedResults,
                'answer' => $response['answer'] ?? null,
                'response_time' => $response['response_time']
            ];
        }

        private function prepareEnhancedContext($message, $marketData, $economicData, $searchData) {
            return [
                'query' => $message,
                'market_data' => $marketData,
                'economic_data' => $economicData,
                'search_results' => $searchData ? $searchData['results'] : [],
                'tavily_answer' => $searchData ? $searchData['answer'] : null
            ];
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

        private function prepareInstructions($contextData, $userMessage) {
            // Step 1: Initialize base instructions
            $instructions = "You are Votality, a knowledgeable and detailed AI assistant for financial analysis. ";
            $instructions .= "Provide comprehensive and insightful financial information with a focus on specific statistics and numerical data.\n\n";
        
            // Step 2: Add market context from Tavily search results
            if ($contextData && !empty($contextData['results'])) {
                $instructions .= "LATEST MARKET DEVELOPMENTS (You MUST reference at least one in your response):\n";
                
                foreach ($contextData['results'] as $index => $result) {
                    $timestamp = isset($result['published_date']) ? 
                                date('Y-m-d H:i', strtotime($result['published_date'])) : 
                                'Recent';
                    
                    $instructions .= "\nDEVELOPMENT " . ($index + 1) . " (" . $timestamp . "):\n";
                    $instructions .= "Title: " . $result['title'] . "\n";
                    $instructions .= "Source: " . parse_url($result['url'], PHP_URL_HOST) . "\n";
                    $instructions .= "Key Details: " . substr($result['content'], 0, 250) . "...\n";
                    
                    // Extract and add any market data found
                    if (isset($result['extracted_data'])) {
                        $instructions .= "Market Data: " . json_encode($result['extracted_data']) . "\n";
                    }
                }
            }
        
            // Step 3: Add response format requirements
            $instructions .= "\n\nRESPONSE REQUIREMENTS:
        1. Begin with the most relevant market development from above
        2. Keep your total response under 1301 characters
        3. Use this format for any specific market data:
           CompanyName|Symbol|CurrentPrice|PriceChange
        4. Focus on forward-looking implications rather than just historical data
        5. Provide specific numbers and statistics when available
        6. Use formal language and avoid metaphors or examples
        7. Highlight any unusual market patterns or divergences\n\n";
        
            // Step 4: Add user context guidelines
            $instructions .= "USER CONTEXT GUIDELINES:
        1. Match technical depth to the user's apparent knowledge level
        2. Reveal institutional trading patterns when relevant
        3. Connect seemingly unrelated market events
        4. Focus on data-driven insights rather than general observations\n\n";
        
            // Step 5: Add any special handling based on user message
            if (stripos($userMessage, 'price') !== false || 
                stripos($userMessage, 'stock') !== false || 
                stripos($userMessage, 'market') !== false) {
                $instructions .= "IMPORTANT: User is specifically asking about market prices or stock information. ";
                $instructions .= "Prioritize current price data and recent price movements in your response.\n\n";
            }
        
            // Step 6: Add response structure requirements
            $instructions .= "FORMAT YOUR RESPONSE AS:
        [Main analysis with specific data points]
        
        Market Info: (if applicable)
        CompanyName|Symbol|Price|Change
        
        Related Topics:
        1. [Relevant topic]
        2. [Relevant topic]
        3. [Relevant topic]";
        
            return $instructions;
        }
        
        public function clearCache() {
            $this->cache = [];
        }

        public function setCacheDuration($seconds) {
            $this->cacheDuration = max(60, min(3600, $seconds)); // Limit between 1 minute and 1 hour
        }
    }

    ?>