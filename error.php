<?php
// ==============================
// CUSTOM ERROR PAGE
// ==============================

$code = $_GET['code'] ?? '404';

$messages = [
    '403' => 'Access Denied',
    '404' => 'Page Not Found',
    '500' => 'Internal Server Error'
];

$message = $messages[$code] ?? 'Unknown Error';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Error <?php echo $code; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; text-align: center; padding: 50px; }
        .error-box { background: white; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #e74c3c; }
        .btn { display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>Error <?php echo $code; ?></h1>
        <p><?php echo $message; ?></p>
        <a href="/" class="btn">Go to Home</a>
    </div>
</body>
</html>