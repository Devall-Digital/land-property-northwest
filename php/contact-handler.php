<?php
/**
 * Northwest Property & Land Sales - Contact Form Handler
 * Professional contact form processing with email notifications
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS headers (adjust domain as needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Configuration
$config = [
    'smtp' => [
        'host' => 'localhost', // Update with your SMTP host
        'port' => 587,
        'username' => 'noreply@landpropertynorthwest.co.uk',
        'password' => '', // Set your SMTP password
        'encryption' => 'tls'
    ],
    'emails' => [
        'to' => 'info@landpropertynorthwest.co.uk',
        'from' => 'noreply@landpropertynorthwest.co.uk',
        'reply_to' => 'info@landpropertynorthwest.co.uk'
    ],
    'company' => [
        'name' => 'Northwest Property & Land Sales',
        'phone' => '+44 7561 724 095',
        'website' => 'https://landpropertynorthwest.co.uk'
    ]
];

try {
    // Get and sanitize input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $required_fields = ['name', 'email', 'inquiry_type'];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Validate email format
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    
    // Check for spam (simple honeypot check)
    if (!empty($input['website'])) {
        $errors[] = 'Spam detected';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'details' => $errors]);
        exit;
    }
    
    // Sanitize input data
    $data = [
        'name' => htmlspecialchars(trim($input['name']), ENT_QUOTES, 'UTF-8'),
        'email' => filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL),
        'phone' => htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'inquiry_type' => htmlspecialchars(trim($input['inquiry_type']), ENT_QUOTES, 'UTF-8'),
        'message' => htmlspecialchars(trim($input['message'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'newsletter' => isset($input['newsletter']) ? 'Yes' : 'No',
        'submitted_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
    
    // Map inquiry types to readable format
    $inquiry_types = [
        'property_purchase' => 'Property Purchase',
        'land_development' => 'Land Development',
        'property_sale' => 'Sell My Property',
        'investment_advice' => 'Investment Advice',
        'general_inquiry' => 'General Inquiry'
    ];
    
    $inquiry_type_readable = $inquiry_types[$data['inquiry_type']] ?? $data['inquiry_type'];
    
    // Prepare email content
    $subject = "New {$inquiry_type_readable} Inquiry - {$data['name']}";
    
    // HTML email template
    $html_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>New Property Inquiry</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .header { background: #000; color: #fff; padding: 20px; text-align: center; }
            .content { padding: 30px; }
            .field { margin-bottom: 20px; }
            .label { font-weight: bold; color: #000; }
            .value { margin-top: 5px; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 14px; color: #666; }
            .highlight { background: #f0f8ff; padding: 15px; border-left: 4px solid #000; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Northwest Property & Land Sales</h1>
                <h2>New Customer Inquiry</h2>
            </div>
            
            <div class='content'>
                <div class='highlight'>
                    <strong>Inquiry Type:</strong> {$inquiry_type_readable}
                </div>
                
                <div class='field'>
                    <div class='label'>Customer Name:</div>
                    <div class='value'>{$data['name']}</div>
                </div>
                
                <div class='field'>
                    <div class='label'>Email Address:</div>
                    <div class='value'><a href='mailto:{$data['email']}'>{$data['email']}</a></div>
                </div>
                
                " . (!empty($data['phone']) ? "
                <div class='field'>
                    <div class='label'>Phone Number:</div>
                    <div class='value'><a href='tel:{$data['phone']}'>{$data['phone']}</a></div>
                </div>
                " : "") . "
                
                " . (!empty($data['message']) ? "
                <div class='field'>
                    <div class='label'>Message:</div>
                    <div class='value'>" . nl2br($data['message']) . "</div>
                </div>
                " : "") . "
                
                <div class='field'>
                    <div class='label'>Newsletter Subscription:</div>
                    <div class='value'>{$data['newsletter']}</div>
                </div>
                
                <div class='field'>
                    <div class='label'>Submitted:</div>
                    <div class='value'>{$data['submitted_at']}</div>
                </div>
            </div>
            
            <div class='footer'>
                <p><strong>Quick Response Required:</strong> Customer expects response within 2 hours</p>
                <p>{$config['company']['name']} | {$config['company']['phone']} | {$config['company']['website']}</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Plain text version
    $text_body = "
New {$inquiry_type_readable} Inquiry - Northwest Property & Land Sales

Customer Details:
Name: {$data['name']}
Email: {$data['email']}
" . (!empty($data['phone']) ? "Phone: {$data['phone']}\n" : "") . "

Inquiry Type: {$inquiry_type_readable}

" . (!empty($data['message']) ? "Message:\n{$data['message']}\n\n" : "") . "

Newsletter Subscription: {$data['newsletter']}
Submitted: {$data['submitted_at']}

---
Quick Response Required: Customer expects response within 2 hours
{$config['company']['name']} | {$config['company']['phone']} | {$config['company']['website']}
    ";
    
    // Email headers
    $headers = [
        'From' => "{$config['company']['name']} <{$config['emails']['from']}>",
        'Reply-To' => $data['email'],
        'Return-Path' => $config['emails']['from'],
        'X-Mailer' => 'PHP/' . phpversion(),
        'X-Priority' => '2', // High priority for business inquiries
        'MIME-Version' => '1.0'
    ];
    
    // Send HTML email
    $boundary = uniqid('np');
    $headers['Content-Type'] = "multipart/alternative; boundary=\"{$boundary}\"";
    
    $email_body = "--{$boundary}\r\n";
    $email_body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $email_body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $email_body .= $text_body . "\r\n\r\n";
    
    $email_body .= "--{$boundary}\r\n";
    $email_body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $email_body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $email_body .= $html_body . "\r\n\r\n";
    $email_body .= "--{$boundary}--";
    
    $header_string = '';
    foreach ($headers as $key => $value) {
        $header_string .= "{$key}: {$value}\r\n";
    }
    
    // Send email
    $mail_sent = mail($config['emails']['to'], $subject, $email_body, $header_string);
    
    if (!$mail_sent) {
        throw new Exception('Failed to send email notification');
    }
    
    // Log the inquiry (you might want to store in database)
    $log_entry = date('Y-m-d H:i:s') . " - New inquiry from {$data['name']} ({$data['email']}) - {$inquiry_type_readable}\n";
    file_put_contents('contact_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
    
    // Send auto-response to customer
    $customer_subject = "Thank you for your property inquiry - {$config['company']['name']}";
    
    $customer_html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Thank You</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; }
            .header { background: #000; color: #fff; padding: 20px; text-align: center; }
            .content { padding: 30px; }
            .highlight { background: #f0f8ff; padding: 15px; border-left: 4px solid #000; margin: 20px 0; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 14px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Northwest Property & Land Sales</h1>
                <h2>Thank You for Your Inquiry</h2>
            </div>
            
            <div class='content'>
                <p>Dear {$data['name']},</p>
                
                <p>Thank you for your interest in our {$inquiry_type_readable} services. We have received your inquiry and will respond within 2 hours during business hours.</p>
                
                <div class='highlight'>
                    <strong>Your Inquiry Details:</strong><br>
                    Type: {$inquiry_type_readable}<br>
                    Submitted: {$data['submitted_at']}
                </div>
                
                <p><strong>What happens next?</strong></p>
                <ul>
                    <li>Our property expert will review your requirements</li>
                    <li>We'll contact you within 2 hours with relevant information</li>
                    <li>We'll arrange a convenient time to discuss your needs</li>
                </ul>
                
                <p><strong>Urgent inquiries:</strong> Call us directly at {$config['company']['phone']}</p>
                
                <p>Best regards,<br>
                The Northwest Property & Land Sales Team</p>
            </div>
            
            <div class='footer'>
                <p>{$config['company']['name']} | {$config['company']['phone']} | {$config['company']['website']}</p>
                <p>Professional property and land sales in Northwest England</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $customer_headers = [
        'From' => "{$config['company']['name']} <{$config['emails']['from']}>",
        'Reply-To' => $config['emails']['reply_to'],
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    $customer_header_string = '';
    foreach ($customer_headers as $key => $value) {
        $customer_header_string .= "{$key}: {$value}\r\n";
    }
    
    // Send auto-response
    mail($data['email'], $customer_subject, $customer_html, $customer_header_string);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your inquiry! We\'ll get back to you within 2 hours.',
        'data' => [
            'inquiry_type' => $inquiry_type_readable,
            'submitted_at' => $data['submitted_at']
        ]
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Contact form error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'An error occurred while processing your request. Please try again or call us directly.',
        'details' => $e->getMessage()
    ]);
}
?>