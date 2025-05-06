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
// In a real application, you would have proper authentication
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

// Get thesis information if student has a team
$thesisInfo = null;
if ($studentData && $studentData['Team_ID']) {
    $sql = "SELECT td.Topic, td.Supervisor, f.Initial, u.Name as SupervisorName 
            FROM thesis_document td 
            JOIN thesis_team tt ON td.TeamID = tt.Team_ID 
            LEFT JOIN faculty f ON td.Supervisor = f.Initial 
            LEFT JOIN user u ON f.User_Email = u.Email 
            WHERE td.TeamID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $studentData['Team_ID']);
    $stmt->execute();
    $result = $stmt->get_result();
    $thesisInfo = $result->fetch_assoc();
}

// Get team members if student has a team
$teamMembers = [];
if ($studentData && $studentData['Team_ID']) {
    $sql = "SELECT s.Student_ID, u.Name, u.Email 
            FROM student s 
            JOIN user u ON s.User_Email = u.Email 
            WHERE s.Team_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $studentData['Team_ID']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $teamMembers[] = $row;
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
  <title>Student Dashboard - Thesis Management System</title>
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
      max-width: 1000px;
      padding: 20px;
    }

    .profile-info {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
    }

    .profile-item {
      margin-bottom: 15px;
      width: 48%;
    }

    .profile-label {
      font-weight: bold;
      color: #004080;
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      width: 100%;
      max-width: 1000px;
    }

    .dashboard-item {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 20px;
    }

    .dashboard-item h3 {
      color: #0055cc;
      margin-top: 0;
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 10px;
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
    }

    .btn:hover {
      background-color: #0044aa;
    }

    /* Team table styles */
    .team-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .team-table th {
      background-color: #e0f0ff;
      color: #004080;
      text-align: left;
      padding: 10px;
    }

    .team-table td {
      padding: 10px;
      border-bottom: 1px solid #e0e0e0;
    }

    .team-table tr:last-child td {
      border-bottom: none;
    }

    .team-table tr:hover {
      background-color: #f5f9ff;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="#">Team Search</a>
    <a href="supervisor.php">Supervisor</a>
    <a href="cosupervisor.php">Co-Supervisor</a>
    <a href="#">Schedule</a>
    <a href="#">Report Progress</a>
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
        <h2>Student Dashboard</h2>
        <p>Welcome, <?php echo displayValue($studentData['Name']); ?></p>
      </div>

      <div class="card">
        <h3>Profile Information</h3>
        <div class="profile-info">
          <div class="profile-item">
            <div class="profile-label">Name:</div>
            <div><?php echo displayValue($studentData['Name']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Email:</div>
            <div><?php echo displayValue($studentData['Email']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Student ID:</div>
            <div><?php echo displayValue($studentData['Student_ID']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Department:</div>
            <div><?php echo displayValue($studentData['department']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">CGPA:</div>
            <div><?php echo displayValue($studentData['CGPA']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Team ID:</div>
            <div><?php echo displayValue($studentData['Team_ID']); ?></div>
          </div>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="dashboard-item">
          <h3>My Thesis</h3>
          <?php if ($thesisInfo): ?>
            <p><strong>Title:</strong> <?php echo displayValue($thesisInfo['Topic']); ?></p>
            <p><strong>Supervisor:</strong> <?php echo displayValue($thesisInfo['SupervisorName']) . ' (' . displayValue($thesisInfo['Initial']) . ')'; ?></p>
          <?php else: ?>
            <p>No thesis information available. You may need to join a team first.</p>
          <?php endif; ?>
          <a href="#" class="btn">View Details</a>
        </div>

        <div class="dashboard-item">
          <h3>My Team</h3>
          <?php if (!empty($teamMembers)): ?>
            <table class="team-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Student ID</th>
                  <th>Email</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teamMembers as $member): ?>
                  <tr>
                    <td><?php echo displayValue($member['Name']); ?></td>
                    <td><?php echo displayValue($member['Student_ID']); ?></td>
                    <td><?php echo displayValue($member['Email']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p>You are not currently part of a team.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</body>
</html>