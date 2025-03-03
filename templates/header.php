<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">
    <script src="https://www.google.com/recaptcha/api.js?render=6LeZ7ZkqAAAAAPqof5pJXtCDyLU6W6cIv5M-QGfM"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.13.216/pdf.min.js"></script>
    <title>Pharos Health Exposure Control Tool</title>
</head>
<body>
    <?php if ($user): ?>
        <div class="top-bar">
            <div class="greeting">
                Hi, <?php echo htmlspecialchars($user['first_name']); ?>:
            </div>
            <div class="actions">
                <a href="?page=dashboard" class="button return-dashboard-button">Return to Dashboard</a>
                <a href="logout.php" class="button logout">Log Out</a>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="container" style="padding-top: 60px;">
