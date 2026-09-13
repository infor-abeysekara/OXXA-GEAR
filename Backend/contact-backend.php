<?php
session_start();
include_once(__DIR__ . '/../include/connection.php');
include_once(__DIR__ . '/../include/functions.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    $subject = isset($_POST['subject']) ? sanitizeInput($_POST['subject']) : '';
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
    $subscribe = isset($_POST['subscribe']) && $_POST['subscribe'] === 'true' ? 1 : 0;

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    // Auto-create table if it doesn't exist
    $createTableQuery = "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        subscribe_newsletter TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $createTableQuery);

    // Insert to DB
    $query = "INSERT INTO contacts (name, email, subject, message, subscribe_newsletter) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssi", $name, $email, $subject, $message, $subscribe);
    
    if ($stmt->execute()) {
        // Send email via mail() function
        $to = "support@oxxagear.lk";
        $email_subject = "New Contact Inquiry: $subject";
        $email_body = "You have received a new message from OXXA GEAR contact form.\n\n".
                      "Name: $name\n".
                      "Email: $email\n".
                      "Subject: $subject\n".
                      "Subscribed to Newsletter: " . ($subscribe ? 'Yes' : 'No') . "\n\n".
                      "Message:\n$message\n";
        $headers = "From: noreply@oxxagear.lk\r\n";
        $headers .= "Reply-To: $email\r\n";

        // We suppress mail() error because it might fail on local WAMP without SMTP setup
        @mail($to, $email_subject, $email_body, $headers);

        echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again later.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
