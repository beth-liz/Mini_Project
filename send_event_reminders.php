<?php
require_once 'db_connect.php';

// Function to send email
function sendReminderEmail($userEmail, $userName, $eventTitle, $eventDate, $eventTime) {
    $to = $userEmail;
    $subject = "Reminder: Upcoming Event - " . $eventTitle;
    
    // Create HTML message
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { padding: 20px; }
            .header { color: #00bcd4; }
            .details { margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2 class='header'>Hello $userName,</h2>
            <p>This is a reminder for your upcoming event tomorrow:</p>
            <div class='details'>
                <p><strong>Event:</strong> $eventTitle</p>
                <p><strong>Date:</strong> $eventDate</p>
                <p><strong>Time:</strong> " . ($eventTime ? $eventTime : 'All day') . "</p>
            </div>
            <p>We look forward to seeing you!</p>
            <p>Best regards,<br>ArenaX Team</p>
        </div>
    </body>
    </html>
    ";

    // Headers for HTML email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ArenaX <noreply@arenax.com>\r\n";

    // Send email
    return mail($to, $subject, $message, $headers);
}

try {
    // Get events that:
    // 1. Have reminders enabled
    // 2. Are happening in the next 24 hours
    // 3. Haven't had a reminder sent yet
    $sql = "SELECT 
                uce.id as event_id,
                uce.title as event_title,
                uce.date as event_date,
                uce.time as event_time,
                u.email as user_email,
                u.name as user_name
            FROM user_calendar_events uce
            JOIN users u ON uce.user_id = u.user_id
            LEFT JOIN user_reminders ur ON uce.id = ur.event_id
            WHERE 
                uce.reminder_enabled = 1
                AND uce.date = CURDATE()
                AND (ur.id IS NULL OR ur.is_sent = 0)";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($events as $event) {
        // Send email reminder
        $emailSent = sendReminderEmail(
            $event['user_email'],
            $event['user_name'],
            $event['event_title'],
            $event['event_date'],
            $event['time']
        );

        if ($emailSent) {
            // Update or insert reminder record
            $reminderSql = "INSERT INTO user_reminders (user_id, event_id, reminder_time, is_sent)
                           SELECT user_id, id, NOW(), 1
                           FROM user_calendar_events
                           WHERE id = :event_id
                           ON DUPLICATE KEY UPDATE
                           reminder_time = NOW(),
                           is_sent = 1";
            
            $reminderStmt = $conn->prepare($reminderSql);
            $reminderStmt->execute(['event_id' => $event['event_id']]);
            
            // Log successful reminder
            error_log("Reminder email sent for event ID: " . $event['event_id']);
        } else {
            // Log failed reminder
            error_log("Failed to send reminder email for event ID: " . $event['event_id']);
        }
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
} 