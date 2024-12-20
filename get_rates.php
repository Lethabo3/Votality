<?php
// OpenExchangeRates API call
$app_id = '269df838ea8c4de68315c97baf07c7b6';
$exchange_api_url = "https://openexchangerates.org/api/latest.json?app_id={$app_id}";

// Finnhub API call
$finnhub_api_key = 'crnm7tpr01qt44di3q5gcrnm7tpr01qt44di3q60';
$finnhub_api_url = "https://finnhub.io/api/v1/economic/calendar?token={$finnhub_api_key}";

// Function to make API calls
function makeApiCall($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => 'Failed to fetch data from API: ' . curl_error($ch)];
    }
    curl_close($ch);
    return json_decode($response, true);
}

// Fetch exchange rates
$exchange_data = makeApiCall($exchange_api_url);
if (isset($exchange_data['error'])) {
    echo json_encode($exchange_data);
    exit;
}

// Fetch economic events
$events_data = makeApiCall($finnhub_api_url);
if (isset($events_data['error'])) {
    echo json_encode($events_data);
    exit;
}

// Process economic events
$upcoming_events = [];
foreach ($events_data as $event) {
    if (isset($event['currency']) && isset($event['dateTime'])) {
        $currency = $event['currency'];
        $event_time = strtotime($event['dateTime']);
        $current_time = time();
        
        // Check if the event is within the next 24 hours
        if ($event_time > $current_time && $event_time <= ($current_time + 86400)) {
            if (!isset($upcoming_events[$currency])) {
                $upcoming_events[$currency] = [];
            }
            $upcoming_events[$currency][] = [
                'name' => $event['event'],
                'time' => $event['dateTime']
            ];
        }
    }
}

// Combine data
$combined_data = [
    'rates' => $exchange_data['rates'],
    'upcoming_events' => $upcoming_events
];

// Send response
header('Content-Type: application/json');
echo json_encode($combined_data);
?>