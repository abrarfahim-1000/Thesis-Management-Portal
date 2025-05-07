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

// Increase MySQL packet size for this connection
$conn->query("SET GLOBAL max_allowed_packet=67108864"); // 64MB

// Get current user email from session (assuming it's stored in session)
// In a real application, you would have proper authentication
$userEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'abrar1@student.com'; // Default for testing

// Get student information
$sql = "SELECT s.Student_ID, u.Name, s.Team_ID 
        FROM student s 
        JOIN user u ON s.User_Email = u.Email 
        WHERE s.User_Email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$studentData = $result->fetch_assoc();

// Check if student is in a team
if (!$studentData || !$studentData['Team_ID']) {
    $message = "You need to be part of a team before submitting a thesis.";
    $messageType = "error";
} else {
    $teamID = $studentData['Team_ID'];
}

// Function to display "Not Available" for null values
function displayValue($value) {
    return ($value === null || $value === '') ? 'Not Available' : $value;
}

// Get existing thesis submissions for this team
$thesisHistory = [];
if (isset($teamID)) {
    $sql = "SELECT td.ID, td.Topic, td.Semester, td.content_name, td.content_type, td.content_size 
            FROM thesis_document td 
            WHERE td.TeamID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teamID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $thesisHistory[] = $row;
    }
}

// Process submission form
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_thesis'])) {
    // Validate inputs
    $submissionType = $_POST['submission_type'] ?? '';
    $thesisTitle = $_POST['submission_title'] ?? '';
    $description = $_POST['submission_description'] ?? '';
    
    if (empty($submissionType) || empty($thesisTitle)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!isset($_FILES['thesis_file']) || $_FILES['thesis_file']['error'] == UPLOAD_ERR_NO_FILE) {
        $message = "Please upload a thesis file.";
        $messageType = "error";
    } else {
        // Check for file upload errors with detailed messages
        if ($_FILES['thesis_file']['error'] !== UPLOAD_ERR_OK) {
            switch ($_FILES['thesis_file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $message = "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = "Missing a temporary folder for upload.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $message = "A PHP extension stopped the file upload.";
                    break;
                default:
                    $message = "Unknown upload error occurred.";
            }
            $messageType = "error";
        } else {
            // Check file type
            $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $fileType = $_FILES['thesis_file']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $message = "Invalid file type. Please upload PDF, DOC, or DOCX files.";
                $messageType = "error";
            } 
            // Check file size (20MB max)
            elseif ($_FILES['thesis_file']['size'] > 20 * 1024 * 1024) {
                $message = "File is too large. Maximum size is 20MB.";
                $messageType = "error";
            } else {
                // Get semester (e.g., Spring2025, Fall2025)
                $currentMonth = date('n');
                $currentYear = date('Y');
                
                if ($currentMonth >= 1 && $currentMonth <= 4) {
                    $semester = "Spring" . $currentYear;
                } elseif ($currentMonth >= 5 && $currentMonth <= 8) {
                    $semester = "Summer" . $currentYear;
                } else {
                    $semester = "Fall" . $currentYear;
                }
                
                // Get supervisor for this team
                $sql = "SELECT Initial FROM thesis_team WHERE Team_ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $teamID);
                $stmt->execute();
                $supervisorResult = $stmt->get_result();
                $supervisorData = $supervisorResult->fetch_assoc();
                $supervisor = $supervisorData ? $supervisorData['Initial'] : null;
                
                // Get co-supervisor if any
                $sql = "SELECT cinitial FROM thesis_team WHERE Team_ID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $teamID);
                $stmt->execute();
                $coSupervisorResult = $stmt->get_result();
                $coSupervisorData = $coSupervisorResult->fetch_assoc();
                $coSupervisor = $coSupervisorData ? $coSupervisorData['cinitial'] : null;
                
                // File details
                $fileName = $_FILES['thesis_file']['name'];
                $fileSize = $_FILES['thesis_file']['size'];
                $fileTmpName = $_FILES['thesis_file']['tmp_name'];
                
                // Read file content - using binary safe method
                $fileContent = null;
                $fp = fopen($fileTmpName, 'rb');
                if ($fp) {
                    $fileContent = fread($fp, filesize($fileTmpName));
                    fclose($fp);
                } else {
                    $message = "Error reading the uploaded file.";
                    $messageType = "error";
                }
                
                if ($fileContent !== null) {
                    // Use a transaction for data integrity
                    $conn->begin_transaction();
                    
                    try {
                        // Insert into database - using proper parameter binding for binary data
                        $sql = "INSERT INTO thesis_document (TeamID, Topic, Semester, Supervisor, Co_Supervisor, content, content_name, content_type, content_size, category_status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);

                        // Bind parameters - note that for binary data, we still use 's' (string)
                        // There's no specific 'b' type in mysqli bind_param
                        $stmt->bind_param("isssssssss", 
                            $teamID, 
                            $thesisTitle, 
                            $semester, 
                            $supervisor, 
                            $coSupervisor, 
                            $fileContent, 
                            $fileName, 
                            $fileType, 
                            $fileSize,
                            $submissionType
                        );
                        
                        if ($stmt->execute()) {
                            // Commit the transaction
                            $conn->commit();
                            
                            $message = "Thesis submitted successfully!";
                            $messageType = "success";
                            
                            // Refresh the thesis history
                            $sql = "SELECT td.ID, td.Topic, td.Semester, td.content_name, td.content_type, td.content_size 
                                    FROM thesis_document td 
                                    WHERE td.TeamID = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $teamID);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $thesisHistory = [];
                            while ($row = $result->fetch_assoc()) {
                                $thesisHistory[] = $row;
                            }
                        } else {
                            // Rollback if there was an error
                            $conn->rollback();
                            $message = "Database Error: " . $stmt->error;
                            $messageType = "error";
                        }
                    } catch (Exception $e) {
                        // Rollback on exception
                        $conn->rollback();
                        $message = "Error: " . $e->getMessage();
                        $messageType = "error";
                    }
                }
            }
        }
    }
}

