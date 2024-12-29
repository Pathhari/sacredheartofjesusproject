<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: log-in.php");
    exit;
}
if ($_SESSION['role'] != 'admin') {
    header('Location: unauthorized.php');
    exit;
}

$firstname = $_SESSION["firstname"] ?? "User";
$lastname  = $_SESSION["lastname"]  ?? "";
$email     = $_SESSION["email"]     ?? "Not Available";

include 'db.php';

$limit       = 8;
$page        = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = $page;
$offset      = ($page - 1) * $limit;
$sort_order  = (isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc') ? 'ASC' : 'DESC';

$sql = "SELECT 
          SacramentRequests.RefNo, 
          Users.FirstName, 
          Users.LastName,
          SacramentRequests.SacramentType, 
          SacramentRequests.ScheduleDate, 
          SacramentRequests.ScheduleTime,
          SacramentRequests.Status, 
          SacramentRequests.CreatedAt AS SubmittedAt
        FROM SacramentRequests
        JOIN Users ON SacramentRequests.UserID = Users.UserID
        WHERE SacramentRequests.Status IN ('Pending', 'Approved', 'Rejected')
          AND SacramentRequests.Deleted = 0
        ORDER BY SacramentRequests.CreatedAt $sort_order
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$sql_count = "SELECT COUNT(*) as total 
              FROM SacramentRequests 
              WHERE Status IN ('Pending', 'Approved', 'Rejected') 
                AND Deleted = 0";
$count_stmt = $conn->prepare($sql_count);
$count_stmt->execute();
$count_result    = $count_stmt->get_result();
$total_records   = $count_result->fetch_assoc()['total'];
$total_pages     = ceil($total_records / $limit);

$stmt->close();
$count_stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage User Requests - Sacred Heart Parish</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Gotham', sans-serif;
            background-color: #fffaf0;
        }
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
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: center;
                padding: 10px 20px;
                text-align: center;
            }
            .header h1 {
                font-size: 1.25rem;
                white-space: normal;
                text-align: center;
                width: 100%;
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
            .header .logo-container img {
                height: 35px;
                margin-right: 5px;
            }
        }
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1rem;
            }
            .header .logo-container img {
                height: 30px;
            }
        
            .toggle-btn {
                right: 10px;
                top: 15px;
            }
        }
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
        .main-content {
            padding: 20px;
            margin-top: 80px;
            margin-left: 220px;
            width: 100%;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 10px;
            }
        }
        .table-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #2d3748;
        }
        td {
            word-wrap: break-word;
            color: #4a5568;
        }
        .badge {
            font-size: 0.9rem;
        }
        .btn {
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a {
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            color: #2d3748;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }
        .pagination a:hover {
            background-color: #f7fafc;
        }
        .pagination a.active {
            background-color: #e85c0d;
            color: white;
        }
        @media (max-width: 768px) {
            table thead {
                display: none;
            }
            table, table tbody, table tr, table td {
                display: block;
                width: 100%;
            }
            table tr {
                margin-bottom: 20px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 10px;
                box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.05);
                background-color: #ffffff;
            }
            table td {
                text-align: left;
                padding: 10px;
                position: relative;
                margin-bottom: 5px;
                font-size: 14px;
            }
            table td::before {
                content: attr(data-label);
                position: absolute;
                top: 50%;
                left: 15px;
                transform: translateY(-50%);
                font-weight: bold;
                color: #2d3748;
                font-size: 12px;
            }
            table td:first-child::before {
                top: 15px;
            }
            table td[data-label] {
                padding-left: 120px;
            }
            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }
            .pagination a {
                font-size: 0.9rem;
                padding: 8px;
            }
        }
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
        .modal-header {
            background-color: #E85C0D;
            color: white;
        }
        .modal-title {
            font-weight: bold;
        }
        .card-title {
            font-weight: bold;
            color: #E85C0D;
        }
        .text-orange-600 {
            color: #E85C0D;
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
                <a href="log-out.php">Log Out</a>
            </div>
        </div>
    </div>
</header>
<div class="flex">
    <nav class="sidebar collapsed" id="sidebar">
        <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="manage_requestsadmin.php" class="active"><i class="fas fa-tasks"></i> <span>Manage Requests</span></a>
        <a href="manage_usersadmin.php"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
        <a href="calendaradmin.php"><i class="fas fa-calendar-alt"></i> <span>Event Calendar</span></a>
        <a href="announcementsadmin.php"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a>
        <div class="toggle-btn" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-chevron-right"></i>
        </div>
    </nav>
    <div class="p-6 max-w-7xl mx-auto">
        <h2 class="text-3xl font-semibold mb-6 text-gray-800">Manage User Requests</h2>
        <p class="mb-6 text-lg leading-relaxed text-gray-800 bg-gray-100 p-4 rounded-lg shadow-sm border border-gray-300">
            Welcome to the <strong class="text-orange-600">Sacred Heart of Jesus Parish Management System</strong>!
            This dashboard provides an overview of the sacrament requests submitted by users.
            You can approve, reject, or provide feedback on each request. The table below summarizes all current requests.
            Use the sorting option to organize requests by submission date.
        </p>
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th class="text-dark">Ref. No.</th>
                        <th class="text-dark">Requester Name</th>
                        <th class="text-dark">Sacrament Type</th>
                        <th class="text-dark">Scheduled Date</th>
                        <th class="text-dark">Status</th>
                        <th class="text-dark">
                            <a href="?sort=<?php echo $sort_order === 'ASC' ? 'desc' : 'asc'; ?>" class="text-dark">
                                Submitted At
                                <?php if ($sort_order === 'ASC'): ?>
                                    <i class="fas fa-sort-up"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort-down"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th class="text-dark">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td data-label="Ref. No."><?php echo htmlspecialchars($row['RefNo']); ?></td>
                                <td data-label="Requester Name"><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></td>
                                <td data-label="Sacrament Type"><?php echo htmlspecialchars($row['SacramentType']); ?></td>
                                <td data-label="Scheduled Date"><?php echo htmlspecialchars((new DateTime($row['ScheduleDate']))->format('F j, Y')); ?></td>
                                <td data-label="Status">
                                    <?php if ($row['Status'] == 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($row['Status'] == 'Approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif ($row['Status'] == 'Rejected'): ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Submitted At"><?php echo htmlspecialchars((new DateTime($row['SubmittedAt']))->format('F j, Y h:i A')); ?></td>
                                <td data-label="Action">
                                    <a href="#" class="btn btn-info btn-sm view-details-btn" data-ref="<?php echo htmlspecialchars($row['RefNo']); ?>">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No requests found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php if ($current_page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="<?php if ($current_page > 1) echo '?page=' . ($current_page - 1); else echo '#'; ?>">Previous</a>
                    </li>
                    <?php for ($page_num = 1; $page_num <= $total_pages; $page_num++): ?>
                        <li class="page-item <?php if ($page_num == $current_page) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $page_num; ?>"><?php echo $page_num; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="<?php if ($current_page < $total_pages) echo '?page=' . ($current_page + 1); else echo '#'; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <div class="notifications-window" id="notificationsWindow" style="display:none;">
            <div class="notifications-header">Notifications</div>
            <div class="notifications-content"></div>
        </div>
    </div>
</div>
<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-labelledby="requestDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sacrament Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-content-placeholder">Loading...</div>
            </div>
            <div class="modal-footer">
                <div id="modal-action-buttons"></div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="approveConfirmationModal" tabindex="-1" aria-labelledby="approveConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title">Approve Sacrament Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to approve this sacrament request?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApproveButton">Approve</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="rejectConfirmationModal" tabindex="-1" aria-labelledby="rejectConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title">Reject Sacrament Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to reject this sacrament request?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRejectButton">Reject</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title">Delete Sacrament Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this sacrament request? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteButton">Delete</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Provide Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <div class="mb-3">
                        <label for="feedbackMessage" class="form-label">Your Feedback</label>
                        <textarea class="form-control" id="feedbackMessage" rows="4" required></textarea>
                    </div>
                    <input type="hidden" id="feedbackRefNo" value="">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitFeedbackButton">Submit Feedback</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalLabel">Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="messageModalContent"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {
    $('.view-details-btn').on('click', function(e) {
        e.preventDefault();
        var refNo = $(this).data('ref');
        var modal = new bootstrap.Modal(document.getElementById('requestDetailsModal'));
        modal.show();
        $.ajax({
            url: 'fetch_request_details.php',
            type: 'GET',
            data: { ref: refNo },
            beforeSend: function() {
                $('#modal-content-placeholder').html('<p>Loading...</p>');
                $('#modal-action-buttons').html('');
            },
            success: function(data) {
                if (data.success) {
                    var htmlContent = '';
                    htmlContent += '<div class="card mb-4">';
                    htmlContent += '<div class="card-body">';
                    htmlContent += '<h5 class="card-title text-orange-600">General Information</h5>';
                    htmlContent += '<div class="row">';
                    htmlContent += '<div class="col-md-6 mb-2"><strong>Reference Number:</strong> ' + data.request.RefNo + '</div>';
                    htmlContent += '<div class="col-md-6 mb-2"><strong>Requester Name:</strong> ' + data.request.FirstName + ' ' + data.request.LastName + '</div>';
                    htmlContent += '<div class="col-md-6 mb-2"><strong>Sacrament Type:</strong> ' + data.request.SacramentType + '</div>';
                    htmlContent += '<div class="col-md-6 mb-2"><strong>Schedule Date:</strong> ' + new Date(data.request.ScheduleDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + '</div>';
                    htmlContent += '<div class="col-md-6 mb-2"><strong>Schedule Time:</strong> ' + new Date("1970-01-01T" + data.request.ScheduleTime).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) + '</div>';
                    if (data.request.PriestName) {
                        var priestName = data.request.PriestName.replace(', SDB', '');
                        htmlContent += '<div class="col-md-6 mb-2"><strong>Assigned Priest:</strong> ' + priestName + '</div>';
                    } else {
                        htmlContent += '<div class="col-md-6 mb-2"><strong>Assigned Priest:</strong> Not assigned</div>';
                    }
                    htmlContent += '<div class="col-md-6 mb-2"><strong>Submitted Date:</strong> ' + new Date(data.request.CreatedAt).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + '</div>';
                    htmlContent += '<div class="col-md-6 mb-2"><strong>Status:</strong> <span class="badge ' + (data.request.Status === 'Approved' ? 'bg-success' : 'bg-warning text-dark') + '">' + data.request.Status + '</span></div>';
                    htmlContent += '</div>';
                    htmlContent += '</div>';
                    htmlContent += '</div>';
                    htmlContent += '<div class="card mb-4">';
                    htmlContent += '<div class="card-body">';
                    htmlContent += '<h5 class="card-title text-orange-600">Specific Details</h5>';
                    switch (data.request.SacramentType) {
                        case 'Baptism':
                            htmlContent += '<div class="row">';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Child Name:</strong> ' + data.details.ChildName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Date of Birth:</strong> ' + data.details.ChildDOB + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Place of Birth:</strong> ' + data.details.ChildBPlace + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Father\'s Name:</strong> ' + data.details.FatherName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Mother\'s Maiden Name:</strong> ' + data.details.MotherMName + '</div>';
                            htmlContent += '</div>';
                            if (data.godparents && data.godparents.length > 0) {
                                htmlContent += '<h6 class="mt-4 text-orange-600">Godparents</h6>';
                                var godfathers = [];
                                var godmothers = [];
                                data.godparents.forEach(function(gp) {
                                    if (gp.GodparentType.toLowerCase() === 'godfather') {
                                        godfathers.push(gp.GodparentName);
                                    } else if (gp.GodparentType.toLowerCase() === 'godmother') {
                                        godmothers.push(gp.GodparentName);
                                    }
                                });
                                htmlContent += '<div class="row">';
                                htmlContent += '<div class="col-md-6">';
                                htmlContent += '<ul class="list-group list-group-flush godfathers-list">';
                                godfathers.forEach(function(name, index) {
                                    htmlContent += '<li class="list-group-item">Godfather ' + (index + 1) + ': ' + name + '</li>';
                                });
                                htmlContent += '</ul>';
                                htmlContent += '</div>';
                                htmlContent += '<div class="col-md-6">';
                                htmlContent += '<ul class="list-group list-group-flush godmothers-list">';
                                godmothers.forEach(function(name, index) {
                                    htmlContent += '<li class="list-group-item">Godmother ' + (index + 1) + ': ' + name + '</li>';
                                });
                                htmlContent += '</ul>';
                                htmlContent += '</div>';
                                htmlContent += '</div>';
                            }
                            if (data.documents && data.documents.length > 0) {
                                htmlContent += '<h6 class="mt-4 card-title text-orange-600">Uploaded Documents</h6>';
                                htmlContent += '<ul class="list-group list-group-flush uploaded-documents-list">';
                                data.documents.forEach(function(doc) {
                                    htmlContent += '<li class="list-group-item"><a href="download_document.php?doc_id=' + doc.DocumentID + '" target="_blank"><i class="fas fa-file-alt me-2"></i>' + doc.DocumentType + '</a></li>';
                                });
                                htmlContent += '</ul>';
                            } else {
                                htmlContent += '<p class="mt-3">No uploaded documents available.</p>';
                            }
                            break;
                        case 'Wedding':
                            htmlContent += '<div class="row">';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Applicant Name:</strong> ' + data.details.ApplicantName + '</div>';
                            htmlContent += '</div>';
                            if (data.documents && data.documents.length > 0) {
                                htmlContent += '<h6 class="mt-4 card-title text-orange-600">Uploaded Wedding Documents</h6>';
                                htmlContent += '<ul class="list-group list-group-flush uploaded-documents-list">';
                                data.documents.forEach(function(doc) {
                                    htmlContent += '<li class="list-group-item"><a href="download_document.php?doc_id=' + doc.DocumentID + '" target="_blank"><i class="fas fa-file-alt me-2"></i>' + doc.DocumentType + '</a></li>';
                                });
                                htmlContent += '</ul>';
                            } else {
                                htmlContent += '<p class="mt-3">No uploaded wedding documents available.</p>';
                            }
                            break;
                        case 'Confirmation':
                            htmlContent += '<div class="row">';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Full Name:</strong> ' + data.details.FullName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Father\'s Full Name:</strong> ' + data.details.FatherFullName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Mother\'s Full Name:</strong> ' + data.details.MotherFullName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Residence:</strong> ' + data.details.Residence + '</div>';
                            htmlContent += '</div>';
                            if (data.documents && data.documents.length > 0) {
                                htmlContent += '<h6 class="mt-4 card-title text-orange-600">Uploaded Documents</h6>';
                                htmlContent += '<ul class="list-group list-group-flush uploaded-documents-list">';
                                data.documents.forEach(function(doc) {
                                    htmlContent += '<li class="list-group-item"><a href="download_document.php?doc_id=' + doc.DocumentID + '" target="_blank"><i class="fas fa-file-alt me-2"></i>' + doc.DocumentType + '</a></li>';
                                });
                                htmlContent += '</ul>';
                            } else {
                                htmlContent += '<p class="mt-3">No uploaded documents available.</p>';
                            }
                            break;
                        case 'First Communion':
                            htmlContent += '<div class="row">';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Child\'s Full Name:</strong> ' + data.details.FullNameChild + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Date of Birth:</strong> ' + data.details.DateOfBirth + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Place of Birth:</strong> ' + data.details.PlaceOfBirth + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Baptismal Date:</strong> ' + data.details.BaptismalDate + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Baptismal Parish:</strong> ' + data.details.BaptismalParish + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Father\'s Name:</strong> ' + data.details.FatherName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Mother\'s Name:</strong> ' + data.details.MotherName + '</div>';
                            htmlContent += '</div>';
                            if (data.documents && data.documents.length > 0) {
                                htmlContent += '<h6 class="mt-4 card-title text-orange-600">Uploaded Documents</h6>';
                                htmlContent += '<ul class="list-group list-group-flush uploaded-documents-list">';
                                data.documents.forEach(function(doc) {
                                    htmlContent += '<li class="list-group-item"><a href="download_document.php?doc_id=' + doc.DocumentID + '" target="_blank"><i class="fas fa-file-alt me-2"></i>' + doc.DocumentType + '</a></li>';
                                });
                                htmlContent += '</ul>';
                            } else {
                                htmlContent += '<p class="mt-3">No uploaded documents available.</p>';
                            }
                            break;
                        case 'Anointing of the Sick':
                            htmlContent += '<div class="row">';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Person\'s Full Name:</strong> ' + data.details.FullName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Gender:</strong> ' + data.details.Gender + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Age:</strong> ' + data.details.Age + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Address:</strong> ' + data.details.Address + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Phone Number:</strong> ' + data.details.PhoneNumber + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Location of Anointing:</strong> ' + data.details.LocationOfAnointing + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Preferred Date and Time:</strong> ' + new Date(data.details.PreferredDateTime).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true }) + '</div>';
                            htmlContent += '</div>';
                            if (data.documents && data.documents.length > 0) {
                                htmlContent += '<h6 class="mt-4 card-title text-orange-600">Uploaded Documents</h6>';
                                htmlContent += '<ul class="list-group list-group-flush uploaded-documents-list">';
                                data.documents.forEach(function(doc) {
                                    htmlContent += '<li class="list-group-item"><a href="download_document.php?doc_id=' + doc.DocumentID + '" target="_blank"><i class="fas fa-file-alt me-2"></i>' + doc.DocumentType + '</a></li>';
                                });
                                htmlContent += '</ul>';
                            } else {
                                htmlContent += '<p class="mt-3">No uploaded documents available.</p>';
                            }
                            break;
                        case 'Funeral and Burial':
                            htmlContent += '<div class="row">';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Deceased Full Name:</strong> ' + data.details.DeceasedFullName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Date of Birth:</strong> ' + data.details.DeceasedDateOfBirth + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Date of Death:</strong> ' + data.details.DeceasedDateOfDeath + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Age:</strong> ' + data.details.Age + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Cause of Death:</strong> ' + data.details.CDeath + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Address:</strong> ' + data.details.Address + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Burial Location:</strong> ' + data.details.BLocation + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Mass Time:</strong> ' + data.details.BMassTime + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Funeral Service Type:</strong> ' + data.details.FuneralServiceType + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Family Representative:</strong> ' + data.details.FamilyRepresentative + '</div>';
                            htmlContent += '</div>';
                            break;
                        case 'Blessing':
                            htmlContent += '<div class="row">';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Full Name:</strong> ' + data.details.FullName + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Address:</strong> ' + data.details.Address + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Contact Number:</strong> ' + data.details.RequesterContact + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Blessing Type:</strong> ' + data.details.BlessingType + '</div>';
                            htmlContent += '<div class="col-md-6 mb-2"><strong>Location of Blessing:</strong> ' + data.details.BlessingPlace + '</div>';
                            htmlContent += '</div>';
                            break;
                        default:
                            htmlContent += '<p>No additional details available.</p>';
                    }
                    htmlContent += '</div></div>';
                    $('#modal-content-placeholder').html(htmlContent);
                    var actionButtons = '';
                    if (data.request.Status === 'Pending') {
                        actionButtons += '<button class="btn btn-success me-2 approve-btn" data-ref="' + encodeURIComponent(data.request.RefNo) + '" data-requires-priest-assignment="' + data.requiresPriestAssignment + '"><i class="fas fa-check me-1"></i> Approve</button>';
                        actionButtons += '<button class="btn btn-danger me-2 reject-btn" data-ref="' + encodeURIComponent(data.request.RefNo) + '"><i class="fas fa-times me-1"></i> Reject</button>';
                    } else if (data.request.Status === 'Approved' || data.request.Status === 'Rejected') {
                        if (data.hasFeedback) {
                            actionButtons += '<button class="btn btn-info me-2 view-feedback-btn" data-ref="' + encodeURIComponent(data.request.RefNo) + '"><i class="fas fa-comments me-1"></i> View Feedback</button>';
                        } else {
                            actionButtons += '<button class="btn btn-primary me-2 feedback-btn" data-ref="' + encodeURIComponent(data.request.RefNo) + '"><i class="fas fa-comments me-1"></i> Add Feedback</button>';
                        }
                        actionButtons += '<button class="btn btn-danger me-2 delete-btn" data-ref="' + encodeURIComponent(data.request.RefNo) + '"><i class="fas fa-trash me-1"></i> Delete</button>';
                    }
                    if (data.request.SacramentType === 'Baptism') {
                        actionButtons += '<a href="baptismrequest_form.php?ref=' + encodeURIComponent(data.request.RefNo) + '" class="btn btn-primary" target="_blank"><i class="fas fa-print me-1"></i> Print</a>';
                    } else if (data.request.SacramentType === 'Wedding') {
                        actionButtons += '<a href="weddingrequest_form.php?ref=' + encodeURIComponent(data.request.RefNo) + '" class="btn btn-primary" target="_blank"><i class="fas fa-print me-1"></i> Print</a>';
                    } else {
                        actionButtons += '<a href="other_sacrament_form.php?ref=' + encodeURIComponent(data.request.RefNo) + '" class="btn btn-primary" target="_blank"><i class="fas fa-print me-1"></i> Print</a>';
                    }
                    $('#modal-action-buttons').html(actionButtons);
                    if (data.requiresPriestAssignment && data.request.Status !== 'Approved')  {
                        $.ajax({
                            url: 'fetch_available_priests.php',
                            type: 'GET',
                            data: {
                                date: data.request.ScheduleDate,
                                time: data.request.ScheduleTime
                            },
                            success: function(priestsData) {
                                if (priestsData.success) {
                                    var priestOptions = '';
                                    priestsData.priests.forEach(function(priest) {
                                        priestOptions += '<option value="' + priest.PriestID + '">' + priest.PriestName + '</option>';
                                    });
                                    var priestAssignmentHtml = '<div class="mt-4">';
                                    priestAssignmentHtml += '<h5 class="card-title text-orange-600">Assign a Priest</h5>';
                                    priestAssignmentHtml += '<select id="priestSelect" class="form-control">';
                                    priestAssignmentHtml += '<option value="">Select a Priest</option>';
                                    priestAssignmentHtml += priestOptions;
                                    priestAssignmentHtml += '</select></div>';
                                    $('#modal-content-placeholder').append(priestAssignmentHtml);
                                } else {
                                    $('#modal-content-placeholder').append('<p class="mt-3 text-danger">No priests are available at the selected date and time.</p>');
                                }
                            },
                            error: function() {
                                $('#modal-content-placeholder').append('<p class="mt-3 text-danger">Error fetching available priests.</p>');
                            },
                            dataType: 'json'
                        });
                    } else if (data.request.Status === 'Approved') {
                        $('#modal-content-placeholder').append('<p class="mt-3 text-success">Sacrament Approved.</p>');
                    }
                } else {
                    showMessageModal('Error', 'Error fetching details: ' + data.message);
                }
            },
            error: function() {
                showMessageModal('Error', 'Error fetching details.');
            },
            dataType: 'json'
        });
    });
    $(document).on('click', '.approve-btn', function() {
        var refNo = $(this).data('ref');
        var requiresPriestAssignment = $(this).data('requires-priest-assignment');
        requiresPriestAssignment = (requiresPriestAssignment === true || requiresPriestAssignment === 'true');
        $('#confirmApproveButton').data('ref', refNo);
        $('#confirmApproveButton').data('requires-priest-assignment', requiresPriestAssignment);
        var approveModal = new bootstrap.Modal(document.getElementById('approveConfirmationModal'));
        approveModal.show();
    });
    $('#confirmApproveButton').on('click', function() {
        var refNo = $(this).data('ref');
        var requiresPriestAssignment = $(this).data('requires-priest-assignment');
        var priestID = null;
        if (requiresPriestAssignment) {
            priestID = $('#priestSelect').val();
            if (!priestID) {
                showMessageModal('Validation Error', 'Please select a priest to assign.');
                return;
            }
        }
        var redirectUrl = 'approve_request.php';
        var form = $('<form action="' + redirectUrl + '" method="post">' +
            '<input type="hidden" name="ref" value="' + refNo + '" />' +
            (priestID ? '<input type="hidden" name="priestID" value="' + priestID + '" />' : '') +
            '</form>');
        $('body').append(form);
        form.submit();
    });
    $(document).on('click', '.reject-btn', function() {
        var refNo = $(this).data('ref');
        $('#confirmRejectButton').data('ref', refNo);
        var rejectModal = new bootstrap.Modal(document.getElementById('rejectConfirmationModal'));
        rejectModal.show();
    });
    $('#confirmRejectButton').on('click', function() {
        var refNo = $(this).data('ref');
        $.ajax({
            url: 'reject_request.php',
            type: 'POST',
            data: { ref: refNo },
            success: function(data) {
                if (data.success) {
                    var rejectModalInstance = bootstrap.Modal.getInstance(document.getElementById('rejectConfirmationModal'));
                    rejectModalInstance.hide();
                    $('#feedbackRefNo').val(refNo);
                    $('#feedbackMessage').val('');
                    var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
                    feedbackModal.show();
                } else {
                    showMessageModal('Error', 'Error rejecting request: ' + data.message);
                }
            },
            error: function() {
                showMessageModal('Error', 'Error rejecting request.');
            },
            dataType: 'json'
        });
    });
    $(document).on('click', '.feedback-btn', function(e) {
        e.preventDefault();
        var refNo = $(this).data('ref');
        $('#feedbackRefNo').val(refNo);
        $('#feedbackMessage').val('');
        var feedbackModal = new bootstrap.Modal(document.getElementById('feedbackModal'));
        feedbackModal.show();
    });
    $('#submitFeedbackButton').on('click', function() {
        var refNo = $('#feedbackRefNo').val();
        var feedbackMessage = $('#feedbackMessage').val();
        if (feedbackMessage.trim() !== '') {
            $.ajax({
                url: 'submit_feedback.php',
                type: 'POST',
                data: {
                    ref: refNo,
                    feedback: feedbackMessage
                },
                success: function(data) {
                    if (data.success) {
                        showMessageModal('Success', 'Feedback submitted successfully.');
                        var feedbackModalInstance = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
                        feedbackModalInstance.hide();
                        location.reload();
                    } else {
                        showMessageModal('Error', 'Error submitting feedback: ' + data.message);
                    }
                },
                error: function() {
                    showMessageModal('Error', 'Error submitting feedback.');
                },
                dataType: 'json'
            });
        } else {
            showMessageModal('Validation Error', 'Please enter your feedback message.');
        }
    });
    $(document).on('click', '.view-feedback-btn', function(e) {
        e.preventDefault();
        var refNo = $(this).data('ref');
        var $button = $(this);
        var feedbackSectionId = '#feedback-section-' + refNo;
        if ($(feedbackSectionId).length) {
            $(feedbackSectionId).toggle();
            if ($(feedbackSectionId).is(':visible')) {
                $button.html('<i class="fas fa-eye-slash me-1"></i> Hide Feedback');
            } else {
                $button.html('<i class="fas fa-eye me-1"></i> View Feedback');
            }
        } else {
            $.ajax({
                url: 'fetch_feedbackadmin.php',
                type: 'GET',
                data: { ref: refNo },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        var feedbackContent = '<div class="card mt-4" id="feedback-section-' + refNo + '">';
                        feedbackContent += '<div class="card-body">';
                        feedbackContent += '<h5 class="card-title text-orange-600">Feedback</h5>';
                        feedbackContent += '<p>' + htmlspecialchars(data.feedback.FeedbackText) + '</p>';
                        feedbackContent += '<p><small class="text-muted">Submitted on: ' + htmlspecialchars(data.feedback.SubmittedAt) + '</small></p>';
                        feedbackContent += '</div></div>';
                        $('#modal-content-placeholder').append(feedbackContent);
                        $button.html('<i class="fas fa-eye-slash me-1"></i> Hide Feedback');
                        $('#modal-content-placeholder').animate({
                            scrollTop: $('#modal-content-placeholder')[0].scrollHeight
                        }, 500);
                    } else {
                        showMessageModal('Error', 'Error fetching feedback: ' + htmlspecialchars(data.message));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    showMessageModal('Error', 'Error fetching feedback.');
                }
            });
        }
    });
    function htmlspecialchars(str) {
        return $('<div>').text(str).html();
    }
    document.addEventListener("DOMContentLoaded", function() {
        var sidebar = document.getElementById('sidebar');
        var toggleBtn = document.getElementById('sidebarToggle');
        sidebar.classList.add('collapsed');
        toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    });
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
    function showMessageModal(title, message) {
        $('#messageModalLabel').text(title);
        $('#messageModalContent').html(message);
        var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
        messageModal.show();
    }
    $('#confirmDeleteButton').on('click', function() {
        var refNo = $(this).data('ref');
        $.ajax({
            url: 'delete_request.php',
            type: 'POST',
            data: { ref: refNo },
            success: function(data) {
                if (data.success) {
                    var deleteModalInstance = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmationModal'));
                    deleteModalInstance.hide();
                    var requestDetailsModalInstance = bootstrap.Modal.getInstance(document.getElementById('requestDetailsModal'));
                    requestDetailsModalInstance.hide();
                    $('a.view-details-btn[data-ref="' + refNo + '"]').closest('tr').remove();
                    showMessageModal('Success', 'Sacrament request deleted successfully.');
                } else {
                    showMessageModal('Error', 'Error deleting request: ' + data.message);
                }
            },
            error: function() {
                showMessageModal('Error', 'Error deleting request.');
            },
            dataType: 'json'
        });
    });
});
$(document).ready(function() {
    setInterval(fetchNotificationsCount, 30000);
    fetchNotificationsCount();
});
function fetchNotificationsCount() {
    $.ajax({
        url: 'fetch_admin_notifications.php',
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
        url: 'fetch_admin_notifications.php',
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
        url: 'mark_admin_notifications_read.php',
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
window.onclick = function(event) {
    var notifWindow = document.getElementById('notificationsWindow');
    if (!event.target.matches('.fa-bell') && !notifWindow.contains(event.target)) {
        notifWindow.style.display = 'none';
    }
}
</script>
</body>
</html>
