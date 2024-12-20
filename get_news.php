<?php
// API keys
$marketauxApiKey = 'o4VnvcRmaBZeK4eBHPJr8KP3xN8gMBTedxHGkCNz';
$EODHDApiKey = '6715a88a03bf20.08672487';
$finnhubApiKey = 'crnm7tpr01qt44di3q5gcrnm7tpr01qt44di3q60';

// Function to make API requests
function makeApiRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if(curl_errno($ch)){
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    return json_decode($response, true);
}

// Function to fetch news from MarketAux
function getMarketAuxNews($symbol) {
    global $marketauxApiKey;
    $url = "https://api.marketaux.com/v1/news/all?symbols={$symbol}&filter_entities=true&language=en&api_token={$marketauxApiKey}";
    $data = makeApiRequest($url);
    $news = [];
    if(isset($data['data'])) {
        foreach($data['data'] as $item) {
            $news[] = [
                'title' => $item['title'],
                'description' => $item['description'],
                'url' => $item['url'],
                'source' => 'MarketAux',
                'published_at' => $item['published_at']
            ];
        }
    }
    return $news;
}

// Function to fetch news from EODHD
function getEODHDNews($symbol) {
    global $EODHDApiKey;
    $url = "https://eodhistoricaldata.com/api/news?api_token={$EODHDApiKey}&s={$symbol}&offset=0&limit=10";
    $data = makeApiRequest($url);
    $news = [];
    foreach($data as $item) {
        $news[] = [
            'title' => $item['title'],
            'description' => $item['content'],
            'url' => $item['link'],
            'source' => 'EODHD',
            'published_at' => $item['date']
        ];
    }
    return $news;
}

// Function to fetch news from Finnhub
function getFinnhubNews($symbol) {
    global $finnhubApiKey;
    $url = "https://finnhub.io/api/v1/company-news?symbol={$symbol}&from=" . date('Y-m-d', strtotime('-7 days')) . "&to=" . date('Y-m-d') . "&token={$finnhubApiKey}";
    $data = makeApiRequest($url);
    $news = [];
    foreach($data as $item) {
        $news[] = [
            'title' => $item['headline'],
            'description' => $item['summary'],
            'url' => $item['url'],
            'source' => 'Finnhub',
            'published_at' => date('Y-m-d H:i:s', $item['datetime'])
        ];
    }
    return $news;
}

// Function to combine and sort news from all sources
function getCombinedNews($symbol) {
    $allNews = array_merge(
        getMarketAuxNews($symbol),
        getEODHDNews($symbol),
        getFinnhubNews($symbol)
    );

    // Sort news by published date, most recent first
    usort($allNews, function($a, $b) {
        return strtotime($b['published_at']) - strtotime($a['published_at']);
    });

    // Limit to 20 most recent news items
    return array_slice($allNews, 0, 20);
}

// Handle the API request
header('Content-Type: application/json');

if(isset($_GET['symbol'])) {
    $symbol = $_GET['symbol'];
    try {
        $news = getCombinedNews($symbol);
        echo json_encode(['status' => 'success', 'data' => $news]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Symbol parameter is required']);
}
?>