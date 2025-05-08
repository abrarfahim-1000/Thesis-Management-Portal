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

$facultyName = isset($facultyData['Name']) ? $facultyData['Name'] : "Faculty";
$facultyInitial = isset($facultyData['Initial']) ? $facultyData['Initial'] : "";

// Get teams under supervision
$teamsUnderSupervision = [];
if ($facultyInitial) {
    $sql = "SELECT Team_ID FROM thesis_team WHERE Initial = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $facultyInitial);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $teamsUnderSupervision[] = $row['Team_ID'];
    }
}

// Get progress reports for teams under supervision
$progressReports = [];
if (!empty($teamsUnderSupervision)) {
    $placeholders = implode(',', array_fill(0, count($teamsUnderSupervision), '?'));
    
    $sql = "SELECT pr.*, tt.Team_ID, tt.Initial, 
           (SELECT GROUP_CONCAT(s.Student_ID) FROM student s WHERE s.Team_ID = tt.Team_ID) AS student_ids,
           (SELECT GROUP_CONCAT(u.Name) FROM student s JOIN user u ON s.User_Email = u.Email WHERE s.Team_ID = tt.Team_ID) AS student_names
           FROM Progress_Report pr
           JOIN thesis_team tt ON pr.Thesis_Team_ID = tt.Team_ID
           WHERE tt.Team_ID IN ($placeholders)
           ORDER BY pr.Completion_Date DESC";
    
    $stmt = $conn->prepare($sql);
    
    // Dynamically bind all team IDs
    $types = str_repeat('i', count($teamsUnderSupervision));
    $stmt->bind_param($types, ...$teamsUnderSupervision);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all reports
    while ($row = $result->fetch_assoc()) {
        $progressReports[] = $row;
    }
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

// Function to format file sizes
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Close the database connection
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Progress Reports - Thesis Management System</title>
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

    .reports-container {
      width: 100%;
      max-width: 1000px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 30px;
    }

    .reports-header {
      font-size: 24px;
      font-weight: bold;
      color: #004080;
      margin-bottom: 20px;
      border-bottom: 1px solid #e6f2ff;
      padding-bottom: 10px;
    }

    .reports-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .reports-table th {
      background-color: #e0f0ff;
      color: #004080;
      text-align: left;
      padding: 12px;
    }

    .reports-table td {
      padding: 12px;
      border-bottom: 1px solid #e0e0e0;
    }

    .reports-table tr:last-child td {
      border-bottom: none;
    }

    .reports-table tr:hover {
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

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    .no-reports {
      text-align: center;
      margin: 30px 0;
      color: #666;
    }

    .status-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
    }

    .status-draft {
      background-color: #ffe0cc;
      color: #994d00;
    }

    .status-submitted {
      background-color: #d4edda;
      color: #155724;
    }

    .team-filter {
      margin-bottom: 20px;
      display: flex;
      align-items: center;
    }

    .team-filter select {
      padding: 8px;
      border-radius: 4px;
      border: 1px solid #ccc;
      margin-left: 10px;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="faculty_dash.php">Dashboard</a>
    <a href="applyAsSupervisor.php">Apply as Supervisor</a>
    <a href="applyAsCosupervisor.php">Apply as Co-Supervisor</a>
    <a href="progress_fac_view.php" class="active">Reports</a>
    <a href="get_schedules.php">Schedule</a>
    <a href="thesisDB.php">Thesis Database</a>
    <a href="assign_panel.php">Panelists</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
    </div>

    <div class="content">
      <div class="header-title">
        <h2>Progress Reports</h2>
        <p>View student progress reports for teams under your supervision</p>
      </div>

      <div class="reports-container">
        <div class="reports-header">Team Progress Reports</div>
        
        <?php if (!empty($progressReports)): ?>
          <div class="team-filter">
            <label for="team-filter">Filter by Team:</label>
            <select id="team-filter">
              <option value="all">All Teams</option>
              <?php 
              $uniqueTeams = array();
              foreach ($progressReports as $report) {
                  if (!in_array($report['Team_ID'], $uniqueTeams)) {
                      $uniqueTeams[] = $report['Team_ID'];
                      echo '<option value="'.$report['Team_ID'].'">Team '.$report['Team_ID'].'</option>';
                  }
              }
              ?>
            </select>
          </div>
          
          <table class="reports-table">
            <thead>
              <tr>
                <th>Team ID</th>
                <th>Student(s)</th>
                <th>Status</th>
                <th>Submission Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($progressReports as $report): ?>
                <tr class="report-row" data-team-id="<?php echo $report['Team_ID']; ?>">
                  <td><?php echo $report['Team_ID']; ?></td>
                  <td>
                    <?php 
                      if (!empty($report['student_names'])) {
                          $names = explode(',', $report['student_names']);
                          foreach ($names as $name) {
                              echo htmlspecialchars($name) . '<br>';
                          }
                      } else {
                          echo 'No students assigned';
                      }
                    ?>
                  </td>
                  <td>
                    <span class="status-badge <?php echo strtolower($report['Status']) === 'draft' ? 'status-draft' : 'status-submitted'; ?>">
                      <?php echo $report['Status']; ?>
                    </span>
                  </td>
                  <td><?php echo $report['Completion_Date']; ?></td>
                  <td>
                    <a href="download_report.php?team_id=<?php echo $report['Thesis_Team_ID']; ?>&completion_date=<?php echo urlencode($report['Completion_Date']); ?>" class="btn">Download</a>
                    <button class="btn" onclick="viewReport(<?php echo $report['Thesis_Team_ID']; ?>, '<?php echo addslashes($report['Completion_Date']); ?>')">View Details</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-reports">
            <p>No progress reports have been submitted by your teams yet.</p>
            <?php if (empty($teamsUnderSupervision)): ?>
              <p>You currently don't have any teams under your supervision.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Filter reports by team
    document.getElementById('team-filter').addEventListener('change', function() {
      const selectedTeam = this.value;
      const rows = document.querySelectorAll('.report-row');
      
      rows.forEach(row => {
        if (selectedTeam === 'all' || row.getAttribute('data-team-id') === selectedTeam) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
    
    // Function to view report details (can be implemented later with a modal)
    function viewReport(teamId, completionDate) {
      alert('Viewing report details for Team ID: ' + teamId + ' and Completion Date: ' + completionDate);
      // This would typically open a modal with report details
    }
  </script>
</body>
</html>
