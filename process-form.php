<?php
/**
 * Enhanced Form Processing Script for Land Property Northwest Website
 * Handles form submissions, validates data, saves to CSV, and sends email notifications
 */

// Set timezone
date_default_timezone_set('Europe/London');

// CORS headers (if needed for AJAX requests)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON data or form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate required fields
$required_fields = ['name', 'phone', 'email', 'location', 'service'];
$errors = [];

foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $errors[] = "Missing required field: $field";
    }
}

// Validate email format
if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
}

// Validate phone number (basic UK phone validation)
if (!empty($input['phone'])) {
    $phone = preg_replace('/[^0-9+]/', '', $input['phone']);
    if (strlen($phone) < 10) {
        $errors[] = 'Invalid phone number';
    }
}

// Return errors if any
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors),
        'errors' => $errors
    ]);
    exit;
}

// Sanitize input
$lead_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'name' => htmlspecialchars(strip_tags(trim($input['name'])), ENT_QUOTES, 'UTF-8'),
    'phone' => htmlspecialchars(strip_tags(trim($input['phone'])), ENT_QUOTES, 'UTF-8'),
    'email' => filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL),
    'location' => !empty($input['location']) ? htmlspecialchars(strip_tags($input['location']), ENT_QUOTES, 'UTF-8') : 'Not specified',
    'service' => !empty($input['service']) ? htmlspecialchars(strip_tags($input['service']), ENT_QUOTES, 'UTF-8') : 'Not specified',
    'property_type' => !empty($input['property-type']) ? htmlspecialchars(strip_tags($input['property-type']), ENT_QUOTES, 'UTF-8') : 'Not specified',
    'message' => !empty($input['message']) ? htmlspecialchars(strip_tags($input['message']), ENT_QUOTES, 'UTF-8') : 'No additional details',
    'source' => 'land-property-northwest',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
];

// Save to CSV file
$csv_file = 'leads/property-leads.csv';
$csv_dir = dirname($csv_file);

// Create directory if it doesn't exist
if (!is_dir($csv_dir)) {
    if (!mkdir($csv_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not create leads directory']);
        exit;
    }
}

// Check if file exists to write headers
$write_headers = !file_exists($csv_file);

// Open file for appending
$file = fopen($csv_file, 'a');
if (!$file) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save lead']);
    exit;
}

// Write headers if new file
if ($write_headers) {
    fputcsv($file, array_keys($lead_data));
}

// Write lead data
fputcsv($file, array_values($lead_data));
fclose($file);

// Send email notification
$send_email = true; // Set to false to disable email notifications
$your_email = 'quotes@landpropertynorthwest.co.uk'; // Your email address

if ($send_email) {
    // Email notification
    $subject = 'New Property Quote Request - ' . $lead_data['name'];
    $message = "New quote request received from landpropertynorthwest.co.uk:\n\n";
    $message .= "Name: " . $lead_data['name'] . "\n";
    $message .= "Phone: " . $lead_data['phone'] . "\n";
    $message .= "Email: " . $lead_data['email'] . "\n";
    $message .= "Location: " . $lead_data['location'] . "\n";
    $message .= "Service Required: " . $lead_data['service'] . "\n";
    $message .= "Property Type: " . $lead_data['property_type'] . "\n";
    $message .= "Additional Details: " . $lead_data['message'] . "\n";
    $message .= "Timestamp: " . $lead_data['timestamp'] . "\n";
    $message .= "IP Address: " . $lead_data['ip_address'] . "\n";
    
    $headers = 'From: noreply@' . $_SERVER['HTTP_HOST'] . "\r\n" .
               'Reply-To: ' . $lead_data['email'] . "\r\n" .
               'X-Mailer: PHP/' . phpversion() . "\r\n" .
               'Content-Type: text/plain; charset=UTF-8';
    
    // Attempt to send email (don't fail if email fails)
    @mail($your_email, $subject, $message, $headers);
}

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Thank you! We will contact you within 24 hours.',
    'lead_id' => uniqid('LEAD-')
]);
?>

