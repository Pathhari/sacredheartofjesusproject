<?php
// Start session
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: log-in.php");
    exit;
}

// Fetch user details from session
$firstname = $_SESSION["firstname"] ?? "User";
$lastname = $_SESSION["lastname"] ?? "";
$email = $_SESSION["email"] ?? "Not Available";

// Include database connection
include 'db.php';

// Fetch available wedding slots
$sql_slots = "
    SELECT e.*, p.PriestName
    FROM UpcomingEvents e
    LEFT JOIN Priests p ON e.PriestID = p.PriestID
    WHERE e.SacramentType = 'Wedding' AND e.Status = 'Available' AND e.EventDate >= CURDATE()
    ORDER BY e.EventDate, e.StartTime
";
$result_slots = $conn->query($sql_slots);

$available_slots = [];
if ($result_slots->num_rows > 0) {
    while ($row = $result_slots->fetch_assoc()) {
        $available_slots[] = $row;
    }
}

// Preserve form data
$form_data = $_SESSION['form_data'] ?? [];

// Include any error messages
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

// Success message handling
if (isset($_SESSION['success_message'])) {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                icon: "success",
                title: "Success!",
                text: "Your Wedding request has been successfully submitted. Please wait for approval.",
                confirmButtonColor: "#E85C0D",
                confirmButtonText: "OK"
            });
        });
    </script>';
    unset($_SESSION['success_message']); // Clear the success message after displaying it
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacraments Request - Wedding Request</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <style>
       body {
            font-family: 'Gotham', sans-serif;
            background-color: #fffaf0; /* Light orange background */
        }

        /* Header */
        .header {
            background-color: #E85C0D;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            color: white;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .header .logo-container {
            display: flex;
            align-items: center;
        }

        .header img {
            height: 50px;
            margin-right: 15px;
        }

        .header h1 {
            font-size: 1.75rem;
            margin: 0;
        }

        .header-icons {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }

        .header-icons .fas.fa-bell {
            cursor: pointer;
            position: relative;
        }
        /* Sidebar */
        .sidebar {
    background-color: #2d3748;
    width: 220px;
    min-height: 100vh;
    padding-top: 20px;
    color: white;
    position: fixed; /* Changed from relative to fixed */
    top: 80px; /* Adjust based on the header height */
    left: 0;
    z-index: 999; /* Ensure it stays above other content */
    transition: width 0.3s ease;
}
.flex {
    display: flex;
    min-height: 100vh;
    padding-top: 80px; /* Adjust based on header height */
}
.p-6 {
    padding: 1.5rem;
    max-width: 100%;
    overflow: auto;
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
            background-color: #F6AD55; /* Orange */
            color: white;
        }

        .sidebar a.active i {
            color: white;
        }
        .btn-primary {
    background-color: #E85C0D;
    color: white;
    padding: 12px 35px;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    font-size: 18px;
    font-weight: bold;
    display: inline-block;
    text-align: center;
    border: none;
}

.btn-primary:hover {
    background-color: #CC4A05;
    transform: translateY(-3px); /* Slight hover lift effect */
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.15); /* Add shadow on hover */
}

.btn-primary:active {
    transform: translateY(1px); /* Button pushes down slightly on click */
    box-shadow: none;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    font-size: 16px;
    font-weight: bold;
    display: inline-block;
    text-align: center;
    border: none;
    margin-right: 10px; /* Adds space between Previous and Submit button */
}

.btn-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-3px);
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.15);
}

.btn-secondary:active {
    transform: translateY(1px);
    box-shadow: none;
}

.btn-container {
    display: flex;
    justify-content: center; /* Center the submit button */
    gap: 20px; /* Space between buttons */
    margin-top: 20px;
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

/* Smooth scrolling for modern feel */
.notifications-content {
    scroll-behavior: smooth;
}

        .back-button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 20px;
        border-radius: 8px;
        background-color: #E85C0D; /* Deep orange */
        color: white;
        font-weight: 600;
        transition: background-color 0.3s ease;
        text-decoration: none;
        margin-bottom: 20px;
    }

    .back-button:hover {
        background-color: #CC4A05;
    }

    .back-button i {
        margin-right: 8px;
    }
    .form-container {
    background-color: #ffffff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    padding: 30px;
    margin-top: 30px;
}
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            display: inline-block;
        }

        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #E85C0D;
            outline: none;
            box-shadow: 0 0 0 3px rgba(232, 92, 13, 0.2);
        }

         .file-upload {
        position: relative;
        margin-top: 0.5rem;
    }

    .file-input {
        display: block;
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
        color: #4a5568;
        transition: border-color 0.3s ease, background-color 0.3s ease;
    }

    .file-input:hover, .file-input:focus {
        border-color: #E85C0D;
    }

    .file-input.file-selected {
        border-color: #28a745;
        background-color: #e6ffe6;
    }

    .upload-icon {
        position: absolute;
        top: 50%;
        right: 1rem;
        transform: translateY(-50%);
        font-size: 1.25rem;
        color: #E85C0D;
        transition: color 0.3s ease;
    }

    .upload-icon.uploaded {
        color: #28a745;
    }

    .form-label {
        font-size: 1rem;
        color: #4a5568;
        font-weight: 500;
    }
      /* Responsive Styles */
