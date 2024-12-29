<?php
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: log-in.php");
    exit;
}

// Fetch user details from session, with default values if they are not set
$firstname = isset($_SESSION["firstname"]) ? $_SESSION["firstname"] : "User";
$lastname  = isset($_SESSION["lastname"])  ? $_SESSION["lastname"]  : "";
$email     = isset($_SESSION["email"])     ? $_SESSION["email"]     : "Not Available";

// Include database connection file
include 'db.php';

// Fetch events and their respective sacrament request statuses from UpcomingEvents
$sql_events = "
    SELECT
        e.*,
        p.PriestName,
        sr.Status AS SacramentStatus
    FROM UpcomingEvents e
    LEFT JOIN Priests p ON e.PriestID = p.PriestID
    LEFT JOIN SacramentRequests sr ON e.EventID = sr.EventID
    WHERE EventDate >= CURDATE()
    ORDER BY EventDate ASC
";
$events = [];

if ($stmt_events = $conn->prepare($sql_events)) {
    if ($stmt_events->execute()) {
        $result_events = $stmt_events->get_result();
        while ($row = $result_events->fetch_assoc()) {
            // Determine event display status based on SacramentStatus
            if ($row['SacramentStatus'] === 'Pending') {
                $row['DisplayStatus'] = 'Pre-Booked';
            } elseif ($row['SacramentStatus'] === 'Approved') {
                $row['DisplayStatus'] = 'Booked';
            } elseif ($row['SacramentStatus'] === 'Rejected') {
                $row['DisplayStatus'] = 'Available';
            } else {
                $row['DisplayStatus'] = 'Available'; // Default to 'Available' if no status
            }
            $events[] = $row;
        }
    }
    $stmt_events->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Sacred Heart Parish</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <style>
        body {
            font-family: 'Gotham', sans-serif;
            background-color: #fffaf0;
        }
        /* Header */
        .header {
            background-color: #E85C0D;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            color: white;
            flex-wrap: wrap;
        }
        .header .logo-container {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }
        .header img {
            height: 50px;
            width: auto;
            margin-right: 15px;
        }
        .header h1 {
            font-size: 1.75rem;
            margin: 0;
            white-space: nowrap;
            flex-shrink: 1;
        }
        .header-icons {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        /* Sidebar */
        .sidebar {
            background-color: #2d3748;
            width: 220px;
            min-height: 100vh;
            padding-top: 20px;
            color: white;
            transition: width 0.3s ease;
            position: relative;
            flex-shrink: 0;
        }
        .sidebar.collapsed {
            width: 60px;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            transition: background-color 0.3s;
        }
        .sidebar a i {
            margin-right: 12px;
            font-size: 1.2rem;
        }
        .sidebar.collapsed a span {
            display: none;
        }
        .sidebar.collapsed i {
            margin-right: 0;
        }
        .sidebar a:hover {
            background-color: #4a5568;
        }
        .sidebar a.active {
            background-color: #F6AD55;
            color: white;
        }
        .sidebar a.active i {
            color: white;
        }
        .flex {
            display: flex;
            min-height: 100vh;
        }
        /* Sidebar Toggle Button */
        .toggle-btn {
            position: absolute;
            top: 50%;
            right: 0;
            background-color: #2d3748;
            color: white;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 1000;
            transform: translateY(-50%);
            transition: right 0.3s ease;
        }
        .sidebar.collapsed .toggle-btn {
            right: -15px;
        }
        /* Profile Dropdown */
        .profile-section {
            position: relative;
            display: inline-block;
        }
        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 8px;
            overflow: hidden;
        }
        .profile-dropdown a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .profile-dropdown a:hover {
            background-color: #f1f1f1;
        }
        .profile-section:hover .profile-dropdown {
            display: block;
        }
        .profile-dropdown a[href="log-out.php"]:hover {
            color: red;
        }
        /* Notification styles */
        .notifications-window {
            position: fixed;
            right: 20px;
            top: 60px;
            width: 320px;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            display: none;
            z-index: 1000;
        }
        .notifications-header {
            background-color: #ff9800;
            color: #fff;
            padding: 15px;
            border-radius: 12px 12px 0 0;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .notifications-content {
            max-height: 350px;
            overflow-y: auto;
        }
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item:hover {
            background-color: #ffe0b2;
        }
        .notifications-content {
            scroll-behavior: smooth;
        }
        /* Calendar Styling */
        .calendar-box {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .fc {
            font-family: 'Gotham', sans-serif;
        }
        .fc-toolbar {
            margin-bottom: 20px !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .fc-toolbar-title {
            font-size: 1.5rem;
            color: #E85C0D;
        }
        .fc-button {
            background-color: #E85C0D;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .fc-button:hover {
            background-color: #D35400;
        }
        .fc-button-primary {
            background-color: #2d3748;
        }
        .fc-button-primary:hover {
            background-color: #1a202c;
        }
        .fc-daygrid-event {
            background-color: #F6AD55;
            border: none;
            border-radius: 6px;
            padding: 5px 10px;
            color: #2d3748;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .fc-daygrid-event:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        /* Event List Section */
        .event-list {
            background-color: #FFEDD5;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 400px;
            overflow-y: auto;
        }
        .event-list h4 {
            font-size: 1.25rem;
            margin-bottom: 12px;
        }
        .event-item {
            display: flex;
            flex-direction: column;
            padding: 15px;
            border-radius: 8px;
            background: #ffffff;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        .event-item:hover {
            transform: scale(1.02);
        }
        .event-date {
            font-size: 1.125rem;
            color: #E85C0D;
            font-weight: bold;
        }
        .event-title {
            font-size: 1rem;
            color: #2d3748;
            margin-top: 8px;
        }
        .event-details {
            font-size: 0.875rem;
            color: #4a5568;
            margin-top: 4px;
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: center;
                padding: 10px 20px;
            }
            .header h1 {
                font-size: 1.25rem;
                white-space: normal;
            }
            .header-icons {
                gap: 10px;
                justify-content: space-between;
                width: 100%;
                margin-top: 10px;
            }
            .header-icons span {
                font-size: 1rem;
            }
            .header-icons i {
                font-size: 1.2rem;
            }
            .calendar-container {
                flex-direction: column;
            }
            .calendar-box,
            .event-list {
                margin-bottom: 20px;
                width: 100%;
            }
            .fc-toolbar {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
<header class="header">
    <div class="logo-container">
        <img src="imgs/mainlogo.png" alt="Parish Logo" class="h-10 w-auto mr-3">
        <h1 class="text-2xl font-bold">Sacred Heart of Jesus Parish</h1>
    </div>
    <div class="header-icons">
        <i class="fas fa-bell fa-2x" onclick="toggleNotifications()" title="Notifications" style="position: relative;">
            <span id="notificationCount" style="display:none; position:absolute; top:-5px; right:-10px; background:red; color:white; border-radius:50%; padding:2px 6px; font-size:12px;">0</span>
        </i>
        <span class="text-lg font-medium">Welcome, <?php echo htmlspecialchars($firstname); ?>!</span>
        <div class="profile-section">
            <i class="fas fa-user-circle fa-2x"></i>
            <div class="profile-dropdown">
                <a href="user_dashboardeditprofile.php">Edit Profile</a>
                <a href="log-out.php">Log Out</a>
            </div>
        </div>
    </div>
</header>
<div class="flex">
    <nav class="sidebar collapsed" id="sidebar">
        <a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="sacraments.php"><i class="fas fa-cross"></i> <span>Sacraments</span></a>
        <a href="calenderuser.php" class="active"><i class="fas fa-calendar-alt"></i> <span>Calendar</span></a>
        <a href="announcementsuser.php"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a>
        <div class="toggle-btn" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-chevron-right"></i>
        </div>
    </nav>
    <div class="p-6 max-w-7xl mx-auto">
        <h2 class="text-3xl font-semibold mb-6 text-gray-800">Calendar Events</h2>
        <p class="mb-6 text-lg leading-relaxed text-gray-800 bg-gray-100 p-4 rounded-lg shadow-sm border border-gray-300">
            This section displays the <strong class="text-orange-600">Upcoming events at Sacred Heart Parish.</strong>
            You can view the event details for any day in the calendar, and the list on the right shows all upcoming
            scheduled events. Stay informed about upcoming sacraments, blessings, and other parish activities!
        </p>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="calendar-box">
                <div id="calendar"></div>
            </div>
            <div class="event-list">
                <h4 class="text-xl font-bold mb-4">Upcoming Events</h4>
                <?php
                $grouped_events = [];
                foreach ($events as $event) {
                    $grouped_events[$event['SacramentType']][] = $event;
                }
                if (!empty($grouped_events)):
                    foreach ($grouped_events as $sacrament_type => $sacrament_events): ?>
                        <h5 class="text-lg font-semibold mt-4 mb-2">
                            <?php echo htmlspecialchars($sacrament_type); ?>
                        </h5>
                        <?php foreach ($sacrament_events as $event): ?>
                            <div class="event-item">
                                <div class="event-date">
                                    <?php echo date('F j, Y', strtotime($event['EventDate'])); ?>
                                </div>
                                <div class="event-title">
                                    <?php echo htmlspecialchars($event['SacramentType']); ?> - <?php echo htmlspecialchars($event['PriestName']); ?>
                                </div>
                                <div class="event-details">
                                    <?php echo date('h:i A', strtotime($event['StartTime'])); ?> - <?php echo date('h:i A', strtotime($event['EndTime'])); ?>
                                </div>
                                <div class="event-details">
                                    Status:
                                    <span style="
                                        color: <?php
                                            echo ($event['DisplayStatus'] === 'Available')
                                                ? 'blue'
                                                : (($event['DisplayStatus'] === 'Pre-Booked') ? 'orange' : 'green');
                                        ?>;
                                        font-weight: bold;">
                                        <?php echo htmlspecialchars($event['DisplayStatus']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach;
                else: ?>
                    <p>No upcoming events.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<div class="notifications-window" id="notificationsWindow" style="display:none;">
    <div class="notifications-header">Notifications</div>
    <div class="notifications-content"></div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var events = <?php echo json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var calendarEl = document.getElementById('calendar');
    var calendarEvents = events.map(function(event) {
        var eventColor = (event.DisplayStatus === 'Available')
            ? 'blue'
            : ((event.DisplayStatus === 'Pre-Booked') ? 'orange' : 'green');
        return {
            title: event.SacramentType + ' (' + event.DisplayStatus + ')',
            start: event.EventDate + 'T' + event.StartTime,
            end: event.EventDate + 'T' + event.EndTime,
            allDay: false,
            color: eventColor,
            extendedProps: {
                priestName: event.PriestName,
                userName: event.FirstName ? event.FirstName + ' ' + event.LastName : null
            }
        };
    });
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: calendarEvents,
        eventOverlap: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        dateClick: function(info) {
            window.location.href = 'sacraments.php';
        },
        eventDidMount: function(info) {
            // Optional: Add tooltip or other behavior
        }
    });
    calendar.render();
});

$(document).ready(function() {
    setInterval(fetchNotificationsCount, 30000);
    fetchNotificationsCount();
});

function fetchNotificationsCount() {
    $.ajax({
        url: 'fetch_notifications.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                var notificationsCount = data.notifications.length;
                if (notificationsCount > 0) {
                    $('#notificationCount').text(notificationsCount).show();
                } else {
                    $('#notificationCount').hide();
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching notifications:', error);
        }
    });
}

function toggleNotifications() {
    var notifWindow = document.getElementById('notificationsWindow');
    if (notifWindow.style.display === 'none' || notifWindow.style.display === '') {
        fetchNotifications();
        notifWindow.style.display = 'block';
    } else {
        notifWindow.style.display = 'none';
    }
}

function fetchNotifications() {
    $.ajax({
        url: 'fetch_notifications.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                var notificationsContent = $('.notifications-content');
                notificationsContent.empty();
                if (data.notifications.length > 0) {
                    data.notifications.forEach(function(notification) {
                        var notificationItem = $('<div class="notification-item"></div>').text(notification.NotificationText);
                        notificationsContent.append(notificationItem);
                    });
                } else {
                    notificationsContent.append('<div class="notification-item">No new notifications.</div>');
                }
                markNotificationsAsRead();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching notifications:', error);
        }
    });
}

function markNotificationsAsRead() {
    $.ajax({
        url: 'mark_notifications_read.php',
        type: 'POST',
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                $('#notificationCount').hide();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error marking notifications as read:', error);
        }
    });
}

function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var toggleBtn = document.getElementById('sidebarToggle');
    sidebar.classList.toggle('collapsed');
    toggleBtn.classList.toggle('collapsed');
    if (sidebar.classList.contains('collapsed')) {
        toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    } else {
        toggleBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
    }
}

window.onclick = function(event) {
    var notifWindow = document.getElementById('notificationsWindow');
    if (!event.target.matches('.fa-bell') && !notifWindow.contains(event.target)) {
        notifWindow.style.display = 'none';
    }
};
</script>
</body>
</html>
