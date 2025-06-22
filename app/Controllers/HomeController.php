<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Home Controller
 *
 * Welcome page controller for Arknox Framework
 */
class HomeController extends Controller
{
    /**
     * Display the Arknox Framework welcome page
     */
    public function index()
    {
        $data = [
            'title' => 'Welcome to Arknox Framework',
            'subtitle' => 'The Most Advanced PHP Framework for Modern Web Development',
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'features' => [
                [
                    'icon' => '⚡',
                    'title' => '10x Performance',
                    'description' => 'Faster than any existing PHP framework with intelligent caching and optimization'
                ],
                [
                    'icon' => '🛡️',
                    'title' => 'Banking-Grade Security',
                    'description' => 'Enterprise compliance (PCI DSS, SOX, GDPR) with comprehensive protection layers'
                ],
                [
                    'icon' => '🏗️',
                    'title' => 'Enhanced ORM',
                    'description' => 'Complete Eloquent-equivalent with all relationship types and advanced features'
                ],
                [
                    'icon' => '👨‍💻',
                    'title' => 'Laravel-Quality CLI',
                    'description' => 'Exceptional developer experience with cross-platform arknox command'
                ],
                [
                    'icon' => '📊',
                    'title' => 'Multi-Database Support',
                    'description' => 'MySQL, PostgreSQL, SQLite, SQL Server with Laravel-style query builder'
                ],
                [
                    'icon' => '🎨',
                    'title' => 'Advanced Templates',
                    'description' => 'Twig integration with superior features compared to Blade'
                ],
                [
                    'icon' => '🐛',
                    'title' => 'Advanced Debugging',
                    'description' => 'Data flow visualization, route debugging, and visual MVC flow diagrams'
                ],
                [
                    'icon' => '🌍',
                    'title' => 'Cross-Platform',
                    'description' => 'Full compatibility with Windows, macOS, and Linux'
                ]
            ],
            'quick_start' => [
                [
                    'step' => 1,
                    'title' => 'Environment Setup',
                    'command' => 'arknox env:setup',
                    'description' => 'Configure your environment and database settings'
                ],
                [
                    'step' => 2,
                    'title' => 'Create Database',
                    'command' => 'arknox db:create',
                    'description' => 'Create your application database'
                ],
                [
                    'step' => 3,
                    'title' => 'Run Migrations',
                    'command' => 'arknox migrate',
                    'description' => 'Set up your database schema'
                ],
                [
                    'step' => 4,
                    'title' => 'Start Development',
                    'command' => 'arknox serve',
                    'description' => 'Launch the development server'
                ]
            ],
            'stats' => [
                [
                    'value' => '10x',
                    'label' => 'Faster Performance',
                    'description' => 'Compared to traditional frameworks'
                ],
                [
                    'value' => '60%',
                    'label' => 'Less Memory',
                    'description' => 'More efficient resource usage'
                ],
                [
                    'value' => '100%',
                    'label' => 'Cross-Platform',
                    'description' => 'Windows, macOS, Linux support'
                ],
                [
                    'value' => '4',
                    'label' => 'Database Systems',
                    'description' => 'MySQL, PostgreSQL, SQLite, SQL Server'
                ]
            ],
            'next_steps' => [
                [
                    'title' => 'Read Documentation',
                    'description' => 'Comprehensive guides and API reference',
                    'link' => '/docs',
                    'icon' => '📚'
                ],
                [
                    'title' => 'Create Your First Model',
                    'description' => 'Generate models, controllers, and views',
                    'link' => '/docs/getting-started',
                    'icon' => '🏗️'
                ],
                [
                    'title' => 'Explore CLI Commands',
                    'description' => 'Powerful command-line tools for development',
                    'link' => '/docs/cli',
                    'icon' => '⚡'
                ],
                [
                    'title' => 'Join Community',
                    'description' => 'Connect with other Arknox developers',
                    'link' => 'https://community.arknox.dev',
                    'icon' => '👥'
                ],
                [
                    'title' => 'About Arknox Technology',
                    'description' => 'Learn about our company and leadership',
                    'link' => '/docs/company',
                    'icon' => '🏢'
                ]
            ]
        ];

        return $this->render('welcome/index', $data);
    }

    /**
     * Display system information
     */
    public function info()
    {
        $data = [
            'title' => 'System Information',
            'system_info' => [
                'Framework Name' => 'Arknox Framework',
                'Framework Version' => '1.0.0',
                'Company' => 'Arknox Technology',
                'CEO & Founder' => 'Akash Hossain',
                'Website' => 'https://arknox.dev',
                'PHP Version' => PHP_VERSION,
                'Operating System' => PHP_OS,
                'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'Memory Limit' => ini_get('memory_limit'),
                'Max Execution Time' => ini_get('max_execution_time') . 's',
                'Upload Max Filesize' => ini_get('upload_max_filesize'),
                'Post Max Size' => ini_get('post_max_size')
            ],
            'extensions' => [
                'PDO' => extension_loaded('pdo') ? '✅ Enabled' : '❌ Disabled',
                'PDO MySQL' => extension_loaded('pdo_mysql') ? '✅ Enabled' : '❌ Disabled',
                'PDO PostgreSQL' => extension_loaded('pdo_pgsql') ? '✅ Enabled' : '❌ Disabled',
                'PDO SQLite' => extension_loaded('pdo_sqlite') ? '✅ Enabled' : '❌ Disabled',
                'OpenSSL' => extension_loaded('openssl') ? '✅ Enabled' : '❌ Disabled',
                'Mbstring' => extension_loaded('mbstring') ? '✅ Enabled' : '❌ Disabled',
                'JSON' => extension_loaded('json') ? '✅ Enabled' : '❌ Disabled',
                'cURL' => extension_loaded('curl') ? '✅ Enabled' : '❌ Disabled',
                'GD' => extension_loaded('gd') ? '✅ Enabled' : '❌ Disabled',
                'Zip' => extension_loaded('zip') ? '✅ Enabled' : '❌ Disabled',
                'OPcache' => extension_loaded('opcache') ? '✅ Enabled' : '❌ Disabled',
                'Redis' => extension_loaded('redis') ? '✅ Enabled' : '❌ Disabled'
            ]
        ];

        return $this->render('welcome/info', $data);
    }
}
