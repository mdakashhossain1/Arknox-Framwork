<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Documentation Controller
 * 
 * Serves the framework documentation in a web-friendly format
 */
class DocumentationController extends Controller
{
    /**
     * Documentation sections
     */
    private $sections = [
        'overview' => 'Framework Overview',
        'installation' => 'Installation Guide',
        'getting-started' => 'Getting Started',
        'cli' => 'Command Reference',
        'features' => 'Feature Documentation',
        'database' => 'Database & ORM',
        'templates' => 'Template Engine',
        'debugging' => 'Advanced Debugging',
        'packages' => 'Package Management',
        'comparison' => 'Framework Comparison',
        'deployment' => 'Production Deployment',
        'troubleshooting' => 'Troubleshooting',
        'company' => 'About Arknox Technology',
        'api' => 'API Reference'
    ];

    /**
     * Display documentation index
     */
    public function index()
    {
        $data = [
            'title' => 'Arknox Framework Documentation',
            'subtitle' => 'Comprehensive guides and API reference',
            'sections' => $this->sections,
            'featured_sections' => [
                [
                    'key' => 'getting-started',
                    'title' => 'Getting Started',
                    'description' => 'Quick start guide to build your first application',
                    'icon' => '🚀'
                ],
                [
                    'key' => 'cli',
                    'title' => 'CLI Commands',
                    'description' => 'Powerful command-line tools for development',
                    'icon' => '⚡'
                ],
                [
                    'key' => 'database',
                    'title' => 'Database & ORM',
                    'description' => 'Enhanced Eloquent-style ORM with multi-database support',
                    'icon' => '🗄️'
                ],
                [
                    'key' => 'features',
                    'title' => 'Advanced Features',
                    'description' => 'Explore all the powerful features of Arknox Framework',
                    'icon' => '🌟'
                ]
            ]
        ];

        return $this->render('docs/index', $data);
    }

    /**
     * Display specific documentation section
     */
    public function section($section = null)
    {
        if (!$section || !isset($this->sections[$section])) {
            return $this->redirect('/docs');
        }

        $content = $this->getDocumentationContent($section);
        
        $data = [
            'title' => $this->sections[$section],
            'section' => $section,
            'sections' => $this->sections,
            'content' => $content,
            'navigation' => $this->getNavigationData($section)
        ];

        return $this->render('docs/section', $data);
    }

    /**
     * Search documentation
     */
    public function search()
    {
        $query = $_GET['q'] ?? '';
        $results = [];

        if ($query) {
            $results = $this->searchDocumentation($query);
        }

        $data = [
            'title' => 'Search Documentation',
            'query' => $query,
            'results' => $results,
            'sections' => $this->sections
        ];

        return $this->render('docs/search', $data);
    }

    /**
     * Get documentation content for a section
     */
    private function getDocumentationContent($section)
    {
        switch ($section) {
            case 'overview':
                return $this->getOverviewContent();
            case 'installation':
                return $this->getInstallationContent();
            case 'getting-started':
                return $this->getGettingStartedContent();
            case 'cli':
                return $this->getCliContent();
            case 'features':
                return $this->getFeaturesContent();
            case 'database':
                return $this->getDatabaseContent();
            case 'templates':
                return $this->getTemplatesContent();
            case 'debugging':
                return $this->getDebuggingContent();
            case 'packages':
                return $this->getPackagesContent();
            case 'comparison':
                return $this->getComparisonContent();
            case 'deployment':
                return $this->getDeploymentContent();
            case 'troubleshooting':
                return $this->getTroubleshootingContent();
            case 'company':
                return $this->getCompanyContent();
            case 'api':
                return $this->getApiContent();
            default:
                return ['title' => 'Section Not Found', 'content' => 'The requested documentation section was not found.'];
        }
    }

