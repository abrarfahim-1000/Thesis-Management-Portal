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

// Get supervisors from the database
$sql = "SELECT f.Initial, f.Domain, f.Availability, f.Requirements, f.department, 
        u.Name, u.Email, u.Department as UserDepartment 
        FROM supervisor s 
        JOIN faculty f ON s.E_Initial = f.Initial 
        JOIN user u ON f.User_Email = u.Email";
$result = $conn->query($sql);

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

// Function to display availability status
function displayAvailability($value) {
    if ($value === null) return 'Not Available';
    return ($value == 1) ? '<span class="available">Accepting</span>' : '<span class="full">Not Accepting</span>';
}

// Function to get initials from name
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

// Function to format domain tags
function formatDomainTags($domain) {
    if ($domain === null || $domain === '') return 'Not Available';
    
    $domains = explode(',', $domain);
    $html = '';
    foreach ($domains as $d) {
        $d = trim($d);
        if (!empty($d)) {
            $html .= '<span class="domain-tag">' . htmlspecialchars($d) . '</span> ';
        }
    }
    return $html;
}

// Close the database connection after we're done
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Supervisors - Thesis Management System</title>
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
      margin: 0;
      font-size: 20px;
    }

    .content {
      padding: 20px;
      overflow-y: auto;
    }

    .supervisor-table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .supervisor-table th,
    .supervisor-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #e0f0ff;
    }

    .supervisor-table th {
      background-color: #e0f0ff;
      color: #004080;
    }

    .initial-circle {
      width: 40px;
      height: 40px;
      background-color: #0055cc;
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
    }

    .available {
      color: green;
      font-weight: bold;
    }

    .full {
      color: red;
      font-weight: bold;
    }

    .domain-tag {
      display: inline-block;
      background-color: #e0f0ff;
      padding: 5px 10px;
      border-radius: 12px;
      font-size: 0.9em;
      margin-right: 5px;
      margin-bottom: 5px;
      border: 1px solid #b3d9ff;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    .no-data {
      text-align: center;
      padding: 20px;
      color: #666;
    }

    .request-btn {
      display: inline-block;
      padding: 5px 10px;
      background-color: #0055cc;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      font-size: 0.9em;
    }

    .disabled-btn {
      display: inline-block;
      padding: 5px 10px;
      background-color: #ccc;
      color: #666;
      border-radius: 5px;
      font-size: 0.9em;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="student_dash.php">Dashboard</a>
    <a href="teamsearch.php">Team Search</a>
    <a href="supervisor.php" class="active">Supervisor</a>
    <a href="cosupervisor.php">Co-Supervisor</a>
    <a href="get_schedule2.php">Schedule</a>
    <a href="progressreport.php">Report Progress</a>
    <a href="submit_thesis.php">Submit Thesis</a>
    <a href="student_feedback.php">Feedback</a>
    <a href="thesisdb.php">ThesisDB</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
    </div>

    <div class="content">
      <h2>Supervisor List</h2>
      <table class="supervisor-table">
        <thead>
          <tr>
            <th>Initial</th>
            <th>Name</th>
            <th>Department</th>
            <th>Availability</th>
            <th>Requirement</th>
            <th>Domain</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($result->num_rows > 0) {
              // Output data of each row
              while($row = $result->fetch_assoc()) {
                  echo "<tr>";
                  echo "<td><div class='initial-circle'>" . $row["Initial"] . "</div></td>";
                  echo "<td>" . displayValue($row["Name"]) . "</td>";
                  echo "<td>" . displayValue($row["department"] ? $row["department"] : $row["UserDepartment"]) . "</td>";
                  echo "<td>" . displayAvailability($row["Availability"]) . "</td>";
                  echo "<td>" . displayValue($row["Requirements"]) . "</td>";
                  echo "<td>" . formatDomainTags($row["Domain"]) . "</td>";
                  echo "<td>";
                  if ($row["Availability"] == 1) {
                      echo "<a href='request_supervisor.php?initial=" . urlencode($row["Initial"]) . "' class='request-btn'>Request</a>";
                  } else {
                      echo "<span class='disabled-btn'>Request</span>";
                  }
                  echo "</td>";
                  echo "</tr>";
              }
          } else {
              echo "<tr><td colspan='7' class='no-data'>No supervisors found</td></tr>";
          }
          $conn->close();
          ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>