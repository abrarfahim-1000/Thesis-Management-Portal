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

// Get the faculty initial from URL parameter
$initial = isset($_GET['initial']) ? $_GET['initial'] : '';
$userEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

if ($initial && $userEmail) {
    // Get student's team ID
    $sql = "SELECT Team_ID FROM student WHERE User_Email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $studentData = $result->fetch_assoc();
    
    if ($studentData && $studentData['Team_ID']) {
        // Check if a request already exists
        $sql = "SELECT * FROM supervisor_requests WHERE Team_ID = ? AND Initial = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $studentData['Team_ID'], $initial);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert new request
            $sql = "INSERT INTO supervisor_requests (Team_ID, Initial, Request_Date, Status) VALUES (?, ?, NOW(), 'Pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $studentData['Team_ID'], $initial);
            
            if ($stmt->execute()) {
                $message = "Request sent successfully!";
                $status = "success";
            } else {
                $message = "Error sending request.";
                $status = "error";
            }
        } else {
            $message = "You have already requested this supervisor.";
            $status = "warning";
        }
    } else {
        $message = "You need to be part of a team to request a supervisor.";
        $status = "warning";
    }
} else {
    $message = "Invalid request parameters.";
    $status = "error";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Supervisor - Thesis Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f2f8fc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .message-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        .message {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0055cc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0044aa;
        }
    </style>
</head>
<body>
    <div class="message-container">
        <div class="message <?php echo $status; ?>">
            <?php echo $message; ?>
        </div>
        <a href="supervisor.php" class="btn">Back to Supervisor List</a>
    </div>
</body>
</html>