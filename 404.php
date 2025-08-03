<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Page Not Found</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root {
      --bg-light: #f5f9ff;
      --bg-dark: #1e1f24;
      --text-light: #001122;
      --text-dark: #f5f5f5;
      --primary: #004;
      --card: #fff;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: var(--bg-light);
      color: var(--text-light);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      transition: 0.3s ease-in-out;
    }

    body.dark {
      background-color: var(--bg-dark);
      color: var(--text-dark);
    }

    .error-box {
      max-width: 500px;
      padding: 30px;
      background: var(--card);
      border-radius: 14px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }

    .error-code {
      font-size: 90px;
      font-weight: 800;
      color: var(--primary);
    }

    .error-message {
      font-size: 20px;
      margin-top: 10px;
      color: #444;
    }

    .back-btn {
      display: inline-block;
      margin-top: 25px;
      padding: 10px 24px;
      background-color: var(--primary);
      color: #fff;
      border-radius: 8px;
      font-size: 15px;
      text-decoration: none;
      transition: 0.3s;
    }

    .back-btn:hover {
      background-color: #004;
    }
  </style>
</head>
<body>
  <div class="error-box">
    <div class="error-code">404</div>
    <div class="error-message">Oops 😢 The page you're looking for doesn't exist.</div>
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
  </div>

</body>
<?php include('footer.php'); ?>
</html>
