<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "thesis_helper";

// Create connection with proper error handling
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Check if required parameters are provided
if (!isset($_GET['team_id']) || !isset($_GET['completion_date'])) {
    die("Missing required parameters. Both team_id and completion_date are needed.");
}

$team_id = intval($_GET['team_id']);
$completion_date = $_GET['completion_date'];

// Debug output (remove in production)
error_log("Attempting to download file for team_id: $team_id and date: $completion_date");

// Prepare and execute query - include file metadata
$stmt = $conn->prepare("SELECT File, file_name, file_type, file_size FROM Progress_Report WHERE Thesis_Team_ID = ? AND Completion_Date = ?");
$stmt->bind_param("is", $team_id, $completion_date);
$stmt->execute();

// Bind result variables
$file_data = null;
$file_name = null;
$file_type = null;
$file_size = null;
$stmt->bind_result($file_data, $file_name, $file_type, $file_size);

// Store the result
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("No file found for the given team and completion date.");
}

// Fetch the data
$stmt->fetch();

// Check if file data exists
if (empty($file_data)) {
    // Debug output
    error_log("File data is empty for team_id: $team_id and date: $completion_date");
    die("File data is empty or corrupted. Please check if the file was uploaded correctly.");
}

// Use stored filename if available, otherwise use a generic name
$download_filename = !empty($file_name) ? $file_name : "progress_report_team_" . $team_id . "_" . $completion_date . ".pdf";

// Use stored content type if available, otherwise use generic binary
$content_type = !empty($file_type) ? $file_type : "application/octet-stream";

// Clear any previous output
ob_clean();

// Set headers for download
header("Content-Type: $content_type");
header("Content-Disposition: attachment; filename=\"$download_filename\"");
header("Content-Length: " . strlen($file_data));
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

// Output the file
echo $file_data;

$stmt->close();
$conn->close();
exit;
?>