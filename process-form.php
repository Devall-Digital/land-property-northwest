<?php
// Land Property Northwest - Contact Form Processing
// ==============================================

// Security and Configuration
session_start();
header('Content-Type: application/json');

// Configuration - UPDATE THESE VALUES
$to_email = "quotes@landpropertynorthwest.co.uk"; // Main email to receive inquiries
$cc_email = "info@landpropertynorthwest.co.uk";   // CC email
$from_email = "noreply@landpropertynorthwest.co.uk"; // Sender email
$business_name = "Land Property Northwest";
$business_phone = "07561724095";

// Error handling
$errors = [];
$success = false;

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize and validate input
    $name = filter_var(trim($_POST['name'] ?? ''), FILTER_SANITIZE_STRING);
    $phone = filter_var(trim($_POST['phone'] ?? ''), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $location = filter_var(trim($_POST['location'] ?? ''), FILTER_SANITIZE_STRING);
    $service = filter_var(trim($_POST['service'] ?? ''), FILTER_SANITIZE_STRING);
    $message = filter_var(trim($_POST['message'] ?? ''), FILTER_SANITIZE_STRING);
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }
    
    if (empty($location)) {
        $errors[] = "Property location is required";
    }
    
    if (empty($service)) {
        $errors[] = "Service selection is required";
    }
    
    // Basic spam protection
    if (isset($_POST['honeypot']) && !empty($_POST['honeypot'])) {
        $errors[] = "Spam detected";
    }
    
    // Rate limiting (basic)
    if (!isset($_SESSION['last_submission'])) {
        $_SESSION['last_submission'] = 0;
    }
    
    if (time() - $_SESSION['last_submission'] < 60) {
        $errors[] = "Please wait before submitting another form";
    }
    
    // If no errors, process the form
    if (empty($errors)) {
        
        // Update last submission time
        $_SESSION['last_submission'] = time();
        
        // Prepare email content
        $subject = "New Quote Request from {$name} - {$location}";
        
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .details { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
                .label { font-weight: bold; color: #2563eb; }
                .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; }
                .urgent { background: #f59e0b; color: white; padding: 10px; text-align: center; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🏠 New Quote Request - {$business_name}</h2>
            </div>
            
            <div class='urgent'>
                ⚡ URGENT: New lead requiring immediate response within 2 hours!
            </div>
            
            <div class='content'>
                <div class='details'>
                    <p><span class='label'>Customer Name:</span> {$name}</p>
                    <p><span class='label'>Phone Number:</span> <a href='tel:{$phone}'>{$phone}</a></p>
                    <p><span class='label'>Email:</span> " . ($email ? "<a href='mailto:{$email}'>{$email}</a>" : "Not provided") . "</p>
                    <p><span class='label'>Property Location:</span> {$location}</p>
                    <p><span class='label'>Service Required:</span> {$service}</p>
                    <p><span class='label'>Submission Time:</span> " . date('d/m/Y H:i:s') . "</p>
                </div>
                
                " . ($message ? "<div class='details'><p><span class='label'>Additional Details:</span></p><p>{$message}</p></div>" : "") . "
                
                <div class='details'>
                    <h3>🎯 Quick Action Items:</h3>
                    <ul>
                        <li>📞 Call {$name} immediately on <strong>{$phone}</strong></li>
                        <li>📧 Send follow-up email if provided</li>
                        <li>📅 Schedule site visit for quote</li>
                        <li>💰 Target: Convert to £3,000+ commission</li>
                    </ul>
                </div>
                
                <div class='details'>
                    <h3>📊 Lead Priority Assessment:</h3>
                    <p><strong>Location:</strong> {$location} " . (stripos($location, 'saddleworth') !== false ? "(🏆 HIGH VALUE AREA)" : "") . "</p>
                    <p><strong>Service:</strong> {$service}</p>
                    <p><strong>Contact Quality:</strong> " . ($email ? "Email + Phone provided" : "Phone only") . "</p>
                </div>
            </div>
            
            <div class='footer'>
                <p>{$business_name} | {$business_phone}</p>
                <p>Automated lead notification system</p>
            </div>
        </body>
        </html>
        ";
        
        // Email headers
        $headers = [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=UTF-8",
            "From: {$business_name} Website <{$from_email}>",
            "Reply-To: " . ($email ?: $from_email),
            "X-Mailer: PHP/" . phpversion(),
            "X-Priority: 1",
            "Importance: High"
        ];
        
        if ($cc_email) {
            $headers[] = "Cc: {$cc_email}";
        }
        
        // Send email
        $mail_sent = mail($to_email, $subject, $email_body, implode("\r\n", $headers));
        
        if ($mail_sent) {
            $success = true;
            
            // Optional: Send auto-response to customer
            if ($email) {
                $customer_subject = "Thank you for your quote request - {$business_name}";
                $customer_body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; }
                    </style>
                </head>
                <body>
                    <div class='header'>
                        <h2>🏠 Thank You - {$business_name}</h2>
                    </div>
                    <div class='content'>
                        <p>Dear {$name},</p>
                        
                        <p>Thank you for your interest in our windows and doors services. We have received your quote request for <strong>{$service}</strong> in <strong>{$location}</strong>.</p>
                        
                        <p><strong>What happens next:</strong></p>
                        <ul>
                            <li>📞 We will call you within <strong>2 hours</strong> on {$phone}</li>
                            <li>📅 Schedule a convenient time for your free site survey</li>
                            <li>📋 Provide you with a detailed, no-obligation quote</li>
                        </ul>
                        
                        <p><strong>Immediate questions?</strong> Call us now on <a href='tel:{$business_phone}'>{$business_phone}</a></p>
                        
                        <p>Best regards,<br>
                        The {$business_name} Team<br>
                        Serving Oldham, Saddleworth & Greater Manchester</p>
                    </div>
                </body>
                </html>
                ";
                
                $customer_headers = [
                    "MIME-Version: 1.0",
                    "Content-type: text/html; charset=UTF-8",
                    "From: {$business_name} <{$from_email}>",
                    "X-Mailer: PHP/" . phpversion()
                ];
                
                mail($email, $customer_subject, $customer_body, implode("\r\n", $customer_headers));
            }
            
        } else {
            $errors[] = "Failed to send email. Please try again or call us directly.";
        }
    }
}

// Return JSON response
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! We will contact you within 2 hours to discuss your free quote.',
        'phone' => $business_phone
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'errors' => $errors,
        'phone' => $business_phone
    ]);
}
?>