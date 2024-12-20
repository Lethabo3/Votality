<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }

    $plan = $data['plan'] ?? null;

    // Store configuration with buy link UUIDs
    $storeData = [
        'premium' => [
            'buy_link' => '6597f511-3631-4eb1-a28a-c9aa4aa21665',
            'price' => 349.99
        ],
        'teams' => [
            'buy_link' => '', // Add your teams buy link when ready
            'price' => 499.99
        ]
    ];

    if (!isset($storeData[$plan])) {
        throw new Exception('Invalid plan selected');
    }

    $planData = $storeData[$plan];

    // Construct the checkout URL with the buy link
    $checkoutUrl = sprintf(
        'https://votality.lemonsqueezy.com/buy/%s',
        $planData['buy_link']
    );

    // Add custom parameters
    $params = [];
    
    if (isset($data['userId'])) {
        $params['checkout[custom][user_id]'] = $data['userId'];
    }

    // Add success and cancel URLs
    $params['checkout[success_url]'] = 'https://votalityai.com/subscription/success';
    $params['checkout[cancel_url]'] = 'https://votalityai.com/pricing';

    // Add parameters to URL
    if (!empty($params)) {
        $checkoutUrl .= '?' . http_build_query($params);
    }

    echo json_encode([
        'success' => true,
        'checkoutUrl' => $checkoutUrl
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}