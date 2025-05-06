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

// Get faculty information to use throughout the script
$sql = "SELECT f.Initial, f.Domain, f.Requirements, 
        u.Name, u.Email 
        FROM faculty f 
        JOIN user u ON f.User_Email = u.Email 
        WHERE f.User_Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $facultyEmail);
$stmt->execute();
$result = $stmt->get_result();
$facultyData = $result->fetch_assoc();
$stmt->close();

// Check if faculty is already a co-supervisor
$isCoSupervisor = false;
$checkSql = "SELECT COUNT(*) as count FROM co_supervisor WHERE E_initial = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $facultyData['Initial']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$checkData = $checkResult->fetch_assoc();
if ($checkData['count'] > 0) {
    $isCoSupervisor = true;
}
$checkStmt->close();

// Check if form is submitted
$successMessage = "";
$errorMessage = "";
$formSubmitted = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $formSubmitted = true;
    // Get form data
    $researchInterests = $_POST['research_interests'];
    $requirements = $_POST['requirements'];
    $availability = isset($_POST['availability']) && $_POST['availability'] == 'yes' ? true : false;

    // Update faculty information - only Domain and Requirements, NOT Availability
    $sql = "UPDATE faculty SET Domain = ?, Requirements = ? WHERE User_Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $researchInterests, $requirements, $facultyEmail);

    if ($stmt->execute()) {
        if ($availability) {
            // Faculty wants to be a co-supervisor
            if (!$isCoSupervisor) {
                // Not a co-supervisor yet, add to table
                $insertSql = "INSERT INTO co_supervisor (E_initial) VALUES (?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("s", $facultyData['Initial']);
                
                if ($insertStmt->execute()) {
                    $successMessage = "You are now registered as a co-supervisor.";
                    $isCoSupervisor = true;
                    
                    // Reset form data after successful submission
                    $researchInterests = "";
                    $requirements = "";
                } else {
                    $errorMessage = "Error adding you as a co-supervisor: " . $insertStmt->error;
                }
                $insertStmt->close();
            } else {
                $successMessage = "You are already a co-supervisor.";
            }
        } else {
            // Faculty does NOT want to be a co-supervisor
            // Check if there are any thesis teams under this co-supervisor
            $checkTeamsSql = "SELECT COUNT(*) as count FROM thesis_team WHERE Cinitial = ?";
            $checkTeamsStmt = $conn->prepare($checkTeamsSql);
            $checkTeamsStmt->bind_param("s", $facultyData['Initial']);
            $checkTeamsStmt->execute();
            $checkTeamsResult = $checkTeamsStmt->get_result();
            $checkTeamsData = $checkTeamsResult->fetch_assoc();
            $checkTeamsStmt->close();

            if ($checkTeamsData['count'] > 0) {
                $errorMessage = "You have theses under your co-supervision. You cannot recuse yourself.";
            } else {
                if ($isCoSupervisor) {
                    // Remove faculty initial from co_supervisor table
                    $deleteSql = "DELETE FROM co_supervisor WHERE E_initial = ?";
                    $deleteStmt = $conn->prepare($deleteSql);
                    $deleteStmt->bind_param("s", $facultyData['Initial']);
                    
                    if ($deleteStmt->execute()) {
                        $successMessage = "You have been removed from the co-supervisor list.";
                        $isCoSupervisor = false;
                        
                        // Reset form data after successful submission
                        $researchInterests = "";
                        $requirements = "";
                    } else {
                        $errorMessage = "Error removing you from co-supervisor list: " . $deleteStmt->error;
                    }
                    $deleteStmt->close();
                } else {
                    $successMessage = "You were not a co-supervisor.";
                }
            }
        }
    } else {
        $errorMessage = "Error updating your information: " . $stmt->error;
    }

    $stmt->close();
    
    // Refresh faculty data after update
    $sql = "SELECT f.Initial, f.Domain, f.Requirements, 
            u.Name, u.Email 
            FROM faculty f 
            JOIN user u ON f.User_Email = u.Email 
            WHERE f.User_Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $facultyEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $facultyData = $result->fetch_assoc();
    $stmt->close();

    // Use PHP header to redirect and refresh the page to clear form fields
    if ($successMessage != "") {
        // Store success message in session to display after redirect
        $_SESSION['success_message'] = $successMessage;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Check for success message in session and display it
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after using it
    
    // Set a timestamp to control notification display time
    $_SESSION['notification_timestamp'] = time();
}