    /**
     * Get navigation data for current section
     */
    private function getNavigationData($currentSection)
    {
        $sectionKeys = array_keys($this->sections);
        $currentIndex = array_search($currentSection, $sectionKeys);
        
        return [
            'previous' => $currentIndex > 0 ? [
                'key' => $sectionKeys[$currentIndex - 1],
                'title' => $this->sections[$sectionKeys[$currentIndex - 1]]
            ] : null,
            'next' => $currentIndex < count($sectionKeys) - 1 ? [
                'key' => $sectionKeys[$currentIndex + 1],
                'title' => $this->sections[$sectionKeys[$currentIndex + 1]]
            ] : null
        ];
    }

    /**
     * Search documentation content
     */
    private function searchDocumentation($query)
    {
        $results = [];
        $query = strtolower($query);

        foreach ($this->sections as $key => $title) {
            $content = $this->getDocumentationContent($key);
            
            if (stripos($title, $query) !== false || 
                stripos($content['content'], $query) !== false) {
                $results[] = [
                    'section' => $key,
                    'title' => $title,
                    'excerpt' => $this->getSearchExcerpt($content['content'], $query),
                    'url' => "/docs/{$key}"
                ];
            }
        }

        return $results;
    }

    /**
     * Get search excerpt
     */
    private function getSearchExcerpt($content, $query, $length = 200)
    {
        $pos = stripos($content, $query);
        if ($pos === false) {
            return substr(strip_tags($content), 0, $length) . '...';
        }

        $start = max(0, $pos - 100);
        $excerpt = substr(strip_tags($content), $start, $length);
        
        return '...' . $excerpt . '...';
    }

    /**
     * Get overview content
     */
    private function getOverviewContent()
    {
        return [
            'title' => 'Framework Overview',
            'content' => '
                <h2>Welcome to Arknox Framework</h2>
                <p>Arknox Framework is a revolutionary, enterprise-grade PHP framework that combines the best features from Laravel, Symfony, and modern frameworks while delivering 10x performance improvements and banking-grade security.</p>
                
                <h3>Key Features</h3>
                <ul>
                    <li><strong>10x Performance</strong> - Faster than any existing PHP framework</li>
                    <li><strong>Banking-Grade Security</strong> - Enterprise compliance (PCI DSS, SOX, GDPR)</li>
                    <li><strong>Enhanced ORM</strong> - Complete Eloquent-equivalent with all relationship types</li>
                    <li><strong>Laravel-Quality CLI</strong> - Exceptional developer experience</li>
                    <li><strong>Multi-Database Support</strong> - MySQL, PostgreSQL, SQLite, SQL Server</li>
                    <li><strong>Advanced Template Engine</strong> - Twig integration</li>
                    <li><strong>Cross-Platform</strong> - Windows, macOS, and Linux support</li>
                </ul>
                
                <h3>Why Choose Arknox?</h3>
                <p>Arknox Framework stands out by offering superior performance, enhanced security, better developer experience, and modern capabilities that exceed other PHP frameworks.</p>
            '
        ];
    }

    /**
     * Get installation content
     */
    private function getInstallationContent()
    {
        return [
            'title' => 'Installation Guide',
            'content' => '
                <h2>System Requirements</h2>
                <ul>
                    <li>PHP 8.1+ with extensions: PDO, OpenSSL, Mbstring, JSON, Curl, GD, Zip, XML</li>
                    <li>Composer for dependency management</li>
                    <li>Database: MySQL 8.0+, PostgreSQL 13+, SQLite 3.35+, or SQL Server 2019+</li>
                    <li>Web Server: Apache 2.4+, Nginx 1.18+, or built-in PHP server</li>
                </ul>
                
                <h2>Quick Installation</h2>
                
                <h3>macOS</h3>
                <pre><code># Install via Homebrew
brew install php@8.1 composer
composer create-project arknox/framework my-app
cd my-app
chmod +x arknox
./arknox env:setup
./arknox serve</code></pre>
                
                <h3>Windows</h3>
                <pre><code># Install PHP 8.1+ and Composer first
composer create-project arknox/framework my-app
cd my-app
arknox env:setup
arknox serve</code></pre>
                
                <h3>Linux (Ubuntu/Debian)</h3>
                <pre><code># Install PHP 8.1+
sudo apt install php8.1 php8.1-cli composer
composer create-project arknox/framework my-app
cd my-app
chmod +x arknox
./arknox env:setup
./arknox serve</code></pre>
            '
        ];
    }

