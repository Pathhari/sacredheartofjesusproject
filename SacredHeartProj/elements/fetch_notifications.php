<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION["userid"])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userID = $_SESSION["userid"];

include 'db.php';

// Initialize notifications array
$notifications = [];

// Fetch unread general notifications
$sqlNotifications = "SELECT NotificationID, UserID, NotificationText, CreatedAt, IsRead FROM Notifications WHERE UserID = ? AND IsRead = 0";
$stmtNotifications = $conn->prepare($sqlNotifications);
if (!$stmtNotifications) {
    echo json_encode(['success' => false, 'message' => 'Database error: Unable to prepare notifications statement']);
    exit;
}
$stmtNotifications->bind_param("i", $userID);
$stmtNotifications->execute();
$resultNotifications = $stmtNotifications->get_result();

while ($row = $resultNotifications->fetch_assoc()) {
    $notifications[] = [
        'NotificationID' => $row['NotificationID'],
        'UserID' => $row['UserID'],
        'NotificationText' => $row['NotificationText'],
        'CreatedAt' => $row['CreatedAt'],
        'IsRead' => $row['IsRead'],
        'Type' => 'General' // You can use this field to distinguish notification types
    ];
    
    // Mark notification as read in the database
    $markReadSQL = "UPDATE Notifications SET IsRead = 1 WHERE NotificationID = ?";
    $stmtMarkRead = $conn->prepare($markReadSQL);
    $stmtMarkRead->bind_param("i", $row['NotificationID']);
    $stmtMarkRead->execute();
    $stmtMarkRead->close();
}
$stmtNotifications->close();

// Fetch unread feedback notifications
$sqlFeedback = "SELECT FeedbackID, UserID, FeedbackText, SubmittedAt, Status FROM Feedback WHERE UserID = ? AND Status = 'Pending'";
$stmtFeedback = $conn->prepare($sqlFeedback);
if (!$stmtFeedback) {
    echo json_encode(['success' => false, 'message' => 'Database error: Unable to prepare feedback statement']);
    exit;
}
$stmtFeedback->bind_param("i", $userID);
$stmtFeedback->execute();
$resultFeedback = $stmtFeedback->get_result();

while ($row = $resultFeedback->fetch_assoc()) {
    $notifications[] = [
        'NotificationID' => 'feedback_' . $row['FeedbackID'], // Prefix to ensure unique IDs
        'UserID' => $row['UserID'],
        'NotificationText' => 'You have new feedback: ' . substr($row['FeedbackText'], 0, 100) . '...', // Customize as needed
        'CreatedAt' => $row['SubmittedAt'],
        'IsRead' => false, // Feedback notifications are treated as unread
        'Type' => 'Feedback'
    ];
    
    // Optionally, mark feedback as processed
    $markProcessedSQL = "UPDATE Feedback SET Status = 'Read' WHERE FeedbackID = ?";
    $stmtMarkProcessed = $conn->prepare($markProcessedSQL);
    $stmtMarkProcessed->bind_param("i", $row['FeedbackID']);
    $stmtMarkProcessed->execute();
    $stmtMarkProcessed->close();
}
$stmtFeedback->close();

// Optionally, sort notifications by CreatedAt descending
usort($notifications, function($a, $b) {
    return strtotime($b['CreatedAt']) - strtotime($a['CreatedAt']);
});

// Return the combined notifications
echo json_encode(['success' => true, 'notifications' => $notifications]);

// Close the database connection
$conn->close();
?>