@media (max-width: 768px) {
    .header {
                flex-direction: column;
                align-items: center;
                padding: 10px 20px;
                text-align: center; /* Center text within the header */
            }
    .header h1 {
                font-size: 1.25rem;
                white-space: normal;
                text-align: center; /* Center only the text */
        width: 100%; /* Ensure it takes full width for centering */
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
    .sidebar {
        position: fixed;
        height: 100vh;
        left: -220px;
        transition: left 0.3s;
    }

    .sidebar.active {
        left: 0;
    }

    .toggle-btn {
        display: block;
    }

    .header .logo-container img {
        height: 35px;
        margin-right: 5px;
    }


    /* Adjust form grid */
    .form-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .godparent-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .header h1 {
        font-size: 1rem;
    }

    .form-container {
        padding: 20px;
    }

    .back-button {
        padding: 8px 15px;
    }

    .header .logo-container img {
        height: 30px;
    }

    .toggle-btn {
        right: 10px;
        top: 15px;
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

<!-- Sidebar -->
<div class="flex">
    <nav class="sidebar collapsed" id="sidebar">
        <a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="sacraments.php" class="active"><i class="fas fa-cross"></i> <span>Sacraments</span></a>
        <a href="calenderuser.php"><i class="fas fa-calendar-alt"></i> <span>Calendar</span></a>
        <a href="announcementsuser.php"><i class="fas fa-bullhorn"></i> <span>Announcements</span></a>

        <!-- Sidebar Toggle Button -->
        <div class="toggle-btn" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-chevron-right"></i>
        </div>
    </nav>


    <!-- Sacraments Page Content -->
    <div class="p-6 max-w-7xl mx-auto">
        <!-- Back Button -->
        <a href="sacraments.php" class="back-button">
    <i class="fas fa-arrow-left"></i> Back to Sacraments
</a>
<!-- Error Message Display -->
<?php if ($error_message): ?>
    <div class="bg-red-100 text-red-700 p-4 mb-4 rounded">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

        <h2 class="text-3xl font-semibold mb-6 text-gray-800">Wedding Request</h2>
        <!-- Back Button -->

        <!-- Overview Text -->
        <p class="text-lg text-gray-600 mb-8">Please fill out the required fields before you submit the Wedding request and be sure to double check each information of the fields to complete the request.</p>
        <p class="text-lg font-bold text-red-700 bg-orange-200 p-4 rounded-md mb-4">
        IMPORTANT: If the papers or the files are not complete, no schedule will be set.
    </p>
  
        <!-- Wedding Request Form -->
        <div class="form-container">
  <form action="wedding_requestaccept.php" method="POST" class="space-y-6" enctype="multipart/form-data">
        <h2 class="text-xl font-semibold text-gray-800">Wedding Form</h2>
        
        <label for="applicantName" class="form-label">Pangalan sa magrequest</label>
        <input type="text" class="form-control block w-full" id="applicantName" name="applicantName" required>

        <h3 class="text-lg font-bold text-red-600 mt-4">Mga papelis paga-andamon</h3>

        <!-- Document Sections -->
        <div class="mt-4">
            <h4 class="font-semibold">1. PAGLUKAT OG MARRIAGE LICENSE PERMIT</h4>
            
            <p class="text-gray-700">A. POPCOM CERTIFICATE (TREE PLANTING CERT.)</p>
            <div class="file-upload">
                <input type="file" name="popcomCertificate" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <p class="text-gray-700">B. BIRTH CERTIFICATE</p>
            <div class="file-upload">
                <input type="file" name="birthCertificate" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <p class="text-gray-700">C. CENOMAR</p>
            <div class="file-upload">
                <input type="file" name="cenomar" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <p class="text-gray-700">D. 1X1 PICTURE</p>
            <div class="file-upload">
                <input type="file" name="picture" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <p class="text-gray-700">E. CEDULA (18 TO 25 YRS OLD)</p>
            <div class="file-upload">
                <input type="file" name="cedula" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>
        </div>

        <!-- Church Document Section -->
        <h3 class="text-lg font-bold text-red-600 mt-4">Para sa simbahan papelis paga-andamon</h3>

        <div class="mt-4">
            <h4 class="font-semibold">2. BAPTISMAL CERTIFICATE</h4>
            <div class="file-upload">
                <input type="file" name="baptismalCertificate" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <h4 class="font-semibold">3. CONFIRMATION CERTIFICATE</h4>
            <div class="file-upload">
                <input type="file" name="confirmationCertificate" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <h4 class="font-semibold">4. DYA or GKK CERTIFICATION</h4>
            <div class="file-upload">
                <input type="file" name="gkkCertification" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <h4 class="font-semibold">5. GKK CERTIFICATION OR RECOMMENDATION GINIKANAN</h4>
            <div class="file-upload">
                <input type="file" name="gkkParentCertification" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <h4 class="font-semibold">6. GKK CERTIFICATION OR SPONSORS RECOMMENDATION</h4>
            <div class="file-upload">
                <input type="file" name="gkkSponsorCertification" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <h4 class="font-semibold">7. FLAM INTERVIEW</h4>
            <div class="file-upload">
                <input type="file" name="flamInterview" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <h4 class="font-semibold">8. PRE-CANA CERTIFICATE</h4>
            <div class="file-upload">
                <input type="file" name="preCanaCertificate" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>

            <h4 class="font-semibold">9. 3 DOMINGO TAWAG KASAL OR (REQUEST BANN OTHERS PARISH)</h4>
            <div class="file-upload">
                <input type="file" name="requestBannOthersParish" class="file-input" accept=".png, .jpg, .jpeg, .pdf">
                <span class="upload-icon"><i class="fas fa-upload"></i></span>
            </div>
        </div>
                <!-- Scheduling Options -->
                <div class="mt-6 bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Select Scheduling Option</h3>
                    <label class="inline-flex items-center">
                        <input type="radio" class="form-radio" name="scheduling_option" value="pre_scheduled" <?php if (($form_data['scheduling_option'] ?? 'pre_scheduled') === 'pre_scheduled') echo 'checked'; ?> onclick="toggleSchedulingOption()">
                        <span class="ml-2">Choose from Available Slots</span>
                    </label>
                    <label class="inline-flex items-center ml-6">
                        <input type="radio" class="form-radio" name="scheduling_option" value="preferred_datetime" <?php if (($form_data['scheduling_option'] ?? '') === 'preferred_datetime') echo 'checked'; ?> onclick="toggleSchedulingOption()">
                        <span class="ml-2">Select Preferred Date and Time (Additional Fee)</span>
                    </label>
                </div>

                <!-- Available Slots Section -->
                <div id="preScheduledSlots" class="mt-6 bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Available Wedding Slots</h3>
                    <?php if (!empty($available_slots)): ?>
                        <label for="SelectedSlot" class="form-label">Choose a Slot</label>
                        <select id="SelectedSlot" name="SelectedSlot" class="form-control">
                            <option value="">-- Select a Slot --</option>
                            <?php foreach ($available_slots as $slot): ?>
                                <option value="<?php echo $slot['EventID']; ?>" <?php if (($form_data['SelectedSlot'] ?? '') == $slot['EventID']) echo 'selected'; ?>>
                                <?php echo date('F j, Y', strtotime($slot['EventDate'])) . ' - ' . date('h:i A', strtotime($slot['StartTime'])) . ' to ' . date('h:i A', strtotime($slot['EndTime'])) . ' with ' . htmlspecialchars($slot['PriestName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p class="text-red-600">No available wedding slots at the moment. Please check back later or select a preferred date and time.</p>
                    <?php endif; ?>
                </div>

                <!-- Preferred Date and Time Section -->
                <div id="preferredDateTime" class="mt-6 bg-white p-6 rounded-lg shadow-md" style="display: none;">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Preferred Date and Time (Additional Fee)</h3>
                    <label for="PreferredWeddingDate" class="form-label">Preferred Date</label>
                    <input type="date" id="PreferredWeddingDate" name="PreferredWeddingDate" value="<?php echo htmlspecialchars($form_data['PreferredWeddingDate'] ?? ''); ?>" class="form-control">

                    <label for="PreferredWeddingTime" class="form-label mt-4">Preferred Time</label>
                    <input type="time" id="PreferredWeddingTime" name="PreferredWeddingTime" value="<?php echo htmlspecialchars($form_data['PreferredWeddingTime'] ?? ''); ?>" class="form-control">
                </div>

                <!-- Submit Button -->
                <div class="btn-container mt-6">
                <button type="button" class="btn btn-primary" onclick="showConfirmationModal()">Submit Request</button>
                </div>
            </form>
        </div>


      <!-- Confirmation Modal -->
      <div id="confirmationModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-8 w-3/4 max-w-md text-center">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Confirmation</h2>
                <p class="text-gray-700 mb-6">Are you sure all the information is true and double-checked for the request approval?</p>
                <div class="flex justify-center gap-4">
                    <button onclick="confirmSubmit()" class="px-6 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700 transition duration-200">Yes, Submit</button>
                    <button onclick="closeConfirmationModal()" class="px-6 py-2 rounded-lg bg-gray-500 text-white hover:bg-gray-600 transition duration-200">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Success Modal -->
        <div id="successModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden">
            <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md text-center">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Success!</h2>
                <p class="text-gray-700 mb-6">Your wedding request has been successfully submitted. Please wait for approval.</p>
                <button onclick="closeSuccessModal()" class="px-6 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700 transition duration-200">OK</button>
            </div>
        </div>
    </div>
</div>
<!-- Notification Window -->
<div class="notifications-window" id="notificationsWindow" style="display:none;">
    <div class="notifications-header">Notifications</div>
    <div class="notifications-content"></div>
</div>



<script>

function toggleSchedulingOption() {
    var schedulingOption = document.querySelector('input[name="scheduling_option"]:checked').value;
    if (schedulingOption === 'pre_scheduled') {
        document.getElementById('preScheduledSlots').style.display = 'block';
        document.getElementById('preferredDateTime').style.display = 'none';
    } else {
        document.getElementById('preScheduledSlots').style.display = 'none';
        document.getElementById('preferredDateTime').style.display = 'block';
    }
}

// Initialize the correct scheduling option on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleSchedulingOption();
});
function showConfirmationModal() {
    Swal.fire({
        title: 'Are you sure?',
        text: 'Are you certain all the information is correct and ready for submission?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E85C0D', // Match your theme color
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Submit',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Submit the form
            document.querySelector('form').submit();
        }
    });
}

        // Show the success modal if there is a success message in session
        document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['success_message'])): ?>
            showSuccessModal();
            <?php unset($_SESSION['success_message']); ?> // Clear the session message after showing the modal
        <?php endif; ?>
    });

    function showSuccessModal() {
        document.getElementById('successModal').classList.remove('hidden');
    }

    function closeSuccessModal() {
        document.getElementById('successModal').classList.add('hidden');
    }

    // Adding green border and icon color when a file is selected
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function () {
            if (this.files.length > 0) {
                this.classList.add('file-selected');
                this.nextElementSibling.classList.add('uploaded'); // Adds green icon
            } else {
                this.classList.remove('file-selected');
                this.nextElementSibling.classList.remove('uploaded');
            }
        });
    });
        
    // Collapse the sidebar by default on page load
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

    $(document).ready(function() {
        // Fetch notifications count every 30 seconds
        setInterval(fetchNotificationsCount, 30000); // 30 seconds

        fetchNotificationsCount();
    });

    // Function to fetch notifications count
    function fetchNotificationsCount() {
        $.ajax({
            url: 'fetch_notifications.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    var notificationsCount = data.notifications.length;
                    if (notificationsCount > 0) {
                        // Update the notification count badge
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
            // Fetch and display notifications
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
                    notificationsContent.empty(); // Clear existing notifications

                    if (data.notifications.length > 0) {
                        data.notifications.forEach(function(notification) {
                            var notificationItem = $('<div class="notification-item"></div>').text(notification.NotificationText);
                            notificationsContent.append(notificationItem);
                        });
                    } else {
                        notificationsContent.append('<div class="notification-item">No new notifications.</div>');
                    }

                    // Mark notifications as read
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
                    // Hide the notification count badge
                    $('#notificationCount').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error marking notifications as read:', error);
            }
        });
    }

    // Hide notification window when clicking outside
    window.onclick = function(event) {
        var notifWindow = document.getElementById('notificationsWindow');
        if (!event.target.matches('.fa-bell') && !notifWindow.contains(event.target)) {
            notifWindow.style.display = 'none';
        }
    }
</script>

</body>
</html>
