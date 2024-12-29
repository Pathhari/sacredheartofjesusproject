<?php
// Include the database connection
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();

    // Start transaction
    $conn->begin_transaction();

    try {
        // Fetch UserID from session
        $UserID = $_SESSION['userid'];

        // Retrieve form data
        $schedulingOption = $_POST['scheduling_option'] ?? 'pre_scheduled';

        // Store form data in session to retain values if needed
        $_SESSION['form_data'] = $_POST;

        // Common form data
        $applicantName = $_POST['applicantName'] ?? null;
        // ... [Retrieve other necessary form fields]

        if ($schedulingOption === 'pre_scheduled') {
            // Handle pre-scheduled slot
            $selectedSlotID = $_POST['SelectedSlot'] ?? null;

            if (!$selectedSlotID) {
                throw new Exception("No wedding slot selected. Please select an available slot.");
            }

            // Check if the selected slot is still available
            $sql_check_slot = "SELECT * FROM UpcomingEvents WHERE EventID = ? AND Status = 'Available' FOR UPDATE";
            $stmt_check_slot = $conn->prepare($sql_check_slot);
            $stmt_check_slot->bind_param("i", $selectedSlotID);
            $stmt_check_slot->execute();
            $result_check_slot = $stmt_check_slot->get_result();

            if ($result_check_slot->num_rows == 0) {
                throw new Exception("The selected wedding slot is no longer available. Please select another slot.");
            }

            // Fetch slot details
            $slot = $result_check_slot->fetch_assoc();
            $PreferredWeddingDate = $slot['EventDate'];
            $PreferredWeddingTime = $slot['StartTime'];
            $PriestID = $slot['PriestID'];

            // Update UpcomingEvents to mark the slot as 'Pre-Booked'
            $update_slot = "UPDATE UpcomingEvents SET Status = 'Pre-Booked' WHERE EventID = ?";
            $stmt_update_slot = $conn->prepare($update_slot);
            $stmt_update_slot->bind_param("i", $selectedSlotID);

            if (!$stmt_update_slot->execute()) {
                throw new Exception("Failed to update the slot status: " . $stmt_update_slot->error);
            }

        } elseif ($schedulingOption === 'preferred_datetime') {
            // Handle preferred date and time
            $PreferredWeddingDate = $_POST['PreferredWeddingDate'] ?? null;
            $PreferredWeddingTime = $_POST['PreferredWeddingTime'] ?? null;

            if (!$PreferredWeddingDate || !$PreferredWeddingTime) {
                throw new Exception("Please provide both preferred date and time.");
            }

            // Conflict checking for any existing events, regardless of status
            $userStartTime = $PreferredWeddingTime;
            $userEndTime = date('H:i:s', strtotime('+1 hour', strtotime($PreferredWeddingTime))); // Assuming event lasts 1 hour

            $sql_conflict = "
                SELECT * FROM UpcomingEvents 
                WHERE EventDate = ? 
                AND StartTime < ? 
                AND EndTime > ?
            ";
            $stmt_conflict = $conn->prepare($sql_conflict);
            $stmt_conflict->bind_param("sss", $PreferredWeddingDate, $userEndTime, $userStartTime);
            $stmt_conflict->execute();
            $result_conflict = $stmt_conflict->get_result();

            if ($result_conflict->num_rows > 0) {
                throw new Exception("The date and time you preferred are not available as there is an existing event scheduled by the admin. Please choose from the available slots.");
            }

            // No priest assigned yet, set PriestID to NULL
            $PriestID = null;

            // Calculate end time
            $endTime = date('H:i:s', strtotime('+1 hour', strtotime($PreferredWeddingTime))); // Assuming event lasts 1 hour

            // Create a new event in UpcomingEvents with Status 'Pre-Booked'
            $sql_insert_event = "
                INSERT INTO UpcomingEvents (EventDate, StartTime, EndTime, SacramentType, PriestID, Status)
                VALUES (?, ?, ?, ?, ?, 'Pre-Booked')
            ";
            $stmt_insert_event = $conn->prepare($sql_insert_event);
            $sacramentType = 'Wedding';
            $stmt_insert_event->bind_param("ssssi", $PreferredWeddingDate, $PreferredWeddingTime, $endTime, $sacramentType, $PriestID);

            if (!$stmt_insert_event->execute()) {
                throw new Exception("Failed to create a new event: " . $stmt_insert_event->error);
            }

            $selectedSlotID = $stmt_insert_event->insert_id;

        } else {
            throw new Exception("Invalid scheduling option.");
        }

        // Generate a random Reference Number (RefNo)
        $RefNo = strtoupper(bin2hex(random_bytes(4))); // Generates an 8-character alphanumeric RefNo

        // Insert into SacramentRequests table with PriestID as NULL
        $stmt = $conn->prepare("
            INSERT INTO SacramentRequests 
            (RefNo, UserID, SacramentType, PriestID, ScheduleDate, ScheduleTime, Status, EventID) 
            VALUES (?, ?, 'Wedding', ?, ?, ?, 'Pending', ?)
        ");
        $stmt->bind_param("siissi", $RefNo, $UserID, $PriestID, $PreferredWeddingDate, $PreferredWeddingTime, $selectedSlotID);

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert into SacramentRequests: " . $stmt->error);
        }

        // Insert into WeddingRequest table
        $stmt_wedding = $conn->prepare("
            INSERT INTO WeddingRequest 
            (RefNo, ApplicantName, PreferredWeddingDate, PreferredWeddingTime, Status) 
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt_wedding->bind_param("ssss", $RefNo, $applicantName, $PreferredWeddingDate, $PreferredWeddingTime);

        if (!$stmt_wedding->execute()) {
            throw new Exception("Failed to insert into WeddingRequest: " . $stmt_wedding->error);
        }

        // Get the last inserted RequesterID from the WeddingRequest table
        $RequesterID = $conn->insert_id;

        // Handle file uploads
        $uploads_dir = 'weddinguploads/';

        // Ensure upload directory exists
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
        }

        // List of files to be uploaded and their respective document types
        $files = [
            'popcomCertificate' => 'Popcom Certificate',
            'birthCertificate' => 'Birth Certificate',
            'cenomar' => 'Cenomar',
            'picture' => 'Picture',
            'cedula' => 'Cedula',
            'baptismalCertificate' => 'Baptismal Certificate',
            'confirmationCertificate' => 'Confirmation Certificate',
            'gkkCertification' => 'GKK Certification',
            'gkkParentCertification' => 'GKK Parent Certification',
            'gkkSponsorCertification' => 'GKK Sponsor Certification',
            'flamInterview' => 'Flam Interview',
            'preCanaCertificate' => 'Pre-Cana Certificate',
            'requestBannOthersParish' => 'Request Bann Others Parish'
        ];

        foreach ($files as $input_name => $document_type) {
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] == 0) {
                $file_name = basename($_FILES[$input_name]['name']);
                $target_path = $uploads_dir . $file_name;

                // Move the uploaded file to the specified directory
                if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $target_path)) {
                    // Insert the document details into the UploadedDocuments table
                    $doc_stmt = $conn->prepare("
                        INSERT INTO WeddingUploadedDocuments (RequesterID, DocumentType, FilePath) 
                        VALUES (?, ?, ?)
                    ");
                    $doc_stmt->bind_param("iss", $RequesterID, $document_type, $target_path);
                    if (!$doc_stmt->execute()) {
                        throw new Exception("Failed to insert into WeddingUploadedDocuments: " . $doc_stmt->error);
                    }
                    $doc_stmt->close();
                } else {
                    throw new Exception("Failed to upload file $file_name. Please check directory permissions.");
                }
            } else {
                // Handle file upload errors if necessary
                if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] != UPLOAD_ERR_NO_FILE) {
                    throw new Exception("Error uploading $input_name: " . $_FILES[$input_name]['error']);
                }
            }
        }

        // Commit the transaction
        $conn->commit();

        // Clear form data from session
        unset($_SESSION['form_data']);

        // Set a success message and redirect
        $_SESSION['success_message'] = "Wedding request successfully submitted, please wait for the approval.";
        header("Location: wedding_request.php");
        exit();

    } catch (Exception $e) {
        // Rollback the transaction if anything goes wrong
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        $_SESSION['form_data'] = $_POST; // Preserve form data to refill the form
        header("Location: wedding_request.php");
        exit();

    } finally {
        // Close statements and connection
        if (isset($stmt)) $stmt->close();
        if (isset($stmt_wedding)) $stmt_wedding->close();
        if (isset($stmt_check_slot)) $stmt_check_slot->close();
        if (isset($stmt_update_slot)) $stmt_update_slot->close();
        if (isset($stmt_conflict)) $stmt_conflict->close();
        if (isset($stmt_insert_event)) $stmt_insert_event->close();
        $conn->close();
    }
}
?>
