<?php
session_start();
include 'database.php';

$login_message = '';
if (isset($_POST['login-submit'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM User WHERE Email = '$email' AND Password = '$password'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $_SESSION['user_email'] = $row['Email'];
        $_SESSION['name'] = $row['Name'];

        // Check email domain
        if (strpos($email, '@bracu.com') !== false) {
            header("Location: faculty_dash.php");
            exit();
        } elseif (strpos($email, '@student.com') !== false) {
            header("Location: student_dash.php");
            exit();
        } else {
            // Default fallback if the email domain is unrecognized
            $login_message = '<div class="message error">Unrecognized email domain. Please contact admin.</div>';
        }
    } else {
        $login_message = '<div class="message error">Invalid email or password</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Log In - Thesis Management System</title>
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
      transition: opacity 0.5s ease-out;
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
  </style>
  <script>
    // Function to hide notifications after a timeout
    document.addEventListener('DOMContentLoaded', function() {
      const messages = document.querySelectorAll('.message');
      if (messages.length > 0) {
        setTimeout(function() {
          messages.forEach(function(message) {
            message.style.opacity = '0';
            setTimeout(function() { 
              message.style.display = 'none'; 
            }, 500);
          });
        }, 3000); // 3 seconds timeout
      }
    });
  </script>
</head>
<body>
  <header>
    <h1>Welcome to the Thesis Management Portal</h1>
  </header>

  <div class="container">
    <h2>Log In</h2>
    <?php echo $login_message; ?>
    <form method="post">
      <label for="login-email">Email</label>
      <input type="email" id="login-email" name="email" required>

      <label for="login-password">Password</label>
      <input type="password" id="login-password" name="password" required>

      <button type="submit" name="login-submit">Log In</button>
    </form>

    <form action="signup.php" method="get">
      <button class="link-button" type="submit">Create a New Account</button>
    </form>
  </div>
</body>
</html>
