<?php
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

$matchingResults = [];
$message = "";
$allTeamSearchData = []; // To store all team_search data

// Fetch all team_search data regardless of form submission
$all_data_sql = "SELECT ts.*, u.Name, u.Email, s.CGPA, s.Student_ID 
                FROM team_search ts
                LEFT JOIN student s ON ts.SID = s.Student_ID
                LEFT JOIN user u ON s.User_Email = u.Email
                ORDER BY ts.SID DESC";
$all_data_result = $conn->query($all_data_sql);
if ($all_data_result) {
    while ($row = $all_data_result->fetch_assoc()) {
        $allTeamSearchData[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $interest = $_POST['interest'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $cgpa = floatval($_POST['cgpa'] ?? 0);
    $date = $_POST['date'] ?? '';
    $email = $_POST['email'] ?? '';
    $team_id_input = trim($_POST['team_id'] ?? '');
    $team_id = $team_id_input === '' ? null : $team_id_input;

    // Get student ID and CGPA from email
    $student_id = null;
    $student_cgpa = 0;
    $student_sql = "SELECT Student_ID, CGPA FROM student WHERE User_Email = ?";
    $stmt = $conn->prepare($student_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $student_result = $stmt->get_result();
    if ($student_result->num_rows > 0) {
        $student_row = $student_result->fetch_assoc();
        $student_id = $student_row['Student_ID'];
        $student_cgpa = floatval($student_row['CGPA']);
    }
    $stmt->close();

    // Always insert into team_search table regardless of match results
    $insert_sql = "INSERT INTO team_search (Interest, Beginning_Semester, Requ_cg, Date, Team_ID, email)
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("ssdsss", $interest, $semester, $cgpa, $date, $team_id, $email);
    
    if ($stmt->execute()) {
        $search_id = $stmt->insert_id;
        $stmt->close();
        
        // Search for matches based ONLY on Interest, Semester, and Required CGPA
        $match_sql = "SELECT ts.*, u.Name, u.Email, s.CGPA, s.Student_ID,
                     (CASE 
                         WHEN LOWER(ts.Interest) = LOWER(?) AND 
                              ts.Beginning_Semester = ? AND 
                              s.CGPA >= ? THEN 100
                         ELSE (
                             CASE 
                                 WHEN LOWER(ts.Interest) = LOWER(?) THEN 40
                                 WHEN ts.Interest LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', ts.Interest, '%') THEN 20 
                                 ELSE 0 
                             END +
                             CASE WHEN ts.Beginning_Semester = ? THEN 30 ELSE 0 END +
                             CASE WHEN s.CGPA >= ? THEN 30 ELSE 0 END
                         )
                     END) AS match_score,
                     (CASE 
                         WHEN LOWER(ts.Interest) = LOWER(?) AND 
                              ts.Beginning_Semester = ? AND 
                              s.CGPA >= ? 
                         THEN 1 ELSE 0 
                     END) AS exact_match
                     FROM team_search ts
                     LEFT JOIN student s ON ts.SID = s.Student_ID
                     LEFT JOIN user u ON s.User_Email = u.Email
                     WHERE ts.SID != ? 
                     ORDER BY exact_match DESC, match_score DESC";
        
        $stmt = $conn->prepare($match_sql);
        $stmt->bind_param("ssdssssdsssd", 
                          $interest, $semester, $cgpa,
                          $interest, $interest, $interest, 
                          $semester, 
                          $cgpa,
                          $interest, $semester, $cgpa,
                          $search_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $matchingResults = [];
        while ($row = $result->fetch_assoc()) {
            $matchingResults[] = $row;
        }
        $stmt->close();
        
        // Filter matching results to only include rows where interest matches exactly
        $matchingResults = array_filter($matchingResults, function($row) use ($interest) {
            return strtolower($row['Interest']) === strtolower($interest);
        });

        // If no matching results, set a message
        if (empty($matchingResults)) {
            $message = "No match";
        } else {
            $message = "Found potential team members that match your criteria!";
        }
        
        // Refresh the all team_search data after insertion
        $all_data_result = $conn->query($all_data_sql);
        $allTeamSearchData = [];
        if ($all_data_result) {
            while ($row = $all_data_result->fetch_assoc()) {
                $allTeamSearchData[] = $row;
            }
        }
    } else {
        $message = "Error: " . $stmt->error;
    }
}

// Function to calculate match percentage
function calculateMatchPercentage($matchScore) {
    return min(100, $matchScore);
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Team Search - Thesis Management System</title>
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
      position: fixed;
      height: 100%;
    }

    .sidebar a {
      text-decoration: none;
      color: #003366;
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 6px;
      transition: background 0.3s;
    }

    .sidebar a.active {
      background-color: #0055cc;
      color: white;
    }

    .sidebar a:hover:not(.active) {
      background-color: #c0ddf0;
    }

    .main {
      margin-left: 200px;
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
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
      padding: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #004080;
      font-weight: bold;
    }

    .form-control {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
      box-sizing: border-box;
    }

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 12px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
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

    .form-actions {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-top: 20px;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background: white;
    }

    th, td {
      border: 1px solid #ddd;
      padding: 12px;
      text-align: left;
    }

    th {
      background-color: #f0f0f0;
      color: #004080;
      font-weight: bold;
    }

    tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    tr:hover {
      background-color: #e9f0f7;
    }

    .match-percentage {
      font-weight: bold;
    }

    .match-high {
      color: #28a745;
    }

    .match-medium {
      color: #fd7e14;
    }

    .match-low {
      color: #dc3545;
    }

    .exact-match {
      background-color: #d4edda !important;
    }

    .alert {
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
      color: #0c5460;
      background-color: #d1ecf1;
      border-color: #bee5eb;
    }

    .message {
      padding: 15px;
      border-radius: 4px;
      margin-bottom: 20px;
      text-align: center;
      font-weight: bold;
    }

    .success-message {
      color: #155724;
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
    }

    .info-message {
      color: #0c5460;
      background-color: #d1ecf1;
      border: 1px solid #bee5eb;
    }

    .error-message {
      color: #721c24;
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
    }

    .contact-btn {
      background-color: #28a745;
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
    }

    .contact-btn:hover {
      background-color: #218838;
    }

    .scrollable-table {
      max-height: 400px;
      overflow-y: auto;
    }

    .tab-container {
      width: 100%;
      margin-bottom: 20px;
    }

    .tab-buttons {
      display: flex;
      border-bottom: 1px solid #ddd;
    }

    .tab-button {
      padding: 10px 20px;
      background-color: #f0f0f0;
      border: 1px solid #ddd;
      border-bottom: none;
      cursor: pointer;
      margin-right: 5px;
      border-top-left-radius: 4px;
      border-top-right-radius: 4px;
    }

    .tab-button.active {
      background-color: white;
      border-bottom: 1px solid white;
      margin-bottom: -1px;
    }

    .tab-content {
      display: none;
      padding: 20px;
      border: 1px solid #ddd;
      border-top: none;
      background-color: white;
    }

    .tab-content.active {
      display: block;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="student_dash.php">Dashboard</a>
    <a href="team.php" class="active">Team Search</a>
    <a href="supervisor.php">Supervisors</a>
    <a href="cosupervisor.php">Co-Supervisors</a>
    <a href="schedule.php">Schedule</a>
    <a href="progress_report.php">Report Progress</a>
    <a href="plagiarism_checker.php">Plagiarism Checker</a>
    <a href="panelists.php">Panelists</a>
    <a href="submit_thesis.php">Submit Thesis</a>
    <a href="feedback.php">Feedback</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
    </div>

    <div class="date-display">
      <?php echo date('Y-m-d H:i:s'); ?> UTC
    </div>

    <div class="content">
      <div class="header-title">
        <h2>Search for Team Members</h2>
        <p>Find teammates based on your interests and requirements</p>
      </div>

      <?php if (!empty($message)): ?>
        <div class="message <?php echo strpos($message, 'Error') !== false ? 'error-message' : (strpos($message, 'Found') !== false ? 'success-message' : 'info-message'); ?>">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
          <div class="form-group">
            <label for="interest">Research Interest:</label>
            <input type="text" id="interest" name="interest" class="form-control" placeholder="e.g., Machine Learning, AI" required>
          </div>

          <div class="form-group">
            <label for="semester">Semester:</label>
            <select id="semester" name="semester" class="form-control" required>
              <option value="">Select Semester</option>
              <option value="Fall">Fall</option>
              <option value="Spring">Spring</option>
              <option value="Summer">Summer</option>
            </select>
          </div>

          <div class="form-group">
            <label for="cgpa">Minimum Required CGPA:</label>
            <input type="number" id="cgpa" name="cgpa" step="0.01" min="0" max="4.00" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="date">Date (YYYY-MM-DD):</label>
            <input type="date" id="date" name="date" class="form-control" required>
          </div>

          <div class="form-group">
            <label for="email">Your Email:</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
          </div>

          <div class="form-group">
            <label for="team_id">Team ID (if in a team, enter it; else leave blank):</label>
            <input type="text" id="team_id" name="team_id" class="form-control" placeholder="e.g., 1 or leave blank if none">
          </div>

          <div class="form-actions">
            <button type="submit" class="btn">Search</button>
            <button type="reset" class="btn btn-secondary">Reset</button>
          </div>
        </form>
      </div>

      <div class="card">
        <div class="tab-container">
          <div class="tab-buttons">
            <button class="tab-button active" onclick="openTab(event, 'matchingResults')">Matching Results</button>
            <button class="tab-button" onclick="openTab(event, 'allResults')">All Team Search Data</button>
          </div>
          
          <div id="matchingResults" class="tab-content active">
            <h3>Matching Results</h3>
            <?php if (!empty($matchingResults)): ?>
              <p>Green highlighted rows indicate exact matches on Interest, Semester, and CGPA requirements.</p>
              <div class="scrollable-table">
                <table>
                  <thead>
                    <tr>
                      <th>Interest</th>
                      <th>Semester</th>
                      <th>Required CGPA</th>
                      <th>Email</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($matchingResults as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars(displayValue($row['Interest'])) ?></td>
                        <td><?= htmlspecialchars(displayValue($row['Beginning_Semester'])) ?></td>
                        <td><?= htmlspecialchars(displayValue($row['Requ_cg'])) ?></td>
                        <td><?= htmlspecialchars(displayValue($row['email'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p>No matching results found. Please search for team members or view all team search data.</p>
            <?php endif; ?>
          </div>
          
          <div id="allResults" class="tab-content">
            <h3>All Team Search Data</h3>
            <p>Showing all entries from the team_search table.</p>
            <div class="scrollable-table">
              <table>
                <thead>
                  <tr>
                    <th>SID</th>
                    <th>Interest</th>
                    <th>Semester</th>
                    <th>Required CGPA</th>
                    <th>Team ID</th>
                    <th>Email</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allTeamSearchData as $row): ?>
                    <tr>
                      <td><?= htmlspecialchars(displayValue($row['SID'])) ?></td>
                      <td><?= htmlspecialchars(displayValue($row['Interest'])) ?></td>
                      <td><?= htmlspecialchars(displayValue($row['Beginning_Semester'])) ?></td>
                      <td><?= htmlspecialchars(displayValue($row['Requ_cg'])) ?></td>
                      <td><?= htmlspecialchars(displayValue($row['Team_ID'])) ?></td>
                      <td><?= htmlspecialchars(displayValue($row['email'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function openTab(evt, tabName) {
      // Declare all variables
      var i, tabContent, tabButtons;

      // Get all elements with class="tab-content" and hide them
      tabContent = document.getElementsByClassName("tab-content");
      for (i = 0; i < tabContent.length; i++) {
        tabContent[i].className = tabContent[i].className.replace(" active", "");
      }

      // Get all elements with class="tab-button" and remove the class "active"
      tabButtons = document.getElementsByClassName("tab-button");
      for (i = 0; i < tabButtons.length; i++) {
        tabButtons[i].className = tabButtons[i].className.replace(" active", "");
      }

      // Show the current tab, and add an "active" class to the button that opened the tab
      document.getElementById(tabName).className += " active";
      evt.currentTarget.className += " active";
    }
  </script>
</body>
</html>