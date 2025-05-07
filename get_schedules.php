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

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 5px 10px; /* Reduced padding */
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px; /* Reduced font size */
    }

    .btn:hover {
      background-color: #0044aa;
    }

    .accept-btn {
      background-color: #28a745;
      margin-right: 5px;
    }

    .accept-btn:hover {
      background-color: #218838;
    }

    .reject-btn {
      background-color: #dc3545;
    }

    .reject-btn:hover {
      background-color: #c82333;
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
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="#">Team Search</a>
    <a href="#">Supervisor</a>
    <a href="get_schedules.php" class="active">Schedule</a>
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
        </div>

        <div class="events-list">
          <?php
          // Database connection
          $servername = "localhost";
          $username = "root"; // Update if your database user is different
          $password = ""; // Update if your database password is different
          $dbname = "thesis_helper"; // The correct database name

          // Create connection
          $conn = new mysqli($servername, $username, $password, $dbname);

          // Check connection
          if ($conn->connect_error) {
              die("Connection failed: " . $conn->connect_error);
          }

          // Update schedule status if a decision is made
          if ($_SERVER["REQUEST_METHOD"] == "POST") {
              $schedule_id = $_POST['schedule_id'];
              $action = $_POST['action'];

              // Update the status based on the action
              $status = $action === 'accept' ? 'accepted' : 'rejected';
              $update_sql = "UPDATE schedule SET status = ? WHERE SID = ?";
              $stmt = $conn->prepare($update_sql);
              $stmt->bind_param("si", $status, $schedule_id);

              if ($stmt->execute()) {
                  echo "<p style='color: green;'>Schedule ID $schedule_id has been $status.</p>";
              } else {
                  echo "<p style='color: red;'>Error: " . $stmt->error . "</p>";
              }

              $stmt->close();
          }

          // Fetch schedules
          $sql = "SELECT SID, Name, Team_ID, Initial, status FROM schedule";
          $result = $conn->query($sql);

          if ($result->num_rows > 0) {
              echo "<table class='events-table'>";
              echo "<thead><tr><th>Schedule ID</th><th>Event Name</th><th>Team ID</th><th>Faculty Initial</th><th>Status</th><th>Actions</th></tr></thead>";
              echo "<tbody>";
              while($row = $result->fetch_assoc()) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($row['SID']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['Name']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['Team_ID']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['Initial']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['status'] ?? 'pending') . "</td>";
                  echo "<td>";
                  if ($row['status'] === 'pending' || $row['status'] === null) {
                      echo "<form method='POST' style='display:inline;'>
                              <input type='hidden' name='schedule_id' value='" . htmlspecialchars($row['SID']) . "'>
                              <button type='submit' name='action' value='accept' class='btn accept-btn'>Accept</button>
                              <button type='submit' name='action' value='reject' class='btn reject-btn'>Reject</button>
                            </form>";
                  } else {
                      echo htmlspecialchars(ucfirst($row['status'])); // Show the status if already set
                  }
                  echo "</td>";
                  echo "</tr>";
              }
              echo "</tbody></table>";
          } else {
              echo "<p>No schedules found.</p>";
          }

          $conn->close();
          ?>
        </div>
      </div>
    </div>
  </div>

</body>
</html>