<?php include 'layouts/header.php'; ?>

<style>
    .hero-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 80px 0;
        text-align: center;
    }
    .hero-logo {
        max-width: 200px;
        margin-bottom: 30px;
        filter: brightness(0) invert(1);
    }
    .hero-title {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 20px;
    }
    .hero-subtitle {
        font-size: 1.5rem;
        margin-bottom: 30px;
        opacity: 0.9;
    }
    .version-badge {
        background: rgba(255,255,255,0.2);
        padding: 8px 16px;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 40px;
    }
    .feature-card {
        background: white;
        border-radius: 10px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-5px);
    }
    .feature-icon {
        font-size: 3rem;
        margin-bottom: 20px;
    }
    .stats-section {
        background: #f8f9fa;
        padding: 60px 0;
    }
    .stat-card {
        text-align: center;
        padding: 30px;
    }
    .stat-value {
        font-size: 3rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 10px;
    }
    .stat-label {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 5px;
    }
    .quick-start {
        background: white;
        padding: 60px 0;
    }
    .step-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
    }
    .step-number {
        background: #667eea;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 15px;
    }
    .next-steps {
        background: #667eea;
        color: white;
        padding: 60px 0;
    }
    .next-step-card {
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        transition: background 0.3s ease;
    }
    .next-step-card:hover {
        background: rgba(255,255,255,0.2);
    }
    .next-step-card a {
        color: white;
        text-decoration: none;
    }
    .btn-primary {
        background: #667eea;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-primary:hover {
        background: #5a6fd8;
        transform: translateY(-2px);
    }
    .btn-outline-light {
        border: 2px solid white;
        color: white;
        padding: 12px 30px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    .btn-outline-light:hover {
        background: white;
        color: #667eea;
    }
</style>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <img src="img/arknox.png" alt="Arknox Framework" class="hero-logo">
        <h1 class="hero-title"><?= htmlspecialchars($title) ?></h1>
        <p class="hero-subtitle"><?= htmlspecialchars($subtitle) ?></p>
        <div class="version-badge">
            Version <?= htmlspecialchars($version) ?> • PHP <?= htmlspecialchars($php_version) ?>
        </div>
        <div class="mt-4">
            <a href="/docs" class="btn btn-outline-light me-3">📚 Documentation</a>
            <a href="/docs/getting-started" class="btn btn-primary">🚀 Get Started</a>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-4 fw-bold">Why Choose Arknox Framework?</h2>
            <p class="lead">Revolutionary features that set us apart from other PHP frameworks</p>
        </div>
        
        <div class="row">
            <?php foreach ($features as $feature): ?>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="feature-card text-center h-100">
                    <div class="feature-icon"><?= $feature['icon'] ?></div>
                    <h5 class="fw-bold"><?= htmlspecialchars($feature['title']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($feature['description']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Performance That Speaks</h2>
            <p class="lead">Numbers that prove our superiority</p>
        </div>
        
        <div class="row">
            <?php foreach ($stats as $stat): ?>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-value"><?= htmlspecialchars($stat['value']) ?></div>
                    <div class="stat-label"><?= htmlspecialchars($stat['label']) ?></div>
                    <p class="text-muted small"><?= htmlspecialchars($stat['description']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Quick Start Section -->
<section class="quick-start">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Quick Start Guide</h2>
            <p class="lead">Get up and running in minutes</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php foreach ($quick_start as $step): ?>
                <div class="step-card">
                    <div class="d-flex align-items-center">
                        <span class="step-number"><?= $step['step'] ?></span>
                        <div>
                            <h5 class="mb-2"><?= htmlspecialchars($step['title']) ?></h5>
                            <code class="bg-dark text-light p-2 rounded d-block mb-2"><?= htmlspecialchars($step['command']) ?></code>
                            <p class="text-muted mb-0"><?= htmlspecialchars($step['description']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Next Steps Section -->
<section class="next-steps">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">What's Next?</h2>
            <p class="lead">Continue your Arknox journey</p>
        </div>
        
        <div class="row">
            <?php foreach ($next_steps as $step): ?>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="next-step-card text-center h-100">
                    <a href="<?= htmlspecialchars($step['link']) ?>">
                        <div class="mb-3" style="font-size: 3rem;"><?= $step['icon'] ?></div>
                        <h5 class="fw-bold"><?= htmlspecialchars($step['title']) ?></h5>
                        <p class="mb-0"><?= htmlspecialchars($step['description']) ?></p>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Company Information Section -->
<section class="py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h3 class="fw-bold mb-3">Proudly Developed by Arknox Technology</h3>
                <p class="lead mb-3">
                    Under the visionary leadership of <strong>CEO Akash Hossain</strong>,
                    Arknox Technology is revolutionizing web development with cutting-edge frameworks and tools.
                </p>
                <div class="d-flex flex-wrap gap-4 mb-3">
                    <div>
                        <strong>🏢 Company:</strong> Arknox Technology
                    </div>
                    <div>
                        <strong>👨‍💼 CEO & Founder:</strong> Akash Hossain
                    </div>
                    <div>
                        <strong>🌐 Website:</strong> <a href="https://arknox.dev" class="text-white">arknox.dev</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-center">
                <img src="img/arknox.png" alt="Arknox Technology"
                     style="max-width: 150px; filter: brightness(0) invert(1);">
            </div>
        </div>
    </div>
</section>

<!-- Footer CTA -->
<section class="py-5 bg-light">
    <div class="container text-center">
        <h3 class="fw-bold mb-3">Ready to Build Something Amazing?</h3>
        <p class="lead mb-4">Join thousands of developers who chose Arknox Framework</p>
        <div>
            <a href="/docs/installation" class="btn btn-primary btn-lg me-3">Install Now</a>
            <a href="/info" class="btn btn-outline-primary btn-lg">System Info</a>
        </div>
        <div class="mt-4">
            <small class="text-muted">
                A product of <strong>Arknox Technology</strong> |
                CEO: <strong>Akash Hossain</strong> |
                <a href="https://arknox.dev" class="text-decoration-none">arknox.dev</a>
            </small>
        </div>
    </div>
</section>

<script>
    // Add some interactive effects
    document.addEventListener('DOMContentLoaded', function() {
        // Animate feature cards on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        // Observe feature cards
        document.querySelectorAll('.feature-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    });
</script>

<?php include 'layouts/footer.php'; ?>
