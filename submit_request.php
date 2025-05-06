<?php
session_start();
$conn = new mysqli("localhost", "root", "", "thesis_helper");

// Check session and connection
if (!isset($_SESSION['user_email']) || $conn->connect_error) {
    header("Location: login.php");
    exit();
}

$student_email = $_SESSION['user_email'];
$supervisor_initial = $_GET['initial'];
$requested_date = $_POST['requested_date']; // Expected from form submission

$sql = "INSERT INTO appointment_requests (student_email, supervisor_initial, requested_date) 
        VALUES ('$student_email', '$supervisor_initial', '$requested_date')";

if ($conn->query($sql) === TRUE) {
    echo "Request submitted successfully!";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