// Close the database connection
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Submit Thesis - Thesis Management System</title>
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

    .sidebar a:hover {
      background-color: #c0ddf0;
    }

    .sidebar a.active {
      background-color: #0055cc;
      color: white;
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

    .user-info {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
    }

    /* Content area */
    .content {
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
      overflow-y: auto;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }

    /* Submission Form */
    .submission-container {
      width: 100%;
      max-width: 800px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 25px;
    }

    .submission-header {
      margin-bottom: 25px;
    }

    .submission-title {
      font-size: 24px;
      font-weight: bold;
      color: #004080;
      margin-bottom: 10px;
    }

    .submission-description {
      color: #666;
      line-height: 1.5;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: bold;
      color: #004080;
      margin-bottom: 8px;
    }

    .form-control {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-family: inherit;
      font-size: 14px;
    }

    textarea.form-control {
      resize: vertical;
      min-height: 100px;
    }

    .form-file-upload {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .file-upload-area {
      border: 2px dashed #b3d9ff;
      border-radius: 6px;
      padding: 20px;
      text-align: center;
      background-color: #f8fbff;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .file-upload-area:hover {
      background-color: #e6f2ff;
    }

    .file-upload-icon {
      font-size: 24px;
      color: #0055cc;
      margin-bottom: 10px;
    }

    .file-upload-text {
      color: #666;
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 25px;
    }

    .btn {
      padding: 10px 20px;
      border-radius: 4px;
      border: none;
      font-size: 14px;
      cursor: pointer;
      font-weight: bold;
    }

    .btn-primary {
      background-color: #0055cc;
      color: white;
    }

    .btn-primary:hover {
      background-color: #0044aa;
    }

    .btn-secondary {
      background-color: #6c757d;
      color: white;
    }

    .btn-secondary:hover {
      background-color: #5a6268;
    }

    /* Submission history */
    .submission-history {
      margin-top: 30px;
      border-top: 1px solid #e6f2ff;
      padding-top: 20px;
    }

    .history-title {
      font-size: 18px;
      font-weight: bold;
      color: #004080;
      margin-bottom: 15px;
    }

    .history-table {
      width: 100%;
      border-collapse: collapse;
    }

    .history-table th, 
    .history-table td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #e6f2ff;
    }

    .history-table th {
      background-color: #f0f7ff;
      color: #004080;
      font-weight: bold;
    }

    /* Alert messages */
    .alert {
      padding: 12px 20px;
      border-radius: 6px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .alert-close {
      background: none;
      border: none;
      color: inherit;
      font-size: 18px;
      cursor: pointer;
    }

    /* File size formatting helper */
    .file-size {
      color: #666;
      font-size: 0.8em;
    }
  </style>
</head>
<body>

  <div class="sidebar">
  <a href="student_dash.php">Dashboard</a>
    <a href="teamsearch.php">Team Search</a>
    <a href="supervisor.php">Supervisor</a>
    <a href="cosupervisor.php">Co-Supervisor</a>
    <a href="get_schedule2.php">Schedule</a>
    <a href="progressreport.php">Report Progress</a>
    <a href="submit_thesis.php" class="active">Submit Thesis</a>
    <a href="student_feedback.php">Feedback</a>
    <a href="thesisDB.php">Thesis Database</a>
  </div>

  <div class="main">
    <div class="topbar">
      <h1>THESIS MANAGEMENT SYSTEM</h1>
      <div class="user-info">
        <span><?php echo isset($studentData['Name']) ? $studentData['Name'] : 'Student'; ?></span>
      </div>
    </div>

    <div class="date-display">
      <?php echo date('Y-m-d H:i:s'); ?> UTC
    </div>

    <div class="content">
      <div class="submission-container">
        <?php if ($message): ?>
          <div class="alert alert-<?php echo $messageType; ?>">
            <div><?php echo $message; ?></div>
            <button class="alert-close" onclick="this.parentElement.style.display='none'">&times;</button>
          </div>
          <?php if ($messageType == 'success'): ?>
            <div style="text-align: center; margin-bottom: 20px;">
              <a href="student_dash.php" class="btn btn-primary">Return to Dashboard</a>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!isset($teamID)): ?>
          <div class="alert alert-error">
            You need to be part of a team before submitting a thesis. Please join or create a team first.
          </div>
          <div style="text-align: center; margin-top: 20px;">
            <a href="student_dash.php" class="btn btn-primary">Return to Dashboard</a>
          </div>
        <?php else: ?>
          <div class="submission-header">
            <div class="submission-title">Submit Thesis or Draft</div>
            <div class="submission-description">
              Upload your thesis document or draft for review by your supervisor. 
              Please ensure your document follows the required formatting guidelines.
            </div>
          </div>

          <form id="thesis-submission-form" method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label for="submission-type">Submission Type</label>
              <select class="form-control" id="submission-type" name="submission_type" required>
                <option value="">Select submission type</option>
                <option value="proposal">Thesis Proposal</option>
                <option value="draft">Draft Submission</option>
                <option value="final">Final Thesis</option>
                <option value="revision">Revision</option>
              </select>
            </div>

            <div class="form-group">
              <label for="submission-title">Thesis Title</label>
              <input type="text" class="form-control" id="submission-title" name="submission_title" placeholder="Enter the title of your thesis" required>
            </div>

            <div class="form-group">
              <label for="submission-description">Description / Notes</label>
              <textarea class="form-control" id="submission-description" name="submission_description" placeholder="Add any notes or comments for your supervisor"></textarea>
            </div>

            <div class="form-group">
              <label>Upload Thesis Document</label>
              <div class="form-file-upload">
                <div class="file-upload-area" id="file-upload-area">
                  <div class="file-upload-icon">📄</div>
                  <div class="file-upload-text">
                    Drag & drop your file here or <span style="color: #0055cc;">Browse</span>
                    <div style="font-size: 12px; margin-top: 8px;">Accepted formats: .pdf, .doc, .docx (Max 20MB)</div>
                  </div>
                </div>
                <input type="file" id="file-input" name="thesis_file" class="file-input" accept=".pdf,.doc,.docx" style="display: none;">
              </div>
              <div id="selected-file" style="margin-top: 10px; font-size: 14px;"></div>
            </div>

            <div class="form-actions">
              <button type="submit" name="submit_thesis" class="btn btn-primary">Submit Thesis</button>
            </div>
          </form>

          <div class="submission-history">
            <div class="history-title">Submission History</div>
            <?php if (empty($thesisHistory)): ?>
              <p>No previous submissions found.</p>
            <?php else: ?>
              <table class="history-table">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Semester</th>
                    <th>File</th>
                    <th>Size</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($thesisHistory as $submission): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($submission['Topic']); ?></td>
                      <td><?php echo htmlspecialchars($submission['Semester']); ?></td>
                      <td><?php echo htmlspecialchars($submission['content_name']); ?></td>
                      <td>
                        <span class="file-size">
                          <?php 
                            // Format file size
                            $size = $submission['content_size'];
                            if ($size < 1024) {
                              echo $size . ' B';
                            } elseif ($size < 1048576) {
                              echo round($size / 1024, 2) . ' KB';
                            } else {
                              echo round($size / 1048576, 2) . ' MB';
                            }
                          ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const fileUploadArea = document.getElementById('file-upload-area');
      const fileInput = document.getElementById('file-input');
      const selectedFileDiv = document.getElementById('selected-file');
      
      // Trigger file input when upload area is clicked
      if (fileUploadArea) {
        fileUploadArea.addEventListener('click', () => {
          fileInput.click();
        });
        
        // Handle drag and drop
        fileUploadArea.addEventListener('dragover', (e) => {
          e.preventDefault();
          fileUploadArea.style.backgroundColor = '#e6f2ff';
        });
        
        fileUploadArea.addEventListener('dragleave', () => {
          fileUploadArea.style.backgroundColor = '#f8fbff';
        });
        
        fileUploadArea.addEventListener('drop', (e) => {
          e.preventDefault();
          fileUploadArea.style.backgroundColor = '#f8fbff';
          if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateSelectedFile(e.dataTransfer.files[0]);
          }
        });
      }
      
      // Handle file selection
      if (fileInput) {
        fileInput.addEventListener('change', () => {
          if (fileInput.files.length) {
            updateSelectedFile(fileInput.files[0]);
          }
        });
      }
      
      function updateSelectedFile(file) {
        // Display selected file name
        selectedFileDiv.innerHTML = `
          <div style="display: flex; align-items: center; background-color: #f0f7ff; padding: 8px 12px; border-radius: 4px;">
            <div style="margin-right: 10px;">📄</div>
            <div>
              <div>${file.name}</div>
              <div style="font-size: 12px; color: #666;">
                ${formatFileSize(file.size)} - ${file.type || 'Unknown type'}
              </div>
            </div>
            <button type="button" style="margin-left: auto; background: none; border: none; color: #dc3545; cursor: pointer;" 
                    onclick="clearSelectedFile()">×</button>
          </div>
        `;
      }
      
      // Format file size
      function formatFileSize(bytes) {
        if (bytes < 1024) {
          return bytes + ' B';
        } else if (bytes < 1048576) {
          return (bytes / 1024).toFixed(2) + ' KB';
        } else {
          return (bytes / 1048576).toFixed(2) + ' MB';
        }
      }
      
      // Define the clearSelectedFile function globally
      window.clearSelectedFile = function() {
        fileInput.value = '';
        selectedFileDiv.innerHTML = '';
      };
    });
  </script>
</body>
</html>
