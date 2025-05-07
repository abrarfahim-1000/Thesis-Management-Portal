<?php
// Start session for user authentication
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "thesis_helper";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current faculty email from session (assuming it's stored in session)
// In a real application, you would have proper authentication
$facultyEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'samiha@bracu.com'; // Default for testing

// Get faculty information
$sql = "SELECT f.Initial, u.Name 
        FROM faculty f 
        JOIN user u ON f.User_Email = u.Email 
        WHERE f.User_Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $facultyEmail);
$stmt->execute();
$result = $stmt->get_result();
$facultyData = $result->fetch_assoc();

// Get team_id from URL parameter
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Check if faculty is supervising this team
$isSupervising = false;
if ($facultyData && $team_id > 0) {
    $sql = "SELECT 1 FROM thesis_team WHERE Team_ID = ? AND Initial = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $team_id, $facultyData['Initial']);
    $stmt->execute();
    $result = $stmt->get_result();
    $isSupervising = ($result->num_rows > 0);
}

// If faculty is not supervising this team, redirect to dashboard
if (!$isSupervising) {
    header("Location: faculty_dash.php");
    exit;
}

// Get the latest document submitted by the team
$documentData = null;
if ($team_id > 0) {
    $sql = "SELECT td.*, 
            (SELECT GROUP_CONCAT(s.Student_ID, ': ', u.Name) 
             FROM student s 
             JOIN user u ON s.User_Email = u.Email 
             WHERE s.Team_ID = td.TeamID) as TeamMembers
            FROM thesis_document td 
            WHERE td.TeamID = ? 
            ORDER BY td.ID DESC 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documentData = $result->fetch_assoc();
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

// Close the database connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Document - Thesis Management System</title>
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

    .search-box {
      display: flex;
      align-items: center;
    }

    .search-box input {
      padding: 6px 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      outline: none;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    .content {
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    .document-container {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 1000px;
      padding: 20px;
    }

    .document-header {
      font-size: 24px;
      font-weight: bold;
      color: #004080;
      padding-bottom: 15px;
      border-bottom: 1px solid #e6f2ff;
      margin-bottom: 20px;
    }

    .document-info {
      background-color: #f5f9ff;
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 20px;
    }

    .document-info p {
      margin: 5px 0;
    }

    .info-label {
      font-weight: bold;
      color: #004080;
    }

    .document-content {
      margin-top: 20px;
      border: 1px solid #e0e0e0;
      border-radius: 6px;
      padding: 15px;
      min-height: 300px;
    }

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      display: inline-block;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    .btn-secondary {
      background-color: #28a745;
    }

    .btn-secondary:hover {
      background-color: #218838;
    }

    .message {
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
    }

    .error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .actions {
      display: flex;
      justify-content: center;
      margin-top: 20px;
    }

    .center-btn {
      display: flex;
      justify-content: center;
      margin-top: 20px;
    }

    iframe {
      width: 100%;
      height: 600px;
      border: 1px solid #ddd;
      border-radius: 4px;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="applyAsSupervisor.php">Apply as Supervisor</a>
    <a href="applyAsCosupervisor.php">Apply as Co-Supervisor</a>
    <a href="get_schedules.php">Schedule</a>
    <a href="#">Plagiarism Checker</a>
    <a href="#">Panelists</a>
    <a href="faculty_dash.php">Dashboard</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
      <div class="search-box">
        <label for="search" style="margin-right: 8px;">Search</label>
        <input type="text" id="search" placeholder="Search...">
      </div>
    </div>

    <div class="date-display">
      <?php echo date('Y-m-d H:i:s'); ?> UTC
    </div>

    <div class="content">
      <div class="document-container">
        <div class="document-header">View Submitted Document</div>
        
        <?php if ($documentData): ?>
          <div class="document-info">
            <p><span class="info-label">Team ID:</span> <?php echo $team_id; ?></p>
            <p><span class="info-label">Thesis Topic:</span> <?php echo displayValue($documentData['Topic']); ?></p>
            <p><span class="info-label">Team Members:</span> <?php echo displayValue($documentData['TeamMembers']); ?></p>
            <p><span class="info-label">Semester:</span> <?php echo displayValue($documentData['Semester']); ?></p>
            <p><span class="info-label">File Name:</span> <?php echo displayValue($documentData['content_name']); ?></p>
            <p><span class="info-label">Supervisor:</span> <?php echo displayValue($documentData['Supervisor']); ?></p>
            <?php if($documentData['Co_Supervisor']): ?>
              <p><span class="info-label">Co-Supervisor:</span> <?php echo displayValue($documentData['Co_Supervisor']); ?></p>
            <?php endif; ?>
          </div>

          <?php if ($documentData['content']): ?>
            <div class="document-content">
              <?php 
                $fileType = strtolower($documentData['content_type']);
                
                // Check if it's a PDF file
                if (strpos($fileType, 'pdf') !== false) {
                  // For PDF files, embed using an iframe
                  echo '<iframe src="data:application/pdf;base64,' . base64_encode($documentData['content']) . '"></iframe>';
                } elseif (strpos($fileType, 'word') !== false || strpos($fileType, 'document') !== false) {
                  // For Word documents, display a message and provide a download link
                  echo '<p>This is a Word document and cannot be displayed directly. Please download to view.</p>';
                  echo '<a href="download_document.php?team_id=' . $team_id . '" class="btn btn-secondary">Download Document</a>';
                } elseif (strpos($fileType, 'text') !== false) {
                  // For text files, display the content
                  echo '<pre>' . htmlspecialchars($documentData['content']) . '</pre>';
                } else {
                  // For other file types, provide a download link
                  echo '<p>This file type cannot be displayed directly. Please download to view.</p>';
                  echo '<a href="download_document.php?team_id=' . $team_id . '" class="btn btn-secondary">Download Document</a>';
                }
              ?>
            </div>
          <?php else: ?>
            <div class="message error">
              <p>The document file is not available.</p>
            </div>
          <?php endif; ?>
          
          <div class="actions">
            <a href="faculty_dash.php" class="btn">Back to Dashboard</a>
          </div>
        <?php else: ?>
          <div class="message error">
            <p>No document has been submitted by this team yet.</p>
            <div class="center-btn">
              <a href="faculty_dash.php" class="btn">Back to Dashboard</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</body>
</html>