// Auto-hide notifications after 3 seconds
if (isset($_SESSION['notification_timestamp']) && (time() - $_SESSION['notification_timestamp'] > 3)) {
    $successMessage = "";
    $errorMessage = "";
    unset($_SESSION['notification_timestamp']);
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Apply as Co-Supervisor - Thesis Management System</title>
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

    .sidebar a.active {
      background-color: #0055cc;
      color: white;
    }

    .sidebar a:hover:not(.active) {
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

    textarea.form-control {
      min-height: 100px;
      resize: vertical;
    }

    .radio-group {
      display: flex;
      gap: 20px;
      margin-top: 10px;
    }

    .radio-option {
      display: flex;
      align-items: center;
    }

    .radio-option input {
      margin-right: 8px;
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
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    .page-info {
      color: #666;
      font-size: 0.9em;
      margin-top: 15px;
      background-color: #f8f9fa;
      padding: 10px;
      border-radius: 4px;
      border-left: 4px solid #0055cc;
    }

    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      width: 100%;
      transition: opacity 0.5s ease-out; /* Add smooth fade-out transition */
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-danger {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="applyAsSupervisor.php">Apply as Supervisor</a>
    <a href="#" class="active">Apply as Co-Supervisor</a>
    <a href="#">Schedule</a>
    <a href="faculty_dash.php">Dashboard</a>
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
        <h2>Apply as Thesis Co-Supervisor</h2>
        <p>Welcome, <?php echo isset($facultyData['Name']) ? $facultyData['Name'] : 'Faculty Member'; ?></p>
      </div>

      <?php if ($successMessage): ?>
        <div class="alert alert-success" id="success-alert">
          <?php echo $successMessage; ?>
        </div>
      <?php endif; ?>

      <?php if ($errorMessage): ?>
        <div class="alert alert-danger" id="error-alert">
          <?php echo $errorMessage; ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <form id="cosupervisorForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
          <div class="form-group">
            <label for="research_interests">Research Interests</label>
            <input type="text" id="research_interests" name="research_interests" class="form-control" 
                   placeholder="Enter your research interests (e.g., Machine Learning, AI, Data Science)" 
                   value="<?php echo isset($facultyData['Domain']) ? $facultyData['Domain'] : ''; ?>">
            <small>This will be stored in the Domain field of the faculty table.</small>
          </div>

          <div class="form-group">
            <label for="requirements">Requirements for Students (Minimum CGPA)</label>
            <input type="number" id="requirements" name="requirements" class="form-control" 
                   step="0.01" min="0" max="4.00" placeholder="Enter minimum CGPA requirement (e.g., 3.50)"
                   value="<?php echo isset($facultyData['Requirements']) ? $facultyData['Requirements'] : ''; ?>">
            <small>Enter the minimum CGPA required for students to apply.</small>
          </div>

          <div class="form-group">
            <label>Co-Supervisor Status</label>
            <div class="radio-group">
              <div class="radio-option">
                <input type="radio" id="availability_yes" name="availability" value="yes" 
                       <?php echo ($isCoSupervisor) ? 'checked' : ''; ?>>
                <label for="availability_yes">Yes, I want to be a co-supervisor</label>
              </div>
              <div class="radio-option">
                <input type="radio" id="availability_no" name="availability" value="no"
                       <?php echo (!$isCoSupervisor) ? 'checked' : ''; ?>>
                <label for="availability_no">No, I do not want to be a co-supervisor</label>
              </div>
            </div>
            <small>This will determine whether you are listed as an available co-supervisor.</small>
          </div>

          <div class="page-info">
            <p>After submission, your research interests and CGPA requirements will be displayed to students. If you choose to be a co-supervisor, students will be able to select you when forming thesis teams.</p>
          </div>

          <div class="form-actions">
            <a href="faculty-dashboard.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn">Submit</button>
            <button type="reset" class="btn btn-secondary">Reset</button>
          </div>
        </form>
      </div>
    </div>
  </div>

</body>
</html>