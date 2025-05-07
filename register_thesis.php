<?php
// Start session for user authentication
session_start();

// Database connection
$servername = "localhost";
$username = "root"; // Change as needed
$password = ""; // Change as needed
$dbname = "thesis_helper";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current user email from session (assuming it's stored in session)
$userEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'abrar1@student.com'; // Default for testing

// Get student information
$sql = "SELECT s.Student_ID, u.Name, u.Email, s.CGPA, s.Team_ID, s.department 
        FROM student s 
        JOIN user u ON s.User_Email = u.Email 
        WHERE s.User_Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();

// Initialize variables
$error = '';
$success = '';
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : (isset($studentData['Team_ID']) ? $studentData['Team_ID'] : 0);
$faculty_list = [];

// Get list of available faculty
$sql = "SELECT f.Initial, u.Name, f.Domain 
        FROM faculty f 
        JOIN user u ON f.User_Email = u.Email 
        WHERE f.Availability = 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faculty_list[] = $row;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $semester = $conn->real_escape_string($_POST['semester']);
    $domain = $conn->real_escape_string($_POST['domain']);
    $supervisor = $conn->real_escape_string($_POST['supervisor']);
    $topic = $conn->real_escape_string($_POST['topic']);

    // Check if student already has a team
    if ($studentData['Team_ID']) {
        $team_id = $studentData['Team_ID'];
    } else {
        // Create a new team
        $sql = "INSERT INTO thesis_team (Initial) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $supervisor);
        
        if ($stmt->execute()) {
            $team_id = $conn->insert_id;
            
            // Update student's team ID
            $sql = "UPDATE student SET Team_ID = ? WHERE User_Email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $team_id, $userEmail);
            $stmt->execute();
        } else {
            $error = "Error creating team: " . $conn->error;
        }
    }

    if (!$error) {
        // Check if this team already has a thesis registered
        $sql = "SELECT * FROM thesis_document WHERE TeamID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "This team already has a thesis registered.";
        } else {
            // Insert thesis document
            $sql = "INSERT INTO thesis_document (TeamID, Topic, Semester, Supervisor) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $team_id, $topic, $semester, $supervisor);
            
            if ($stmt->execute()) {
                // Update thesis team with supervisor
                $sql = "UPDATE thesis_team SET Initial = ? WHERE Team_ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $supervisor, $team_id);
                $stmt->execute();
                
                $success = "Thesis registration successful!";
            } else {
                $error = "Error registering thesis: " . $conn->error;
            }
        }
    }
    $stmt->close();
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register Thesis - Thesis Management System</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to bottom, #e0f0ff, #b3d9ff);
      color: #003366;
      display: flex;
      height: 100vh;
    }

    /* Sidebar */
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

    /* Main section */
    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }

    /* Topbar */
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

    /* Content area */
    .content {
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    .header-title {
      text-align: center;
      margin-bottom: 10px;
    }

    .card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 800px;
      padding: 30px;
    }

    .card h2 {
      color: #0055cc;
      margin-top: 0;
      margin-bottom: 30px;
      text-align: center;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: #004080;
    }

    input[type="text"],
    input[type="number"],
    select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 16px;
    }

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      display: inline-block;
      margin-top: 10px;
      text-decoration: none;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    .btn-return {
      background-color: #6c757d;
    }

    .btn-return:hover {
      background-color: #5a6268;
    }

    .error {
      color: #dc3545;
      margin-bottom: 20px;
      padding: 10px;
      background-color: #f8d7da;
      border-radius: 4px;
    }

    .success {
      color: #28a745;
      margin-bottom: 20px;
      padding: 10px;
      background-color: #d4edda;
      border-radius: 4px;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    .button-group {
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="student_dash.php">Dashboard</a>
    <a href="teamsearch.php">Team Search</a>
    <a href="supervisor.php">Supervisor</a>
    <a href="cosupervisor.php">Co-Supervisor</a>
    <a href="#">Schedule</a>
    <a href="progressreport.php">Report Progress</a>
    <a href="#">Plagiarism Checker</a>
    <a href="#">Panelists</a>
    <a href="#">Submit Thesis</a>
    <a href="#">Feedback</a>
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
      <div class="header-title">
        <p>Welcome, <?php echo displayValue($studentData['Name']); ?></p>
      </div>

      <div class="card">
        <h2>Register Thesis</h2>
        
        <?php if ($error): ?>
          <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
          <div class="success"><?php echo $success; ?></div>
          <div style="text-align: center;">
            <a href="student_dash.php" class="btn btn-return">Return to Dashboard</a>
          </div>
        <?php else: ?>
          <form method="post" action="register_thesis.php">
            <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
            
            <div class="form-group">
              <label for="topic">Thesis Topic</label>
              <input type="text" id="topic" name="topic" required>
            </div>
            
            <div class="form-group">
              <label for="semester">Starting Semester</label>
              <select id="semester" name="semester" required>
                <option value="">Select Semester</option>
                <option value="Spring">Spring</option>
                <option value="Summer">Summer</option>
                <option value="Fall">Fall</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="domain">Domain</label>
              <input type="text" id="domain" name="domain" required>
            </div>
            
            <div class="form-group">
              <label for="supervisor">Supervisor</label>
              <select id="supervisor" name="supervisor" required>
                <option value="">Select Supervisor</option>
                <?php foreach ($faculty_list as $faculty): ?>
                  <option value="<?php echo $faculty['Initial']; ?>">
                    <?php echo $faculty['Name'] . ' (' . $faculty['Initial'] . ') - ' . $faculty['Domain']; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="button-group">
              <a href="student_dash.php" class="btn btn-return">Cancel</a>
              <button type="submit" class="btn">Register Thesis</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

</body>
</html>
