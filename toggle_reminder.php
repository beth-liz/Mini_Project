<?php
session_start();
require_once 'db_connect.php';

// Check if request is POST and contains JSON data
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($input['id']) && isset($input['reminder_enabled'])) {
    try {
        // Begin transaction
        $conn->beginTransaction();

        // Update the reminder status in user_calendar_events
        $updateSQL = "UPDATE user_calendar_events 
                     SET reminder_enabled = :reminder_enabled 
                     WHERE id = :event_id";
        $stmt = $conn->prepare($updateSQL);
        $result = $stmt->execute([
            'reminder_enabled' => $input['reminder_enabled'],
            'event_id' => $input['id']
        ]);

        if (!$result) {
            throw new Exception('Failed to update reminder status');
        }

        // If enabling reminder, create a reminder record
        if ($input['reminder_enabled'] == 1) {
            // Get event details
            $eventSQL = "SELECT user_id, date, time FROM user_calendar_events WHERE id = :event_id";
            $eventStmt = $conn->prepare($eventSQL);
            $eventStmt->execute(['event_id' => $input['id']]);
            $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                throw new Exception('Event not found');
            }

            // Calculate reminder time (1 day before the event)
            $eventDateTime = $event['date'] . ' ' . ($event['time'] ?? '00:00:00');
            $reminderTime = date('Y-m-d H:i:s', strtotime($eventDateTime . ' -1 day'));

            // Delete any existing reminders for this event
            $deleteSQL = "DELETE FROM user_reminders WHERE event_id = :event_id";
            $deleteStmt = $conn->prepare($deleteSQL);
            $deleteStmt->execute(['event_id' => $input['id']]);

            // Create new reminder
            $reminderSQL = "INSERT INTO user_reminders (user_id, event_id, reminder_time, is_sent)
                           VALUES (:user_id, :event_id, :reminder_time, 0)";
            $reminderStmt = $conn->prepare($reminderSQL);
            $reminderStmt->execute([
                'user_id' => $event['user_id'],
                'event_id' => $input['id'],
                'reminder_time' => $reminderTime
            ]);
        } else {
            // If disabling reminder, remove any existing reminders
            $deleteSQL = "DELETE FROM user_reminders WHERE event_id = :event_id";
            $deleteStmt = $conn->prepare($deleteSQL);
            $deleteStmt->execute(['event_id' => $input['id']]);
        }

        // Commit transaction
        $conn->commit();

        // Verify the update
        $verifySQL = "SELECT reminder_enabled FROM user_calendar_events WHERE id = :event_id";
        $verifyStmt = $conn->prepare($verifySQL);
        $verifyStmt->execute(['event_id' => $input['id']]);
        $currentState = $verifyStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'reminder_enabled' => $currentState,
            'message' => $currentState == 1 ? 'Reminder enabled' : 'Reminder disabled'
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}
?> 