<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'MVC Framework') ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
        .framework-info {
            background: #e8f4fd;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= htmlspecialchars($title ?? 'Welcome') ?></h1>
        <p><?= htmlspecialchars($message ?? 'Welcome to your fresh MVC framework!') ?></p>
        
        <div class="framework-info">
            <h3>Getting Started</h3>
            <p>Your MVC framework is now clean and ready for development:</p>
            <ul style="text-align: left; display: inline-block;">
                <li>Create controllers in <code>app/Controllers/</code></li>
                <li>Create models in <code>app/Models/</code></li>
                <li>Create views in <code>app/Views/</code></li>
                <li>Define routes in <code>config/routes.php</code></li>
            </ul>
        </div>
    </div>
</body>
</html>
