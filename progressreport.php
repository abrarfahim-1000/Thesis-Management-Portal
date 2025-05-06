<?php
// Start session
session_start();

// Database connection
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'thesis_helper';
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user name - expect it to be passed from student_dashboard
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "Student";

$submissionMessage = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team_id = $_POST['team-id'];
    $status = $_POST['status'];
    $comment = $_POST['comment'];
    $faculty_initial = $_POST['faculty-initial'];
    $completion_date = $_POST['completion-date'];

    // Handle file upload
    if (isset($_FILES['file-upload']) && $_FILES['file-upload']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file-upload']['tmp_name'];
        $file_name = $_FILES['file-upload']['name'];
        $file_size = $_FILES['file-upload']['size'];
        $file_type = $_FILES['file-upload']['type'];
        
        // Read file as binary data
        $file_data = file_get_contents($file_tmp);
        
        if ($file_data === false) {
            $submissionMessage = "❌ Error reading file data.";
        }
    } else {
        // Provide more detailed error message based on the error code
        $error_message = "File upload failed: ";
        if (isset($_FILES['file-upload'])) {
            switch ($_FILES['file-upload']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error_message .= "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message .= "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message .= "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message .= "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message .= "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message .= "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message .= "A PHP extension stopped the file upload.";
                    break;
                default:
                    $error_message .= "Unknown error.";
            }
        } else {
            $error_message .= "No file uploaded.";
        }
        $submissionMessage = "❌ " . $error_message;
    }

    if (empty($submissionMessage)) {
        // Check if Thesis_Team_ID exists in thesis_team table first
        $checkQuery = "SELECT Team_ID FROM thesis_team WHERE Team_ID = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $team_id);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // Check if a report with this team_id and completion_date already exists (composite primary key)
            $duplicateCheck = "SELECT * FROM Progress_Report WHERE Thesis_Team_ID = ? AND Completion_date = ?";
            $dupStmt = $conn->prepare($duplicateCheck);
            $dupStmt->bind_param("is", $team_id, $completion_date);
            $dupStmt->execute();
            $dupStmt->store_result();
            
            if ($dupStmt->num_rows > 0) {
                // Duplicate entry found
                $submissionMessage = "❌ A report for this team on this date already exists.";
                $dupStmt->close();
            } else {
                // No duplicate, proceed with insert
                // Also store file metadata
                $stmt = $conn->prepare("INSERT INTO Progress_Report (Thesis_Team_ID, File, Status, Comment, Initial, Completion_date, file_name, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                // Add file metadata parameters
                $stmt->bind_param("issssssis", $team_id, $file_data, $status, $comment, $faculty_initial, $completion_date, $file_name, $file_type, $file_size);
                
                if ($stmt->execute()) {
                    $submissionMessage = "✅ Progress report submitted successfully.";
                } else {
                    // Check if the error is due to duplicate entry (just in case the check above didn't catch it)
                    if ($conn->errno == 1062) {
                        $submissionMessage = "❌ A report for this team on this date already exists.";
                    } else {
                        $submissionMessage = "❌ Error: " . $stmt->error;
                    }
                }
                $stmt->close();
                $dupStmt->close();
            }
        } else {
            // Thesis_Team_ID doesn't exist, show error
            $submissionMessage = "❌ Error: Invalid Thesis_Team_ID.";
        }
        
        $checkStmt->close();
    }
}

$conn->close();
?>

<!-- Rest of your HTML code remains the same -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Progress Report - Thesis Management System</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to bottom, #e0f0ff, #b3d9ff);
      color: #003366;
      display: flex;
      height: 100vh;
    }

    .sidebar {
      width: 200px;
      background-color: #d0e7f9;
      box-shadow: 2px 0 5px rgba(0,0,0,0.1);
      display: flex;
      flex-direction: column;
      padding: 20px 10px;
    }

    .sidebar a {
      text-decoration: none;
      color: #003366;
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 6px;
      transition: background 0.3s;
    }

    .sidebar a:hover {
      background-color: #c0ddf0;
    }

    .sidebar a.active {
      background-color: #0055cc;
      color: white;
    }

    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }

    .topbar {
      background-color: #c0ddf0;
      padding: 15px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .topbar h1 {
      font-size: 20px;
      color: #002244;
      margin: 0;
    }

    .content {
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    .report-container {
      width: 100%;
      max-width: 800px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 30px;  /* Increased padding from 20px to 30px */
    }

    .report-header {
      font-size: 24px;
      font-weight: bold;
      color: #004080;
      margin-bottom: 20px;
      border-bottom: 1px solid #e6f2ff;
      padding-bottom: 10px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #004080;
    }

    .form-control {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-family: inherit;
      font-size: 14px;
    }

    select.form-control {
      height: 38px;
    }

    textarea.form-control {
      min-height: 100px;
    }

    .file-input {
      margin-top: 5px;
    }

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      margin-top: 10px;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    .message {
      margin: 15px 0;
      padding: 10px;
      border-radius: 4px;
      font-weight: bold;
      width: 100%;
      max-width: 800px;
      text-align: center;
    }

    .message.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .message.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="student_dash.php">Dashboard</a>
    <a href="team.php">Team</a>
    <a href="supervisor.php">Supervisor</a>
    <a href="progressreport.php" class="active">Report Progress</a>
    <a href="#">Submit Thesis</a>
    <a href="#">Feedback</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
      <div class="user-info"><?php echo $userName; ?></div>
    </div>

    <div class="date-display">
      <?php echo date('Y-m-d H:i:s T'); ?>
    </div>

    <div class="content">
      <?php if (!empty($submissionMessage)): ?>
        <div class="message <?php echo (strpos($submissionMessage, '✅') !== false) ? 'success' : 'error'; ?>">
          <?php echo $submissionMessage; ?>
        </div>
      <?php endif; ?>

      <div class="report-container">
        <div class="report-header">Submit Progress Report</div>
        
        <form method="post" enctype="multipart/form-data">
          <div class="form-group">
            <label for="team-id">Thesis Team ID</label>
            <input type="number" class="form-control" id="team-id" name="team-id" required>
          </div>
          
          <div class="form-group">
            <label for="file-upload">Report File</label>
            <input type="file" class="file-input" id="file-upload" name="file-upload" required>
          </div>
          
          <div class="form-group">
            <label for="status">Status</label>
            <select class="form-control" id="status" name="status" required>
              <option value="">Select status</option>
              <option value="Draft">Draft</option>
              <option value="Submitted">Submitted</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="comment">Comment</label>
            <textarea class="form-control" id="comment" name="comment"></textarea>
          </div>
          
          <div class="form-group">
            <label for="faculty-initial">Faculty Initial</label>
            <input type="text" class="form-control" id="faculty-initial" name="faculty-initial">
          </div>
          
          <div class="form-group">
            <label for="completion-date">Completion Date</label>
            <input type="date" class="form-control" id="completion-date" name="completion-date" required>
          </div>
          
          <button type="submit" class="btn">Submit Report</button>
        </form>
      </div>
    </div>
  </div>

</body>
</html>
