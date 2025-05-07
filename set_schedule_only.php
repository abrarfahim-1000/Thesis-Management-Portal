<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create a New Schedule - Thesis Management System</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to bottom, #e0f0ff, #b3d9ff);
      color: #003366;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .container {
      width: 100%;
      max-width: 600px;
      background-color: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    h1 {
      color: #004080;
      text-align: center;
    }

    form {
      display: flex;
      flex-direction: column;
    }

    label {
      margin-bottom: 5px;
      font-weight: bold;
    }

    input, select, textarea {
      margin-bottom: 15px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    .success {
      color: green;
      font-weight: bold;
      text-align: center;
    }

    .error {
      color: red;
      font-weight: bold;
      text-align: center;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 20px;
      color: #0055cc;
      text-decoration: none;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .actions {
      display: flex;
      justify-content: space-between;
      margin-top: 10px;
    }
  </style>
</head>
<body>

  <div class="container">
    <h1>Create a New Schedule</h1>

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
        echo "<p class='error'>Connection failed: " . htmlspecialchars($conn->connect_error) . "</p>";
        exit;
    }

    // Check if Event_Date and Event_Time columns exist, if not, add them
    $checkDateColumn = "SHOW COLUMNS FROM schedule LIKE 'Event_Date'";
    $dateColumnExists = $conn->query($checkDateColumn)->num_rows > 0;
    
    if (!$dateColumnExists) {
        $addDateColumn = "ALTER TABLE schedule ADD COLUMN Event_Date DATE";
        $conn->query($addDateColumn);
        
        $addTimeColumn = "ALTER TABLE schedule ADD COLUMN Event_Time TIME";
        $conn->query($addTimeColumn);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Collect and validate form data
        $event_name = trim($_POST['event_name']);
        $team_id = intval($_POST['team_id']);
        $faculty_initial = trim($_POST['faculty_initial']);
        $event_date = $_POST['event_date'];
        $event_time = $_POST['event_time'];

        // Validate team_id exists
        $teamCheck = "SELECT Team_ID FROM thesis_team WHERE Team_ID = ?";
        $teamStmt = $conn->prepare($teamCheck);
        $teamStmt->bind_param("i", $team_id);
        $teamStmt->execute();
        $teamResult = $teamStmt->get_result();
        $teamExists = $teamResult->num_rows > 0;
        $teamStmt->close();

        // Validate faculty initial exists
        $facultyCheck = "SELECT Initial FROM faculty WHERE Initial = ?";
        $facultyStmt = $conn->prepare($facultyCheck);
        $facultyStmt->bind_param("s", $faculty_initial);
        $facultyStmt->execute();
        $facultyResult = $facultyStmt->get_result();
        $facultyExists = $facultyResult->num_rows > 0;
        $facultyStmt->close();

        if (empty($event_name) || empty($team_id) || empty($faculty_initial) || empty($event_date) || empty($event_time)) {
            echo "<p class='error'>All fields are required!</p>";
        } elseif (!$teamExists) {
            echo "<p class='error'>Team ID does not exist!</p>";
        } elseif (!$facultyExists) {
            echo "<p class='error'>Faculty Initial does not exist!</p>";
        } else {
            // Insert data into the database
            $sql = "INSERT INTO schedule (Name, Team_ID, Initial, Event_Date, Event_Time, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sisss", $event_name, $team_id, $faculty_initial, $event_date, $event_time);
                if ($stmt->execute()) {
                    echo "<p class='success'>Schedule created successfully!</p>";
                    echo "<a href='get_schedule2.php' class='back-link'>Return to Schedule List</a>";
                } else {
                    echo "<p class='error'>Error: " . htmlspecialchars($stmt->error) . "</p>";
                }
                $stmt->close();
            } else {
                echo "<p class='error'>Error preparing statement: " . htmlspecialchars($conn->error) . "</p>";
            }
        }
    }
    ?>

    <form method="POST" action="">
      <label for="event_name">Event Name:</label>
      <input type="text" id="event_name" name="event_name" placeholder="Enter the event name" required>

      <label for="team_id">Team ID:</label>
      <input type="number" id="team_id" name="team_id" placeholder="Enter your Team ID" required>

      <label for="faculty_initial">Faculty Initial:</label>
      <input type="text" id="faculty_initial" name="faculty_initial" placeholder="Enter Faculty Initial" required>

      <label for="event_date">Date:</label>
      <input type="date" id="event_date" name="event_date" required>

      <label for="event_time">Time:</label>
      <input type="time" id="event_time" name="event_time" required>

      <div class="actions">
        <a href="get_schedule2.php" class="back-link">Cancel</a>
        <button type="submit" class="btn">Create Schedule</button>
      </div>
    </form>
  </div>

</body>
</html>
