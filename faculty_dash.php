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

// Get teams under supervision
$teamsUnderSupervision = [];
if ($facultyData) {
    $sql = "SELECT tt.Team_ID, td.Topic, td.ThesisID 
            FROM thesis_team tt 
            LEFT JOIN thesis_document td ON tt.Team_ID = td.TeamID 
            WHERE tt.Initial = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $facultyData['Initial']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // Count team members
        $memberCountSql = "SELECT COUNT(*) as memberCount FROM student WHERE Team_ID = ?";
        $memberStmt = $conn->prepare($memberCountSql);
        $memberStmt->bind_param("i", $row['Team_ID']);
        $memberStmt->execute();
        $memberResult = $memberStmt->get_result();
        $memberData = $memberResult->fetch_assoc();
        
        $row['memberCount'] = $memberData['memberCount'];
        $teamsUnderSupervision[] = $row;
        $memberStmt->close();
    }
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

// Function to display availability status
function displayAvailability($value) {
    if ($value === null) return 'Not Available';
    return ($value == 1) ? 'Accepting' : 'Not Accepting';
}

// Close the database connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Faculty Dashboard - Thesis Management System</title>
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
      margin-right: 5px;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    /* Team table styles */
    .teams-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .teams-table th {
      background-color: #e0f0ff;
      color: #004080;
      text-align: left;
      padding: 10px;
    }

    .teams-table td {
      padding: 10px;
      border-bottom: 1px solid #e0e0e0;
    }

    .teams-table tr:last-child td {
      border-bottom: none;
    }

    .teams-table tr:hover {
      background-color: #f5f9ff;
    }

    .team-id {
      color: #0055cc;
      text-decoration: underline;
      cursor: pointer;
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
    <a href="applyAsSupervisor.php">Apply as Supervisor</a>
    <a href="applyAsCosup.php">Apply as Co-Supervisor</a>
>>>>>>> db1176c0d510f82a112386f12661f02a7d8aac8e
    <a href="#">Schedule</a>
    <a href="#">Plagiarism Checker</a>
    <a href="#">Panelists</a>
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
        <h2>Faculty Dashboard</h2>
        <p>Welcome, <?php echo displayValue($facultyData['Name']); ?></p>
      </div>

      <div class="card">
        <h3>Faculty Information</h3>
        <div class="profile-info">
          <div class="profile-item">
            <div class="profile-label">Name:</div>
            <div><?php echo displayValue($facultyData['Name']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Email:</div>
            <div><?php echo displayValue($facultyData['Email']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Department:</div>
            <div><?php echo displayValue($facultyData['department'] ? $facultyData['department'] : $facultyData['UserDepartment']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Status:</div>
            <div><?php echo displayAvailability($facultyData['Availability']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Initial:</div>
            <div><?php echo displayValue($facultyData['Initial']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Domain:</div>
            <div><?php echo displayValue($facultyData['Domain']); ?></div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Requirements:</div>
            <div><?php echo displayValue($facultyData['Requirements']); ?></div>
          </div>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="dashboard-item">
          <h3>Teams Under Supervision</h3>
          <?php if (!empty($teamsUnderSupervision)): ?>
            <table class="teams-table">
              <thead>
                <tr>
                  <th>Team ID</th>
                  <th>Thesis Topic</th>
                  <th>Members</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teamsUnderSupervision as $team): ?>
                  <tr>
                    <td><a href="#" class="team-id"><?php echo $team['Team_ID']; ?></a></td>
                    <td><?php echo displayValue($team['Topic']); ?></td>
                    <td><?php echo $team['memberCount']; ?></td>
                    <td>
                      <a href="feedback.php?team_id=<?php echo $team['Team_ID']; ?>&thesis_id=<?php echo $team['ThesisID']; ?>" class="btn">Send Feedback</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p>You do not have any teams under your supervision.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