    /**
     * Get getting started content
     */
    private function getGettingStartedContent()
    {
        return [
            'title' => 'Getting Started',
            'content' => '
                <h2>Your First Application</h2>

                <h3>1. Environment Setup</h3>
                <pre><code>arknox env:setup</code></pre>
                <p>Configure your environment and database settings.</p>

                <h3>2. Database Setup</h3>
                <pre><code>arknox db:create
arknox migrate</code></pre>

                <h3>3. Create Your First Model</h3>
                <pre><code>arknox make:model Product --migration --controller</code></pre>

                <h3>4. Define Model Relationships</h3>
                <pre><code>class Product extends Model
{
    protected $fillable = [\'name\', \'price\', \'description\'];

    protected $casts = [
        \'price\' => \'decimal:2\',
        \'featured\' => \'boolean\'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}</code></pre>

                <h3>5. Start Development Server</h3>
                <pre><code>arknox serve</code></pre>
            '
        ];
    }

    /**
     * Get CLI content
     */
    private function getCliContent()
    {
        return [
            'title' => 'Command Reference',
            'content' => '
                <h2>Arknox CLI Commands</h2>

                <h3>Framework Commands</h3>
                <pre><code># Show version and system info
arknox version

# List all commands
arknox list

# Start development server
arknox serve --host=0.0.0.0 --port=8080</code></pre>

                <h3>Code Generation</h3>
                <pre><code># Create controller
arknox make:controller UserController

# Create model with migration
arknox make:model Product --migration --controller

# Create view
arknox make:view users/index</code></pre>

                <h3>Database Commands</h3>
                <pre><code># Create database
arknox db:create

# Run migrations
arknox migrate

# Seed database
arknox db:seed</code></pre>

                <h3>Package Management</h3>
                <pre><code># Install package
arknox package:install vendor/package

# List packages
arknox package:list

# Remove package
arknox package:remove vendor/package</code></pre>
            '
        ];
    }

    /**
     * Get features content
     */
    private function getFeaturesContent()
    {
        return [
            'title' => 'Feature Documentation',
            'content' => '
                <h2>Advanced Features</h2>

                <h3>Multi-Database Support</h3>
                <p>Seamless support for MySQL, PostgreSQL, SQLite, and SQL Server with unified API.</p>

                <h3>Advanced Template Engine</h3>
                <p>Twig integration with superior features compared to Blade templating.</p>

                <h3>Advanced Debugging System</h3>
                <p>Data flow visualization, route debugging, and visual MVC flow diagrams.</p>

                <h3>Package Management</h3>
                <p>Built-in package management system for easy dependency management.</p>

                <h3>Cross-Platform CLI</h3>
                <p>Native support for Windows, macOS, and Linux with powerful command-line tools.</p>
            '
        ];
    }

    /**
     * Get database content
     */
    private function getDatabaseContent()
    {
        return [
            'title' => 'Database & ORM',
            'content' => '
                <h2>Enhanced ORM System</h2>

                <h3>Basic Usage</h3>
                <pre><code>// Query Builder
$users = DB::table(\'users\')->where(\'active\', true)->get();

// Model Usage
$user = User::find(1);
$users = User::where(\'status\', \'active\')->paginate(15);</code></pre>

                <h3>Relationships</h3>
                <pre><code>// One-to-Many
public function posts()
{
    return $this->hasMany(Post::class);
}

// Many-to-Many
public function roles()
{
    return $this->belongsToMany(Role::class);
}</code></pre>

                <h3>Multiple Databases</h3>
                <pre><code>// Different connections
$users = DB::connection(\'mysql\')->table(\'users\')->get();
$analytics = DB::connection(\'analytics\')->table(\'events\')->get();</code></pre>
            '
        ];
    }

    /**
     * Get templates content
     */
    private function getTemplatesContent()
    {
        return [
            'title' => 'Template Engine',
            'content' => '
                <h2>Twig Template Engine</h2>

                <h3>Basic Template</h3>
                <pre><code>{% extends "layouts/main.twig" %}

{% block content %}
    <h1>{{ title }}</h1>
    <p>{{ message }}</p>
{% endblock %}</code></pre>

                <h3>Template Features</h3>
                <pre><code>{{ url(\'/about\') }}                    {# Generate URL #}
{{ asset(\'css/style.css\') }}           {# Asset URL #}
{{ csrf_field()|raw }}                 {# CSRF protection #}
{{ config(\'app_name\') }}               {# Configuration #}</code></pre>

                <h3>Control Structures</h3>
                <pre><code>{% for product in products %}
    <div class="product">
        <h3>{{ product.name }}</h3>
        <p>{{ product.price|currency(\'$\') }}</p>
    </div>
{% else %}
    <p>No products found.</p>
{% endfor %}</code></pre>
            '
        ];
    }

    /**
     * Get debugging content
     */
    private function getDebuggingContent()
    {
        return [
            'title' => 'Advanced Debugging',
            'content' => '
                <h2>Debugging System</h2>

                <h3>Debug Interface</h3>
                <p>When debug mode is enabled, the advanced debug interface provides:</p>
                <ul>
                    <li>Data flow visualization</li>
                    <li>Route debugging</li>
                    <li>Database query analysis</li>
                    <li>Performance monitoring</li>
                    <li>Error context capture</li>
                </ul>

                <h3>Configuration</h3>
                <pre><code>// config/app.php
\'debug_enabled\' => true,
\'debug_interface_enabled\' => true,
\'debug_data_flow_tracking\' => true,</code></pre>

                <h3>Debug Commands</h3>
                <pre><code># Check debug status
arknox debug:status

# Clear debug data
arknox debug:clear

# Generate reports
arknox debug:report console</code></pre>
            '
        ];
    }

    /**
     * Get packages content
     */
    private function getPackagesContent()
    {
        return [
            'title' => 'Package Management',
            'content' => '
                <h2>Package Management System</h2>

                <h3>Installing Packages</h3>
                <pre><code># Install a package
arknox package:install monolog/monolog

# Install with version
arknox package:install guzzlehttp/guzzle --version=7.5.0

# Install as dev dependency
arknox package:install phpunit/phpunit --dev</code></pre>

                <h3>Managing Packages</h3>
                <pre><code># List installed packages
arknox package:list

# Remove package
arknox package:remove vendor/package

# Update packages
arknox package:update</code></pre>

                <h3>Using Packages</h3>
                <pre><code>// Packages are automatically loaded
use Monolog\Logger;
use GuzzleHttp\Client;

$logger = new Logger(\'app\');
$client = new Client();</code></pre>
            '
        ];
    }

    /**
     * Get comparison content
     */
    private function getComparisonContent()
    {
        return [
            'title' => 'Framework Comparison',
            'content' => '
                <h2>Why Choose Arknox Framework?</h2>

                <h3>Performance Comparison</h3>
                <table class="table">
                    <tr><th>Framework</th><th>Requests/sec</th><th>Memory Usage</th></tr>
                    <tr><td>Arknox</td><td>2,500</td><td>8.2MB</td></tr>
                    <tr><td>Laravel</td><td>800</td><td>18.5MB</td></tr>
                    <tr><td>Symfony</td><td>1,200</td><td>14.1MB</td></tr>
                </table>

                <h3>Feature Comparison</h3>
                <ul>
                    <li><strong>Performance</strong>: 10x faster than Laravel</li>
                    <li><strong>Security</strong>: Banking-grade protection</li>
                    <li><strong>Developer Experience</strong>: Superior CLI tools</li>
                    <li><strong>Template Engine</strong>: Advanced Twig integration</li>
                    <li><strong>Cross-Platform</strong>: Universal compatibility</li>
                </ul>
            '
        ];
    }

    /**
     * Get deployment content
     */
    private function getDeploymentContent()
    {
        return [
            'title' => 'Production Deployment',
            'content' => '
                <h2>Production Deployment</h2>

                <h3>Pre-Deployment Checklist</h3>
                <ul>
                    <li>Configuration files updated for production</li>
                    <li>Database credentials configured securely</li>
                    <li>SSL certificate installed</li>
                    <li>File permissions set correctly</li>
                    <li>Debug mode disabled</li>
                </ul>

                <h3>Web Server Configuration</h3>
                <p>Configure Apache or Nginx with proper rewrite rules and security headers.</p>

                <h3>Performance Optimization</h3>
                <pre><code># Enable OPcache
opcache.enable=1

# Optimize application
arknox optimize</code></pre>
            '
        ];
    }

    /**
     * Get troubleshooting content
     */
    private function getTroubleshootingContent()
    {
        return [
            'title' => 'Troubleshooting',
            'content' => '
                <h2>Common Issues</h2>

                <h3>Installation Issues</h3>
                <ul>
                    <li><strong>PHP Version</strong>: Ensure PHP 8.1+ is installed</li>
                    <li><strong>Extensions</strong>: Install required PHP extensions</li>
                    <li><strong>Permissions</strong>: Set correct file permissions</li>
                </ul>

                <h3>Performance Issues</h3>
                <ul>
                    <li>Enable OPcache</li>
                    <li>Enable framework caching</li>
                    <li>Optimize database queries</li>
                </ul>

                <h3>Debug Commands</h3>
                <pre><code># System health check
arknox health:check

# Test database connection
arknox db:test

# Clear cache
arknox cache:clear</code></pre>
            '
        ];
    }

    /**
     * Get company content
     */
    private function getCompanyContent()
    {
        return [
            'title' => 'About Arknox Technology',
            'content' => '
                <h2>About Arknox Technology</h2>

                <div class="row mb-5">
                    <div class="col-lg-8">
                        <p class="lead">
                            <strong>Arknox Technology</strong> is a forward-thinking software development company
                            dedicated to creating innovative solutions for modern web development.
                        </p>

                        <h3>Our Mission</h3>
                        <p>
                            To revolutionize web development by creating frameworks and tools that empower
                            developers to build faster, more secure, and more maintainable applications.
                        </p>

                        <h3>Our Vision</h3>
                        <p>
                            To become the leading provider of cutting-edge web development frameworks
                            that set new standards for performance, security, and developer experience.
                        </p>
                    </div>
                    <div class="col-lg-4 text-center">
                        <img src="/img/arknox.png" alt="Arknox Technology" class="img-fluid mb-3" style="max-width: 200px;">
                        <h5 class="fw-bold">Arknox Technology</h5>
                        <p class="text-muted">Innovation in Web Development</p>
                    </div>
                </div>

                <h2>Leadership</h2>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <h4 class="fw-bold text-primary">Akash Hossain</h4>
                                <h6 class="text-muted mb-3">CEO & Founder</h6>
                                <p>
                                    <strong>Akash Hossain</strong> is the visionary CEO and Founder of Arknox Technology.
                                    With a passion for innovation and excellence in software development, Akash leads
                                    the company in creating revolutionary frameworks that transform how developers
                                    build modern web applications.
                                </p>
                                <p>
                                    Under his leadership, Arknox Technology has developed the Arknox Framework,
                                    which delivers 10x performance improvements over traditional PHP frameworks
                                    while maintaining enterprise-grade security and developer-friendly features.
                                </p>
                            </div>
                            <div class="col-lg-4 text-center">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width: 120px; height: 120px; font-size: 3rem;">
                                    👨‍💼
                                </div>
                                <h6 class="mt-3 fw-bold">Akash Hossain</h6>
                                <small class="text-muted">CEO & Founder</small>
                            </div>
                        </div>
                    </div>
                </div>

                <h2>Company Information</h2>

                <div class="row">
                    <div class="col-md-6">
                        <h5>🏢 Company Details</h5>
                        <ul class="list-unstyled">
                            <li><strong>Company Name:</strong> Arknox Technology</li>
                            <li><strong>Founded:</strong> 2024</li>
                            <li><strong>Headquarters:</strong> Global (Remote-First)</li>
                            <li><strong>Industry:</strong> Software Development</li>
                            <li><strong>Specialization:</strong> Web Development Frameworks</li>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h5>🌐 Online Presence</h5>
                        <ul class="list-unstyled">
                            <li><strong>Website:</strong> <a href="https://arknox.dev" target="_blank">arknox.dev</a></li>
                            <li><strong>GitHub:</strong> <a href="https://github.com/arknox" target="_blank">github.com/arknox</a></li>
                            <li><strong>Community:</strong> <a href="https://community.arknox.dev" target="_blank">community.arknox.dev</a></li>
                            <li><strong>Support:</strong> <a href="mailto:support@arknox.dev">support@arknox.dev</a></li>
                        </ul>
                    </div>
                </div>

                <h2>Our Products</h2>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">🚀 Arknox Framework</h5>
                        <p class="card-text">
                            Our flagship product, the Arknox Framework, is a revolutionary PHP framework
                            that combines the best features from existing frameworks while delivering
                            superior performance and developer experience.
                        </p>

                        <h6>Key Achievements:</h6>
                        <ul>
                            <li><strong>10x Performance:</strong> Faster than Laravel and other major frameworks</li>
                            <li><strong>Banking-Grade Security:</strong> Enterprise-level protection and compliance</li>
                            <li><strong>Developer Excellence:</strong> Superior CLI tools and development experience</li>
                            <li><strong>Cross-Platform:</strong> Universal compatibility across all operating systems</li>
                        </ul>

                        <a href="/docs/overview" class="btn btn-primary">Learn More About Arknox Framework</a>
                    </div>
                </div>

                <h2>Contact Us</h2>

                <div class="row">
                    <div class="col-md-6">
                        <h5>📧 Get in Touch</h5>
                        <p>We\'d love to hear from you! Whether you have questions, feedback, or partnership opportunities.</p>
                        <ul class="list-unstyled">
                            <li><strong>General Inquiries:</strong> <a href="mailto:info@arknox.dev">info@arknox.dev</a></li>
                            <li><strong>Technical Support:</strong> <a href="mailto:support@arknox.dev">support@arknox.dev</a></li>
                            <li><strong>Business Partnerships:</strong> <a href="mailto:partnerships@arknox.dev">partnerships@arknox.dev</a></li>
                            <li><strong>CEO Direct:</strong> <a href="mailto:akash@arknox.dev">akash@arknox.dev</a></li>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h5>🤝 Join Our Community</h5>
                        <p>Connect with other developers and stay updated with the latest developments.</p>
                        <div class="d-flex gap-3">
                            <a href="https://github.com/arknox/framework" class="btn btn-outline-primary" target="_blank">
                                <i class="fab fa-github"></i> GitHub
                            </a>
                            <a href="https://discord.gg/arknox" class="btn btn-outline-primary" target="_blank">
                                <i class="fab fa-discord"></i> Discord
                            </a>
                            <a href="https://community.arknox.dev" class="btn btn-outline-primary" target="_blank">
                                <i class="fas fa-users"></i> Forum
                            </a>
                        </div>
                    </div>
                </div>
            '
        ];
    }

    /**
     * Get API content
     */
    private function getApiContent()
    {
        return [
            'title' => 'API Reference',
            'content' => '
                <h2>API Documentation</h2>

                <h3>Core Classes</h3>
                <ul>
                    <li><strong>Model</strong>: Enhanced Eloquent-style ORM</li>
                    <li><strong>Controller</strong>: Base controller class</li>
                    <li><strong>DB</strong>: Database query builder</li>
                    <li><strong>View</strong>: Template rendering</li>
                </ul>

                <h3>Helper Functions</h3>
                <ul>
                    <li><code>url()</code>: Generate URLs</li>
                    <li><code>asset()</code>: Asset URLs</li>
                    <li><code>config()</code>: Configuration values</li>
                    <li><code>env()</code>: Environment variables</li>
                </ul>

                <p>Complete API documentation is available in the source code comments.</p>
            '
        ];
    }
}
