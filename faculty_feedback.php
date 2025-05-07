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

// Enable transaction support for better error handling
$conn->begin_transaction();

// Get current faculty email from session (assuming it's stored in session)
// In a real application, you would have proper authentication
$facultyEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'samiha@bracu.com'; // Default for testing

// Get faculty information
$sql = "SELECT f.Initial, f.Domain, f.Availability, f.Requirements, f.department, 
        u.Name, u.Email, u.Department as UserDepartment 
        FROM faculty f 
        JOIN user u ON f.User_Email = u.Email 
        WHERE f.User_Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $facultyEmail);
$stmt->execute();
$result = $stmt->get_result();
$facultyData = $result->fetch_assoc();

// Get team_id and thesis_id from URL parameters
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$thesis_id = isset($_GET['thesis_id']) ? intval($_GET['thesis_id']) : 0;

// If thesis_id is not provided, try to get it from the database
if ($team_id > 0 && $thesis_id == 0) {
    $sql = "SELECT ThesisID FROM thesis_document WHERE TeamID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $thesis_id = $row['ThesisID'];
    }
}

// Get team information
$teamInfo = null;
if ($team_id > 0) {
    $sql = "SELECT tt.Team_ID, td.Topic, td.Supervisor, td.ID as DocumentID, td.ThesisID 
            FROM thesis_team tt 
            LEFT JOIN thesis_document td ON tt.Team_ID = td.TeamID 
            WHERE tt.Team_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teamInfo = $result->fetch_assoc();
    
    // If we found a thesis ID in the team info, use it
    if ($teamInfo && isset($teamInfo['ThesisID']) && $teamInfo['ThesisID'] > 0) {
        $thesis_id = $teamInfo['ThesisID'];
    }
}

// Get existing feedback for this team
$existingFeedback = [];
if ($team_id > 0) {
    // Modified to retrieve feedback even if thesis_id is null
    $sql = "SELECT Topic, Feedback, faculty, Timestamp 
            FROM feedback 
            WHERE Team_ID = ? 
            ORDER BY Topic";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $existingFeedback[] = $row;
    }
}

