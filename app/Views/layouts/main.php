<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'MVC Framework') ?></title>

    <!-- Add your CSS files here -->
    <link rel="stylesheet" href="/assets/css/style.css">

    <!-- Additional head content -->
    <?php if (isset($head_content)): ?>
        <?= $head_content ?>
    <?php endif; ?>
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <!-- Main Content -->
    <main>
        <?= $content ?>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Add your JavaScript files here -->
    <script src="/assets/js/app.js"></script>

    <!-- Additional scripts -->
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html>