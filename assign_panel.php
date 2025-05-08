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

// Get current faculty email from session
$facultyEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'samiha@bracu.com';

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

// Get team_id and thesis_id from URL parameters
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$thesis_id = isset($_GET['thesis_id']) ? intval($_GET['thesis_id']) : 0;

// If thesis_id is not provided, try to get it from the thesis_document table
if ($team_id > 0 && $thesis_id == 0) {
    $sql = "SELECT ThesisID FROM thesis_document WHERE TeamID = ? ORDER BY ID DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $thesis_id = $row['ThesisID'];
    }
}

// Get list of available faculty for panel
$availableFaculty = [];
$sql = "SELECT f.Initial, u.Name, f.Domain 
        FROM faculty f 
        JOIN user u ON f.User_Email = u.Email 
        WHERE f.Initial != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $facultyData['Initial']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $availableFaculty[] = $row;
}

// Get existing panel if any
$panelData = null;
if ($thesis_id) {
    $sql = "SELECT * FROM panelist WHERE Thesis_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $thesis_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $panelData = $result->fetch_assoc();
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $faculty1 = $_POST['faculty1'] ?? '';
    $faculty2 = $_POST['faculty2'] ?? '';
    $faculty3 = $_POST['faculty3'] ?? '';
    
    if (empty($faculty1) || empty($faculty2) || empty($faculty3)) {
        $message = "Please select all three panel members.";
        $messageType = "error";
    } else if ($faculty1 == $faculty2 || $faculty2 == $faculty3 || $faculty1 == $faculty3) {
        $message = "Please select different faculty members for each panel position.";
        $messageType = "error";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Get thesis title
            $sql = "SELECT Topic FROM thesis_document WHERE TeamID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $team_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $thesisData = $result->fetch_assoc();
            $thesis_title = $thesisData['Topic'];
            
            // Check if thesis_id exists in thesisdb table
            $sql = "SELECT Thesis_ID FROM thesisdb WHERE Thesis_ID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $thesis_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // If thesis_id doesn't exist in thesisdb, insert it first
            if ($result->num_rows == 0 && $thesis_id > 0) {
                $sql = "INSERT INTO thesisdb (Thesis_ID) VALUES (?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $thesis_id);
                $stmt->execute();
            }
            
            if ($panelData) {
                // Update existing panel
                $sql = "UPDATE panelist SET Faculty1 = ?, Faculty2 = ?, Faculty3 = ? WHERE Thesis_ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssi", $faculty1, $faculty2, $faculty3, $thesis_id);
            } else {
                // Insert new panel
                $sql = "INSERT INTO panelist (Thesis_ID, thesis_title, Faculty1, Faculty2, Faculty3) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issss", $thesis_id, $thesis_title, $faculty1, $faculty2, $faculty3);
            }
            
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $message = "Panel assigned successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $message = "Error assigning panel: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

// Close the database connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Panel - Thesis Management System</title>
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

        .content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .panel-form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 800px;
        }

        .form-header {
            font-size: 24px;
            font-weight: bold;
            color: #004080;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #004080;
        }

        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            background-color: #0055cc;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
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

        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .current-panel {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .current-panel h4 {
            color: #004080;
            margin-top: 0;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <a href="faculty_dash.php" class="active">Dashboard</a>
    <a href="applyAsSupervisor.php">Apply as Supervisor</a>
    <a href="applyAsCosupervisor.php">Apply as Co-Supervisor</a>
    <a href="progress_fac_view.php">Reports</a>
    <a href="get_schedules.php">Schedule</a>
    <a href="thesisDB.php">Thesis Database</a>
    <a href="assign_panel.php">Panelists</a>
    </div>

    <div class="main">
        <div class="topbar">
            <h1>THESIS MANAGEMENT SYSTEM</h1>
        </div>

        <div class="content">
            <div class="panel-form">
                <div class="form-header">Assign Panel Members</div>
                
                <?php if ($message): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($panelData): ?>
                    <div class="current-panel">
                        <h4>Current Panel Members</h4>
                        <p><strong>Panel Member 1:</strong> <?php echo displayValue($panelData['Faculty1']); ?></p>
                        <p><strong>Panel Member 2:</strong> <?php echo displayValue($panelData['Faculty2']); ?></p>
                        <p><strong>Panel Member 3:</strong> <?php echo displayValue($panelData['Faculty3']); ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="faculty1">Panel Member 1:</label>
                        <select name="faculty1" id="faculty1" required>
                            <option value="">Select Faculty Member</option>
                            <?php foreach ($availableFaculty as $faculty): ?>
                                <option value="<?php echo $faculty['Initial']; ?>" 
                                    <?php echo ($panelData && $panelData['Faculty1'] == $faculty['Initial']) ? 'selected' : ''; ?>>
                                    <?php echo $faculty['Name'] . ' (' . $faculty['Initial'] . ') - ' . $faculty['Domain']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="faculty2">Panel Member 2:</label>
                        <select name="faculty2" id="faculty2" required>
                            <option value="">Select Faculty Member</option>
                            <?php foreach ($availableFaculty as $faculty): ?>
                                <option value="<?php echo $faculty['Initial']; ?>"
                                    <?php echo ($panelData && $panelData['Faculty2'] == $faculty['Initial']) ? 'selected' : ''; ?>>
                                    <?php echo $faculty['Name'] . ' (' . $faculty['Initial'] . ') - ' . $faculty['Domain']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="faculty3">Panel Member 3:</label>
                        <select name="faculty3" id="faculty3" required>
                            <option value="">Select Faculty Member</option>
                            <?php foreach ($availableFaculty as $faculty): ?>
                                <option value="<?php echo $faculty['Initial']; ?>"
                                    <?php echo ($panelData && $panelData['Faculty3'] == $faculty['Initial']) ? 'selected' : ''; ?>>
                                    <?php echo $faculty['Name'] . ' (' . $faculty['Initial'] . ') - ' . $faculty['Domain']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="actions">
                        <a href="faculty_dash.php" class="btn btn-secondary">Back to Dashboard</a>
                        <button type="submit" class="btn">Assign Panel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>