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

// Get team ID from URL parameter
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Get team information
$teamInfo = null;
$teamMembers = [];

if ($team_id > 0) {
    // Get team information and thesis topic
    $sql = "SELECT tt.Team_ID, td.Topic, tt.Initial as SupervisorInitial, tt.cinitial as CoSupervisorInitial,
            f.User_Email as SupervisorEmail, u.Name as SupervisorName,
            f2.User_Email as CoSupervisorEmail, u2.Name as CoSupervisorName
            FROM thesis_team tt 
            LEFT JOIN thesis_document td ON tt.Team_ID = td.TeamID
            LEFT JOIN faculty f ON tt.Initial = f.Initial
            LEFT JOIN user u ON f.User_Email = u.Email
            LEFT JOIN faculty f2 ON tt.cinitial = f2.Initial
            LEFT JOIN user u2 ON f2.User_Email = u2.Email
            WHERE tt.Team_ID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teamInfo = $result->fetch_assoc();
    $stmt->close();
    
    // Get team members
    $sql = "SELECT s.Student_ID, s.CGPA, s.department, u.Name, u.Email 
            FROM student s
            JOIN user u ON s.User_Email = u.Email
            WHERE s.Team_ID = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $teamMembers[] = $row;
    }
    $stmt->close();
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Team Details - Thesis Management System</title>
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

    .team-info {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .team-item {
      margin-bottom: 15px;
      width: 48%;
    }

    .team-label {
      font-weight: bold;
      color: #004080;
    }

    .members-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .members-table th {
      background-color: #e0f0ff;
      color: #004080;
      text-align: left;
      padding: 10px;
    }

    .members-table td {
      padding: 10px;
      border-bottom: 1px solid #e0e0e0;
    }

    .members-table tr:last-child td {
      border-bottom: none;
    }

    .members-table tr:hover {
      background-color: #f5f9ff;
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

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    .no-team {
      text-align: center;
      color: #004080;
      font-size: 18px;
      margin: 40px 0;
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
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
    </div>

    <div class="content">
      <div class="header-title">
        <h2>Team Details</h2>
      </div>

      <?php if ($teamInfo): ?>
      <div class="card">
        <h3>Team Information</h3>
        <div class="team-info">
          <div class="team-item">
            <div class="team-label">Team ID:</div>
            <div><?php echo displayValue($teamInfo['Team_ID']); ?></div>
          </div>
          <div class="team-item">
            <div class="team-label">Thesis Topic:</div>
            <div><?php echo displayValue($teamInfo['Topic']); ?></div>
          </div>
          <div class="team-item">
            <div class="team-label">Supervisor:</div>
            <div><?php echo displayValue($teamInfo['SupervisorName']) . ' (' . displayValue($teamInfo['SupervisorInitial']) . ')'; ?></div>
          </div>
          <div class="team-item">
            <div class="team-label">Supervisor Email:</div>
            <div><?php echo displayValue($teamInfo['SupervisorEmail']); ?></div>
          </div>
          <?php if ($teamInfo['CoSupervisorInitial']): ?>
          <div class="team-item">
            <div class="team-label">Co-Supervisor:</div>
            <div><?php echo displayValue($teamInfo['CoSupervisorName']) . ' (' . displayValue($teamInfo['CoSupervisorInitial']) . ')'; ?></div>
          </div>
          <div class="team-item">
            <div class="team-label">Co-Supervisor Email:</div>
            <div><?php echo displayValue($teamInfo['CoSupervisorEmail']); ?></div>
          </div>
          <?php endif; ?>
        </div>

        <h3>Team Members</h3>
        <?php if (!empty($teamMembers)): ?>
          <table class="members-table">
            <thead>
              <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Department</th>
                <th>CGPA</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($teamMembers as $member): ?>
                <tr>
                  <td><?php echo $member['Student_ID']; ?></td>
                  <td><?php echo displayValue($member['Name']); ?></td>
                  <td><?php echo displayValue($member['Email']); ?></td>
                  <td><?php echo displayValue($member['department']); ?></td>
                  <td><?php echo displayValue($member['CGPA']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>No team members found for this team.</p>
        <?php endif; ?>

        <a href="faculty_dash.php" class="btn">Back to Dashboard</a>
      </div>
      <?php else: ?>
        <div class="card">
          <p class="no-team">Team not found or invalid team ID.</p>
          <div style="text-align: center;">
            <a href="student_dash.php" class="btn">Back to Dashboard</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</body>
</html>
