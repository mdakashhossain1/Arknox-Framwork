<?php
/**
 * Posts Index View
 * 
 * Generated view file
 */
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <h1><?= htmlspecialchars($title ?? 'Posts Index') ?></h1>
            
            <div class="content">
                <p>Welcome to the Posts Index page!</p>
                
                <!-- Add your content here -->
                
            </div>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: -10px;
}

.col-12 {
    flex: 0 0 100%;
    padding: 10px;
}

h1 {
    color: #333;
    margin-bottom: 20px;
}

.content {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}
</style>