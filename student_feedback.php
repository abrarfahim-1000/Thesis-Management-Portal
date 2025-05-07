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

// Get current user email from session (assuming it's stored in session)
// In a real application, you would have proper authentication
$userEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'abrar1@student.com'; // Default for testing

// Get team_id from URL parameter if available
$teamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : null;

// Get student information including team ID
$sql = "SELECT s.Student_ID, s.Team_ID, u.Name, u.Email 
        FROM student s 
        JOIN user u ON s.User_Email = u.Email 
        WHERE s.User_Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();

// Use team_id from URL if provided, otherwise use student's team_id
if ($teamId === null) {
    $teamId = $studentData['Team_ID'];
}

// Get thesis ID for the team
$thesisId = null;
if ($teamId) {
    $sql = "SELECT ThesisID FROM thesis_document WHERE TeamID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $thesisId = $row['ThesisID'];
    }
}

// Get feedback for the team
$feedbackItems = [];
if ($teamId && $thesisId) {
    $sql = "SELECT f.Topic, f.Feedback, f.Team_ID, f.Thesis_ID, f.faculty, 
            u.Name as FacultyName
            FROM feedback f
            LEFT JOIN faculty fac ON f.faculty = fac.Initial
            LEFT JOIN user u ON fac.User_Email = u.Email
            WHERE f.Team_ID = ? AND f.Thesis_ID = ?
            ORDER BY f.Topic";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teamId, $thesisId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $feedbackItems[] = $row;
    }
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
  <title>Feedback - Thesis Management System</title>
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

    .user-info {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
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
      overflow-y: auto;
    }

    .feedback-container {
      width: 100%;
      max-width: 800px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      overflow: hidden;
      padding: 20px;
    }

    .feedback-header {
      font-size: 24px;
      font-weight: bold;
      color: #004080;
      padding-bottom: 15px;
      border-bottom: 1px solid #e6f2ff;
      margin-bottom: 20px;
    }

    .feedback-item {
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 6px;
      background-color: #f5f9ff;
      border-left: 4px solid #0055cc;
    }

    .feedback-meta {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
    }

    .feedback-topic {
      font-weight: bold;
      color: #004080;
      font-size: 18px;
    }

    .feedback-ids {
      color: #666;
      font-size: 14px;
    }

    .feedback-text {
      line-height: 1.6;
      color: #333;
    }

    .feedback-faculty {
      margin-top: 10px;
      font-style: italic;
      color: #0055cc;
      text-align: right;
      font-size: 14px;
    }

    .no-feedback {
      padding: 30px;
      text-align: center;
      color: #666;
      font-size: 18px;
    }

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      display: inline-block;
      margin-top: 20px;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    .back-container {
      width: 100%;
      max-width: 800px;
      text-align: center;
      margin-top: 10px;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="student_dash.php">Dashboard</a>
    <a href="teamsearch.php">Team Search</a>
    <a href="supervisor.php">Supervisor</a>
    <a href="cosupervisor.php">Co-Supervisor</a>
    <a href="get_schedule2.php">Schedule</a>
    <a href="progressreport.php">Report Progress</a>
    <a href="#">Plagiarism Checker</a>
    <a href="#">Panelists</a>
    <a href="submit_thesis.php">Submit Thesis</a>
    <a href="student_feedback.php?team_id=<?php echo $teamId; ?>" class="active">Feedback</a>
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
      <div class="feedback-container">
        <div class="feedback-header">Thesis Feedback</div>
        
        <?php if (!empty($feedbackItems)): ?>
          <?php foreach ($feedbackItems as $feedback): ?>
            <div class="feedback-item">
              <div class="feedback-meta">
                <div class="feedback-topic"><?php echo htmlspecialchars($feedback['Topic']); ?></div>
                <div class="feedback-ids">Team ID: <?php echo htmlspecialchars($feedback['Team_ID']); ?> | Thesis ID: <?php echo htmlspecialchars($feedback['Thesis_ID']); ?></div>
              </div>
              <div class="feedback-text">
                <?php echo nl2br(htmlspecialchars($feedback['Feedback'])); ?>
              </div>
              <div class="feedback-faculty">
                - <?php echo displayValue($feedback['FacultyName']) . ' (' . displayValue($feedback['faculty']) . ')'; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-feedback">
            <p>No feedback has been provided by faculty yet.</p>
            <?php if (!$teamId): ?>
              <p>You need to join a team first to receive feedback.</p>
            <?php elseif (!$thesisId): ?>
              <p>Your team needs to submit a thesis document first.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      
      <div class="back-container">
        <a href="student_dash.php" class="btn">Back to Dashboard</a>
      </div>
    </div>
  </div>

</body>
</html>