<?php
require_once 'db_connect.php';

// Fetch reminders that need to be sent
$reminderSQL = "SELECT ur.*, uce.title, uce.date, uce.time, u.email, u.name
                FROM user_reminders ur
                JOIN user_calendar_events uce ON ur.event_id = uce.id
                JOIN users u ON ur.user_id = u.user_id
                WHERE ur.is_sent = 0 
                AND ur.reminder_time <= NOW()
                AND uce.reminder_enabled = 1";

$stmt = $conn->prepare($reminderSQL);
$stmt->execute();
$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reminders as $reminder) {
    // Prepare email content
    $to = $reminder['email'];
    $subject = "Reminder: Upcoming Booking - " . $reminder['title'];
    
    $message = "Dear " . $reminder['name'] . ",\n\n";
    $message .= "This is a reminder for your upcoming booking:\n\n";
    $message .= "Event: " . $reminder['title'] . "\n";
    $message .= "Date: " . $reminder['date'] . "\n";
    $message .= "Time: " . ($reminder['time'] ?? 'All day') . "\n\n";
    $message .= "We look forward to seeing you!\n\n";
    $message .= "Best regards,\nArenaX Team";
    
    $headers = "From: arenax@gmail.com";

    // Send email
    if (mail($to, $subject, $message, $headers)) {
        // Update reminder as sent
        $updateSQL = "UPDATE user_reminders 
                     SET is_sent = 1 
                     WHERE id = :reminder_id";
        $updateStmt = $conn->prepare($updateSQL);
        $updateStmt->execute(['reminder_id' => $reminder['id']]);
    }
}
?> 