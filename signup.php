<?php
session_start();
include 'database.php';

$signup_message = '';
if (isset($_POST['signup-submit'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $password = $_POST['password'];
    $faculty_initial = isset($_POST['faculty_initial']) ? $_POST['faculty_initial'] : '';

    $check_sql = "SELECT * FROM User WHERE Email = '$email'";
    $check_result = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $signup_message = '<div class="message error">Email already exists.</div>';
    } else {
        // Check if it's a faculty email and validate the initial
        if (strpos($email, '@bracu.com') !== false) {
            if (empty($faculty_initial)) {
                $signup_message = '<div class="message error">Faculty initial is required for BRACU faculty.</div>';
            } else {
                // Check if the initial already exists
                $check_initial_sql = "SELECT * FROM Faculty WHERE Initial = '$faculty_initial'";
                $check_initial_result = mysqli_query($conn, $check_initial_sql);

                if (mysqli_num_rows($check_initial_result) > 0) {
                    $signup_message = '<div class="message error">Faculty initial already exists.</div>';
                } else {
                    // Proceed with insertion
                    proceedWithInsertion($conn, $name, $email, $department, $password, $faculty_initial);
                }
            }
        } else {
            // Not a faculty email, proceed normally
            proceedWithInsertion($conn, $name, $email, $department, $password, $faculty_initial);
        }
    }
}

// Function to handle the insertion process
function proceedWithInsertion($conn, $name, $email, $department, $password, $faculty_initial) {
    global $signup_message;
    
    $insert_user_sql = "INSERT INTO User (Name, Email, Department, Password) VALUES ('$name', '$email', '$department', '$password')";
    if (mysqli_query($conn, $insert_user_sql)) {
        // Decide where to insert: Student or Faculty
        if (strpos($email, '@student.com') !== false) {
            // Insert into Student table
            $insert_student_sql = "INSERT INTO Student (User_Email, Department) VALUES ('$email', '$department')";
            if (!mysqli_query($conn, $insert_student_sql)) {
                $signup_message = '<div class="message error">User created, but error adding to Student table: ' . mysqli_error($conn) . '</div>';
            } else {
                $signup_message = '<div class="message success">Student account created successfully! <a href="landing.php">Log in here</a>.</div>';
            }
        } elseif (strpos($email, '@bracu.com') !== false) {
            // Insert into Faculty table
            $insert_faculty_sql = "INSERT INTO Faculty (Initial, User_Email, Department) VALUES ('$faculty_initial', '$email', '$department')";
            if (!mysqli_query($conn, $insert_faculty_sql)) {
                $signup_message = '<div class="message error">User created, but error adding to Faculty table: ' . mysqli_error($conn) . '</div>';
            } else {
                $signup_message = '<div class="message success">Faculty account created successfully! <a href="landing.php">Log in here</a>.</div>';
            }
        } else {
            $signup_message = '<div class="message warning">User created, but email domain not recognized (no student/faculty entry made).</div>';
        }
    } else {
        $signup_message = '<div class="message error">Error creating user: ' . mysqli_error($conn) . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sign Up - Thesis Management System</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to bottom, #e0f0ff, #b3d9ff);
      color: #003366;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    header {
      text-align: center;
      margin-top: 40px;
    }
    header h1 {
      font-size: 32px;
      color: #002244;
    }
    .container {
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      padding: 30px;
      width: 90%;
      max-width: 400px;
      margin: 20px;
    }
    h2 {
      color: #004080;
      margin-bottom: 10px;
    }
    label, input, button {
      width: 100%;
      margin-bottom: 10px;
    }
    input {
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    button {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 6px;
      cursor: pointer;
    }
    button:hover {
      background-color: #0044aa;
    }
    .message {
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 5px;
      /* Add transition for smooth fade out effect */
      transition: opacity 0.5s ease-in-out;
    }
    .success {
      background-color: #d4edda;
      color: #155724;
    }
    .error {
      background-color: #f8d7da;
      color: #721c24;
    }
    .link-button {
      background: none;
      border: none;
      color: #0055cc;
      cursor: pointer;
      text-decoration: underline;
      margin-top: 10px;
      font-size: 14px;
    }
    .link-button:hover {
      color: #003399;
      text-decoration: underline;
    }
    #faculty-initial-container {
      display: none; /* Initially hidden */
      margin-bottom: 15px;
      padding-top: 5px;
      border-top: 1px solid #e0f0ff;
    }
    #faculty-initial-container label {
      color: #004080;
      font-weight: bold;
    }
    .field-note {
      font-size: 12px;
      color: #666;
      margin-top: 2px;
      margin-bottom: 10px;
    }
  </style>
  <script>
    // Function to hide notifications after a set time
    document.addEventListener('DOMContentLoaded', function() {
      const messages = document.querySelectorAll('.message');
      
      if (messages.length > 0) {
        setTimeout(function() {
          messages.forEach(function(message) {
            // Fade out effect
            message.style.opacity = '0';
            // Remove from DOM after fade transition completes
            setTimeout(function() {
              message.style.display = 'none';
            }, 500);
          });
        }, 3000); // 3 second timeout
      }
      
      // Add email domain check to show/hide faculty initial field
      const emailInput = document.getElementById('signup-email');
      const facultyInitialContainer = document.getElementById('faculty-initial-container');
      
      // Initial check
      checkEmailDomain();
      
      // Add event listener for email changes
      emailInput.addEventListener('input', checkEmailDomain);
      
      function checkEmailDomain() {
        if (emailInput.value.indexOf('@bracu.com') !== -1) {
          facultyInitialContainer.style.display = 'block';
          document.getElementById('signup-faculty-initial').required = true;
        } else {
          facultyInitialContainer.style.display = 'none';
          document.getElementById('signup-faculty-initial').required = false;
        }
      }
    });
  </script>
</head>
<body>
  <header>
    <h1>Welcome to the Thesis Management Portal</h1>
  </header>

  <div class="container">
    <h2>Create Account</h2>
    <?php echo $signup_message; ?>
    <form method="post">
      <label for="signup-name">Name</label>
      <input type="text" id="signup-name" name="name" required>

      <label for="signup-email">Email</label>
      <input type="email" id="signup-email" name="email" required>

      <label for='signup-department'>Department</label>
      <input type='department' id='signup-department' name='department' required>

      <label for="signup-password">Password</label>
      <input type="password" id="signup-password" name="password" required>

      <div id="faculty-initial-container">
        <label for="signup-faculty-initial">Faculty Initial (for BRACU faculty only)</label>
        <input type="text" id="signup-faculty-initial" name="faculty_initial">
      </div>

      <button type="submit" name="signup-submit">Create Account</button>
    </form>

    <form action="landing.php" method="get">
      <button class="link-button" type="submit">Back to Log In</button>
    </form>
  </div>
</body>
</html>
