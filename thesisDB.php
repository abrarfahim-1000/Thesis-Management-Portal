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

// Get all final theses
$finalTheses = [];
$sql = "SELECT td.ID, td.ThesisID, td.TeamID, td.Topic, td.Supervisor, f.Domain, u.Name as SupervisorName 
        FROM thesis_document td 
        LEFT JOIN faculty f ON td.Supervisor = f.Initial 
        LEFT JOIN user u ON f.User_Email = u.Email 
        WHERE td.category_status = 'final'
        ORDER BY td.ID DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $finalTheses[] = $row;
    }
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
  <title>Thesis Database - Thesis Management System</title>
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

    /* Content area */
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
      width: 100%;
      max-width: 1000px;
      margin: 0 auto;
    }

    .header-title {
      text-align: center;
      margin-bottom: 10px;
      width: 100%;
    }

    .card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      width: 100%;
      padding: 20px;
    }

    .thesis-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .thesis-table th {
      background-color: #e0f0ff;
      color: #004080;
      text-align: left;
      padding: 10px;
    }

    .thesis-table td {
      padding: 10px;
      border-bottom: 1px solid #e0e0e0;
    }

    .thesis-table tr:last-child td {
      border-bottom: none;
    }

    .thesis-table tr:hover {
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
      margin-right: 5px;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    .empty-message {
      text-align: center;
      padding: 20px;
      color: #666;
    }
  </style>
</head>
<body>
  <!-- Sidebar for Faculty -->
  <?php if (isset($_SESSION['user_email']) && strpos($_SESSION['user_email'], '@bracu.com') !== false): ?>
  <div class="sidebar">
    <a href="faculty_dash.php">Dashboard</a>
    <a href="applyAsSupervisor.php">Apply as Supervisor</a>
    <a href="applyAsCosupervisor.php">Apply as Co-Supervisor</a>
    <a href="progress_fac_view.php">Reports</a>
    <a href="get_schedules.php">Schedule</a>
    <a href="thesisDB.php" class="active">Thesis Database</a>
  </div>
  <!-- Sidebar for Students -->
  <?php else: ?>
  <div class="sidebar">
    <a href="student_dash.php">Dashboard</a>
    <a href="teamsearch.php">Team Search</a>
    <a href="supervisor.php">Supervisor</a>
    <a href="cosupervisor.php">Co-Supervisor</a>
    <a href="get_schedule2.php">Schedule</a>
    <a href="progressreport.php">Report Progress</a>
    <a href="submit_thesis.php">Submit Thesis</a>
    <a href="student_feedback.php">Feedback</a>
    <a href="thesisdb.php" class="active">ThesisDB</a>
  </div>
  <?php endif; ?>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
    </div>

    <div class="date-display">
      <?php echo date('Y-m-d H:i:s'); ?> UTC
    </div>

    <div class="content">
      <div class="header-title">
        <h2>Thesis Database</h2>
        <p>Browse completed theses from all departments</p>
      </div>

      <div class="card">
        <h3>Final Theses</h3>
        <?php if (!empty($finalTheses)): ?>
          <table class="thesis-table">
            <thead>
              <tr>
                <th>Thesis Title</th>
                <th>Domain</th>
                <th>Supervisor</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($finalTheses as $thesis): ?>
                <tr>
                  <td><?php echo htmlspecialchars(displayValue($thesis['Topic'])); ?></td>
                  <td><?php echo htmlspecialchars(displayValue($thesis['Domain'])); ?></td>
                  <td><?php echo htmlspecialchars(displayValue($thesis['SupervisorName'])) . ' (' . htmlspecialchars(displayValue($thesis['Supervisor'])) . ')'; ?></td>
                  <td>
                    <a href="visitor_view_document.php?team_id=<?php echo $thesis['TeamID']; ?>" class="btn">View Document</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty-message">
            <p>No final theses are currently available in the database.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
