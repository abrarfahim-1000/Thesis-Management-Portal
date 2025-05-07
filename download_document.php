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

// Get team_id from URL parameter
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Check if faculty is supervising this team
$isSupervising = false;
if ($facultyData && $team_id > 0) {
    $sql = "SELECT 1 FROM thesis_team WHERE Team_ID = ? AND Initial = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $team_id, $facultyData['Initial']);
    $stmt->execute();
    $result = $stmt->get_result();
    $isSupervising = ($result->num_rows > 0);
}

// If faculty is not supervising this team, redirect to dashboard
if (!$isSupervising) {
    header("Location: faculty_dash.php");
    exit;
}

// Get the latest document submitted by the team
if ($team_id > 0) {
    $sql = "SELECT content, content_name, content_type 
            FROM thesis_document 
            WHERE TeamID = ? 
            ORDER BY ID DESC 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($file_content, $file_name, $file_type);
        $stmt->fetch();
        
        // Set appropriate headers for file download
        header("Content-Type: $file_type");
        header("Content-Disposition: attachment; filename=\"$file_name\"");
        header("Content-Length: " . strlen($file_content));
        
        // Output file content
        echo $file_content;
        exit;
    } else {
        // No document found, redirect back to dashboard
        header("Location: faculty_dash.php");
        exit;
    }
} else {
    // Invalid team ID, redirect back to dashboard
    header("Location: faculty_dash.php");
    exit;
}

// Close the database connection
$stmt->close();
$conn->close();
?>