// Process form submission
$message = '';
$error = '';
$feedbackSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    try {
        $topic = trim($_POST['topic']);
        $feedback_text = trim($_POST['feedback_text']);
        
        // Validate input
        if (empty($topic) || empty($feedback_text)) {
            throw new Exception("Both topic and feedback are required.");
        } else if ($team_id <= 0) {
            throw new Exception("Invalid team ID.");
        }
        
        // Use the document ID as the thesis ID if needed
        if (($thesis_id <= 0) && isset($teamInfo['DocumentID']) && $teamInfo['DocumentID'] > 0) {
            $thesis_id = $teamInfo['DocumentID'];
        }
        
        // If thesis_id is still 0, we need to create a thesis document first
        if ($thesis_id <= 0) {
            // First check if there's already a thesis document for this team
            $sql = "SELECT ID, ThesisID FROM thesis_document WHERE TeamID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Use existing thesis document
                $thesis_id = $row['ID'];  // Use ID as thesis_id for feedback
            } else {
                // Create a new thesis document entry
                $sql = "INSERT INTO thesis_document (TeamID, Topic, Supervisor) VALUES (?, 'Topic to be determined', ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $team_id, $facultyData['Initial']);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error creating thesis document: " . $stmt->error);
                }
                
                $thesis_id = $conn->insert_id;
                
                // Check if the thesis_id was created successfully
                if ($thesis_id <= 0) {
                    throw new Exception("Failed to create thesis document.");
                }
            }
        }
        
        // Now we can insert or update the feedback
        if ($thesis_id > 0) {
            // Include timestamp for new feedback
            $timestamp = date('Y-m-d H:i:s');
            
            // Check if feedback on this topic already exists
            $sql = "SELECT * FROM feedback WHERE Team_ID = ? AND Topic = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $team_id, $topic);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing feedback
                $sql = "UPDATE feedback SET Feedback = ?, faculty = ?, Timestamp = ? WHERE Team_ID = ? AND Topic = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssis", $feedback_text, $facultyData['Initial'], $timestamp, $team_id, $topic);
            } else {
                // Insert new feedback
                $sql = "INSERT INTO feedback (Topic, Team_ID, Thesis_ID, Feedback, faculty, Timestamp) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("siisss", $topic, $team_id, $thesis_id, $feedback_text, $facultyData['Initial'], $timestamp);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Error submitting feedback: " . $stmt->error);
            }
            
            // If we got here, everything worked
            $message = "Feedback submitted successfully!";
            $feedbackSubmitted = true;
            
            // Commit the transaction
            $conn->commit();
            
            // Refresh the existing feedback list
            $sql = "SELECT Topic, Feedback, faculty, Timestamp FROM feedback WHERE Team_ID = ? ORDER BY Topic";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingFeedback = [];
            while ($row = $result->fetch_assoc()) {
                $existingFeedback[] = $row;
            }
        } else {
            throw new Exception("Could not create or find a valid thesis document.");
        }
    } catch (Exception $e) {
        // Rollback the transaction
        $conn->rollback();
        $error = $e->getMessage();
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
  <title>Provide Feedback - Thesis Management System</title>
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

    .team-info {
      background-color: #f5f9ff;
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 20px;
    }

    .team-info p {
      margin: 5px 0;
    }

    .team-info-label {
      font-weight: bold;
      color: #004080;
    }

    .feedback-form {
      margin-top: 20px;
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

    .form-group input, .form-group textarea, .form-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-family: 'Segoe UI', sans-serif;
    }

    .form-group textarea {
      min-height: 150px;
      resize: vertical;
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
      background-color: #6c757d;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
    }

    .message {
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 15px;
    }

    .success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .existing-feedback {
      margin-top: 30px;
    }

    .existing-feedback h3 {
      color: #004080;
      border-bottom: 1px solid #e6f2ff;
      padding-bottom: 10px;
      margin-bottom: 15px;
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

    .feedback-date {
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

    .actions {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
    }

    .center-btn {
      display: flex;
      justify-content: center;
      margin-top: 20px;
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
    </div>

    <div class="content">
      <div class="feedback-container">
        <div class="feedback-header">Provide Feedback</div>
        
        <?php if ($teamInfo): ?>
          <?php if ($feedbackSubmitted): ?>
            <div class="message success">
              <p><?php echo $message; ?></p>
              <p>Your feedback has been successfully submitted to the team.</p>
            </div>
            
            <div class="center-btn">
              <a href="faculty_dash.php" class="btn">Return to Dashboard</a>
            </div>
          <?php else: ?>
            <div class="team-info">
              <p><span class="team-info-label">Team ID:</span> <?php echo displayValue($teamInfo['Team_ID']); ?></p>
              <p><span class="team-info-label">Thesis Topic:</span> <?php echo displayValue($teamInfo['Topic']); ?></p>
              <p><span class="team-info-label">Supervisor:</span> <?php echo displayValue($facultyData['Name']) . ' (' . displayValue($facultyData['Initial']) . ')'; ?></p>
            </div>
            
            <?php if (!empty($message)): ?>
              <div class="message success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
              <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form class="feedback-form" method="POST" action="">
              <div class="form-group">
                <label for="topic">Feedback Topic:</label>
                <input type="text" id="topic" name="topic" placeholder="e.g., Literature Review, Methodology, Data Analysis" required>
              </div>
              
              <div class="form-group">
                <label for="feedback_text">Feedback:</label>
                <textarea id="feedback_text" name="feedback_text" placeholder="Enter your detailed feedback here..." required></textarea>
              </div>
              
              <div class="actions">
                <a href="faculty_dash.php" class="btn btn-secondary">Back to Dashboard</a>
                <button type="submit" name="submit_feedback" class="btn">Submit Feedback</button>
              </div>
            </form>
            
            <?php if (!empty($existingFeedback)): ?>
              <div class="existing-feedback">
                <h3>Previous Feedback</h3>
                
                <?php foreach ($existingFeedback as $feedback): ?>
                  <div class="feedback-item">
                    <div class="feedback-meta">
                      <div class="feedback-topic"><?php echo htmlspecialchars($feedback['Topic']); ?></div>
                      <div class="feedback-date"><?php echo date('Y-m-d H:i', strtotime($feedback['Timestamp'])); ?></div>
                    </div>
                    <div class="feedback-text">
                      <?php echo nl2br(htmlspecialchars($feedback['Feedback'])); ?>
                    </div>
                    <div class="feedback-faculty">
                      - <?php echo displayValue($feedback['faculty']); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        <?php else: ?>
          <div class="message error">
            <p>Invalid team ID or team not found.</p>
            <div style="margin-top: 15px;">
              <a href="faculty_dash.php" class="btn">Back to Dashboard</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</body>
</html>