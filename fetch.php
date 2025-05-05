<?php
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

// SQL query
$sql = "SELECT Student_ID, User_Email FROM student";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Output as an HTML table
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["Student_ID"] . "</td>";
        // echo "<td>" . $row["name"] . "</td>";
        echo "<td>" . $row["User_Email"] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "0 results";
}

$conn->close();
?>
