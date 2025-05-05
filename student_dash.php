<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard - Thesis Management System</title>
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
      max-width: 1000px;
      padding: 20px;
    }

    .profile-info {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
    }

    .profile-item {
      margin-bottom: 15px;
      width: 48%;
    }

    .profile-label {
      font-weight: bold;
      color: #004080;
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      width: 100%;
      max-width: 1000px;
    }

    .dashboard-item {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      padding: 20px;
    }

    .dashboard-item h3 {
      color: #0055cc;
      margin-top: 0;
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 10px;
    }

    .btn {
      background-color: #0055cc;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      display: inline-block;
    }

    .btn:hover {
      background-color: #0044aa;
    }

    /* Team table styles */
    .team-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    .team-table th {
      background-color: #e0f0ff;
      color: #004080;
      text-align: left;
      padding: 10px;
    }

    .team-table td {
      padding: 10px;
      border-bottom: 1px solid #e0e0e0;
    }

    .team-table tr:last-child td {
      border-bottom: none;
    }

    .team-table tr:hover {
      background-color: #f5f9ff;
    }

    .date-display {
      text-align: right;
      padding: 10px 20px;
      color: #666;
      font-size: 0.9em;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <a href="#">Team Search</a>
    <a href="#">Supervisor</a>
    <a href="#">Schedule</a>
    <a href="#">Notification</a>
    <a href="#">Report Progress</a>
    <a href="#">Plagiarism Checker</a>
    <a href="#">Panelists</a>
    <a href="#">Submit Thesis</a>
    <a href="#">Feedback</a>
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
      2025-05-05 19:56:07 UTC
    </div>

    <div class="content">
      <div class="header-title">
        <h2>Student Dashboard</h2>
        <p>Welcome, abrarfahim-1000</p>
      </div>

      <div class="card">
        <h3>Profile Information</h3>
        <div class="profile-info">
          <div class="profile-item">
            <div class="profile-label">Name:</div>
            <div>John Doe</div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Email:</div>
            <div>john.doe@example.com</div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Enrollment Year:</div>
            <div>2023</div>
          </div>
          <div class="profile-item">
            <div class="profile-label">Status:</div>
            <div>Active</div>
          </div>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="dashboard-item">
          <h3>My Thesis</h3>
          <p><strong>Title:</strong> Investigating AI Applications</p>
          <p><strong>Supervisor:</strong> Dr. Smith</p>
          <a href="#" class="btn">View Details</a>
        </div>

        <div class="dashboard-item">
          <h3>My Team</h3>
          <table class="team-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Student ID</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>John Doe</td>
                <td>ST12345</td>
                <td>john.doe@example.com</td>
              </tr>
              <tr>
                <td>Jane Smith</td>
                <td>ST12346</td>
                <td>jane.smith@example.com</td>
              </tr>
              <tr>
                <td>Alex Johnson</td>
                <td>ST12347</td>
                <td>alex.johnson@example.com</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</body>
</html>