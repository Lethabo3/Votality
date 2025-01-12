<?php
require_once 'stedi_ai_config.php';

class VotalityAIService {
    private $apiKey;
    private $apiUrl;
    private $alphaVantageApiKey;
    private $benzingaApiKey;
    private $finnhubApiKey;
    private $marketauxApiKey;
    private $alphaVantageApiUrl = 'https://www.alphavantage.co/query';
    private $benzingaApiUrl = 'https://api.benzinga.com/api/v2/news';
    private $finnhubApiUrl = 'https://finnhub.io/api/v1';
    private $marketauxApiUrl = 'https://api.marketaux.com/v1';
    private $conversationHistory = [];
    private $cache = [];
    private $cacheDuration = 300; // 5 minutes

    private $nasdaqDataLinkApiKey;
    private $nasdaqDataLinkApiUrl = 'https://data.nasdaq.com/api/v3/';

    public function __construct() {
        $this->apiKey = GEMINI_API_KEY;
        $this->apiUrl = GEMINI_API_URL;
        $this->alphaVantageApiKey = ALPHA_VANTAGE_API_KEY;
        $this->benzingaApiKey = '685f0ad2fe3f4facb3da0aeacb27b76b';
        $this->finnhubApiKey = 'crnm7tpr01qt44di3q5gcrnm7tpr01qt44di3q60';
        $this->marketauxApiKey = 'o4VnvcRmaBZeK4eBHPJr8KP3xN8gMBTedxHGkCNz';
        $this->nasdaqDataLinkApiKey = 'VGV68j1nV9w9Zn3vwbsG';
    }


    public function generateResponse($message, $chatId) {
        $this->addToHistory('user', $message);

        $symbol = $this->extractFinancialInstrument($message);
        $marketData = null;
        $economicData = null;

        if ($symbol || preg_match('/(buy|sell|invest|divest)/i', $message)) {
            if ($symbol) {
                $marketData = $this->fetchMarketData($symbol);
            }
            
            if (preg_match('/(buy|sell|invest|divest|econom|gdp|inflation|unemployment|interest rate)/i', $message)) {
                $economicData = $this->fetchEconomicData();
            }
        }

        $instructions = $this->prepareInstructions($marketData, $economicData);
        
        $aiRequest = [
            'contents' => array_merge(
                $this->getConversationHistoryForAI(),
                [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $instructions . "\n\nUser message: " . $message]]
                    ]
                ]
            ),
            'generationConfig' => [
                'temperature' => 0.4,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 850,
            ]
        ];

        $ch = curl_init($this->apiUrl . '?key=' . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($aiRequest));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $aiResponse = $result['candidates'][0]['content']['parts'][0]['text'];
            $cleanedResponse = $this->removeAsterisks($aiResponse);
            
            $this->addToHistory('ai', $cleanedResponse);
            
            return $cleanedResponse;
        } else {
            return "I'm sorry, but I couldn't generate a proper response. Can I help with anything else?";
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
        if (isset($this->cache[$instrument['symbol']]) && (time() - $this->cache[$instrument['symbol']]['time'] < $this->cacheDuration)) {
            return $this->cache[$instrument['symbol']]['data'];
        }

        $function = $this->getAlphaVantageFunction($instrument);
        $url = $this->alphaVantageApiUrl . "?function={$function}&symbol={$instrument['symbol']}&apikey={$this->alphaVantageApiKey}";
        
        $response = file_get_contents($url);
        
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        $result = [
            $instrument['symbol'] => [
                'source' => 'Alpha Vantage',
                'data' => $data
            ]
        ];

        $this->cache[$instrument['symbol']] = [
            'time' => time(),
            'data' => $result
        ];

        return $result;
    }

    private function getAlphaVantageFunction($instrument) {
        switch ($instrument['type']) {
            case 'stock':
                return 'GLOBAL_QUOTE';
            case 'forex':
                return 'FX_DAILY';
            case 'crypto':
                return 'DIGITAL_CURRENCY_DAILY';
            case 'index':
                return 'TIME_SERIES_DAILY';
            default:
                return 'GLOBAL_QUOTE';
        }
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

        return $stories;
    }

    private function fetchFinnhubNews($limit) {
        $url = "{$this->finnhubApiUrl}/news?category=general&token={$this->finnhubApiKey}";
        $response = $this->makeApiRequest($url);
        $stories = [];

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

        return $stories;
    }

    private function fetchMarketauxNews($limit) {
        $url = "{$this->marketauxApiUrl}/news/all?api_token={$this->marketauxApiKey}&limit={$limit}";
        $response = $this->makeApiRequest($url);
        $stories = [];

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

        return $stories;
    }

    private function makeApiRequest($url) {
        $response = file_get_contents($url);
        return json_decode($response, true);
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
        $instructions = "You are Votality, a friendly AI for the Votality app. Make financial data easy and fun. Guidelines:
        1. Respond directly to user input. Only give financial info when asked.
        2. Greet only when the user greets first, if not get directly to the point. Greet friendly and briefly.
        4. Use formal tone.
        5. For financial instruments, provide deep market analysis including: volume profile patterns, institutional order flow, market structure shifts, intermarket correlations, cross-asset relationships, and liquidity profiles.
        6. Present complex data through clear narrative, focusing on non-obvious relationships and hidden market dynamics.
        7. No emojis.
        8. End with relevant follow-up question if appropriate.
        9. No direct advice. Present sophisticated analysis combining technical, fundamental, and structural factors.
        10. Include economic context with emphasis on institutional positioning, dark pool activity, and forward-looking growth metrics.
        11. Give short concise response.
        12. Never give a response with any of these {},[], or with a response that [something not found]!! Never
        13. Use strictly formal language, do not use methaphors and examples
        14. Never give a response that's too long
        15. Give short to medium response!!! Very Important
        
        You can discuss stocks, forex, crypto, and market indexes.";
        if ($marketData) {
            $instructions .= "\n\nLatest market data: " . json_encode($marketData);
        }
        if ($economicData) {
            $instructions .= "\n\nEconomic indicators: " . json_encode($economicData);
        }
        $instructions .= "\n\nFor financial queries, use:
        [SYMBOL/NAME] - [MARKET STRUCTURE ANALYSIS] - [VOLUME/LIQUIDITY PROFILE] - [INSTITUTIONAL POSITIONING]
        For buy/sell analysis, add: [INTERMARKET CORRELATIONS AND RISK DECOMPOSITION]
        Present analysis in three layers: current context with divergences, hidden correlations, and synthesis of technical/fundamental factors.";
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
            $url = "{$this->nasdaqDataLinkApiUrl}datasets/{$code}.json?api_key={$this->nasdaqDataLinkApiKey}&rows=1";
            
            $data = $this->makeApiRequest($url);
            if (isset($data['dataset']['data'][0][1])) {
                $economicData[$name] = $data['dataset']['data'][0][1];
            }
        }

        return $economicData;
    }
}
?>  
