<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Schedule - Thesis Management System</title>
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
      font-size: 14px;
    }

    .content {
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .schedule-container {
      width: 100%;
      max-width: 800px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 20px;
    }

    .schedule-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .schedule-title {
      font-size: 24px;
      font-weight: bold;
      color: #004080;
    }

    .schedule-button {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
    }

    .schedule-button:hover {
      background-color: #0044aa;
    }

    .events-list {
      width: 100%;
    }

    .events-table {
      width: 100%;
      border-collapse: collapse;
    }

    .events-table th, .events-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #e6f2ff;
    }

    .events-table th {
      background-color: #f0f7ff;
      color: #004080;
      font-weight: bold;
    }

    .events-table tr:hover {
      background-color: #f5f9ff;
    }

    .no-meetings-message {
      text-align: center;
      font-size: 18px;
      color: #004080;
    }

    .status-pending {
      color: #ff9900;
      font-weight: bold;
    }

    .status-accepted {
      color: #00aa00;
      font-weight: bold;
    }

    .status-rejected {
      color: #cc0000;
      font-weight: bold;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="#">Team Search</a>
    <a href="#">Supervisor</a>
    <a href="get_schedule2.php" class="active">Schedule</a>
    <a href="#">Submit Thesis</a>
    <a href="#">Feedback</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
      <div class="user-info">shahriar121-11</div>
    </div>

    <div class="content">
      <div class="schedule-container">
        <div class="schedule-header">
          <div class="schedule-title">Event Schedule</div>
          <!-- Fixed: Corrected the filename to match the actual file -->
          <a href="set_schedule_only.php" class="schedule-button">Schedule Meeting</a>
        </div>

        <div class="events-list">
          <?php
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

          // Check if the Event_Date and Event_Time columns exist
          $checkColumnsQuery = "SHOW COLUMNS FROM schedule LIKE 'Event_Date'";
          $columnsExist = $conn->query($checkColumnsQuery)->num_rows > 0;

          // Fetch schedules with appropriate columns
          if ($columnsExist) {
              $sql = "SELECT SID, Name, Team_ID, Initial, status, Event_Date, Event_Time FROM schedule";
          } else {
              $sql = "SELECT SID, Name, Team_ID, Initial, status FROM schedule";
          }
          
          $result = $conn->query($sql);

          if ($result && $result->num_rows > 0) {
              echo "<table class='events-table'>";
              echo "<thead><tr><th>Schedule ID</th><th>Event Name</th><th>Team ID</th><th>Faculty Initial</th>";
              
              // Add date and time columns if they exist
              if ($columnsExist) {
                  echo "<th>Date</th><th>Time</th>";
              }
              
              echo "<th>Status</th></tr></thead>";
              echo "<tbody>";
              
              while($row = $result->fetch_assoc()) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($row['SID']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['Name']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['Team_ID']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['Initial']) . "</td>";
                  
                  // Display date and time if columns exist
                  if ($columnsExist) {
                      echo "<td>" . htmlspecialchars($row['Event_Date']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['Event_Time']) . "</td>";
                  }
                  
                  // Display status with appropriate styling
                  $status = $row['status'] ?? 'pending';
                  $statusClass = 'status-' . strtolower($status);
                  echo "<td class='" . $statusClass . "'>" . htmlspecialchars(ucfirst($status)) . "</td>";
                  
                  echo "</tr>";
              }
              echo "</tbody></table>";
          } else {
              // Message when no meetings are scheduled
              echo "<p class='no-meetings-message'>No meetings scheduled yet. Click 'Schedule Meeting' to create one.</p>";
          }

          $conn->close();
          ?>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
