<?php
// courses.php - Lists all available courses with links to their detail pages
session_start();
require_once 'config.php';

// Fetch all courses
$stmt = $conn->query("SELECT * FROM courses ORDER BY title ASC");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>All Courses | TestMaster</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f5f9ff;
      margin: 0;
      padding: 40px;
    }
    .courses-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 30px;
    }
    .course-card {
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
      padding: 20px;
      transition: 0.3s;
      border-left: 5px solid #0044cc;
    }
    .course-card:hover {
      transform: scale(1.02);
    }
    .course-title {
      font-size: 18px;
      font-weight: 600;
      color: #003366;
    }
    .course-code {
      font-size: 13px;
      color: #666;
    }
    .course-description {
      margin: 10px 0;
      font-size: 14px;
      color: #444;
    }
    .view-btn {
      display: inline-block;
      background: #0044cc;
      color: white;
      padding: 8px 14px;
      text-decoration: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      transition: background 0.3s ease-in-out;
    }
    .view-btn:hover {
      background: #003099;
    }
  </style>
</head>
<body>
  <h1>📚 Available Courses</h1>
  <div class="courses-container">
    <?php foreach ($courses as $course): ?>
      <div class="course-card">
        <div class="course-title"><?= htmlspecialchars($course['title']) ?></div>
        <div class="course-code">Code: <?= htmlspecialchars($course['course_code']) ?></div>
        <div class="course-description">
          <?= substr(htmlspecialchars($course['description']), 0, 100) ?>...
        </div>
        <a href="course-details.php?id=<?= $course['id'] ?>" class="view-btn">View Course</a>
      </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
