<?php
// cancel_subscription.php
<?php
require_once 'database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get subscription info from database
    $stmt = $conn->prepare("SELECT lemon_squeezy_subscription_id FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !$user['lemon_squeezy_subscription_id']) {
        throw new Exception('No active subscription found');
    }

    // Cancel subscription in Lemon Squeezy
    $subscriptionId = $user['lemon_squeezy_subscription_id'];
    $response = cancelLemonSqueezySubscription($subscriptionId);

    if ($response['success']) {
        // Update user's subscription status in database
        $stmt = $conn->prepare("UPDATE users SET subscription_plan = 'free', subscription_status = 'cancelled' WHERE user_id = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } else {
        throw new Exception($response['error']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function cancelLemonSqueezySubscription($subscriptionId) {
    $apiKey = 'your_lemon_squeezy_api_key';
    $url = "https://api.lemonsqueezy.com/v1/subscriptions/{$subscriptionId}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$apiKey}",
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['success' => $httpCode === 200];
}

// get_invoices.php
<?php
require_once 'database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT 
            invoice_id,
            amount,
            DATE_FORMAT(created_at, '%d %b %Y') as date,
            status
        FROM invoices 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
    
    echo json_encode(['success' => true, 'invoices' => $invoices]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// invoice.php
<?php
require_once 'database.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.html');
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $invoiceId = $_GET['id'];
    
    $stmt = $conn->prepare("
        SELECT * FROM invoices 
        WHERE invoice_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ss", $invoiceId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    
    // Generate PDF invoice
    require_once('tcpdf/tcpdf.php');
    $pdf = new TCPDF();
    // Add invoice content to PDF
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    
    // Add invoice details
    $pdf->Cell(0, 10, 'Invoice #' . $invoice['invoice_id'], 0, 1);
    $pdf->Cell(0, 10, 'Date: ' . $invoice['created_at'], 0, 1);
    $pdf->Cell(0, 10, 'Amount: $' . number_format($invoice['amount'], 2), 0, 1);
    
    $pdf->Output('Invoice_' . $invoice['invoice_id'] . '.pdf', 'D');
} catch (Exception $e) {
    echo "Error generating invoice: " . $e->getMessage();
}