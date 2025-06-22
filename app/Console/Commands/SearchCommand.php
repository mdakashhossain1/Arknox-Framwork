<?php

namespace App\Console\Commands;

/**
 * Search Command
 * 
 * Search for available packages
 */
class SearchCommand extends BaseCommand
{
    private $repositories = [
        'packagist' => 'https://packagist.org/search.json',
        'github' => 'https://api.github.com/search/repositories'
    ];
    
    public function execute($arguments)
    {
        if (empty($arguments)) {
            $this->error("Search term is required.");
            $this->info("Usage: php console search <search-term>");
            return;
        }
        
        $searchTerm = $arguments[0];
        $this->info("Searching for packages: {$searchTerm}");
        
        // For demo purposes, show some example packages
        $this->showDemoResults($searchTerm);
    }
    
    /**
     * Show demo search results
     */
    private function showDemoResults($searchTerm)
    {
        $demoPackages = [
            'monolog/monolog' => [
                'name' => 'monolog/monolog',
                'description' => 'Sends your logs to files, sockets, inboxes, databases and various web services',
                'version' => '2.8.0',
                'downloads' => '500M+',
                'stars' => '20k+'
            ],
            'guzzlehttp/guzzle' => [
                'name' => 'guzzlehttp/guzzle',
                'description' => 'Guzzle is a PHP HTTP client library',
                'version' => '7.5.0',
                'downloads' => '400M+',
                'stars' => '22k+'
            ],
            'symfony/console' => [
                'name' => 'symfony/console',
                'description' => 'Eases the creation of beautiful and testable command line interfaces',
                'version' => '6.2.0',
                'downloads' => '300M+',
                'stars' => '9k+'
            ],
            'phpunit/phpunit' => [
                'name' => 'phpunit/phpunit',
                'description' => 'The PHP Unit Testing framework',
                'version' => '10.0.0',
                'downloads' => '200M+',
                'stars' => '19k+'
            ],
            'doctrine/orm' => [
                'name' => 'doctrine/orm',
                'description' => 'Object-Relational-Mapper for PHP',
                'version' => '2.14.0',
                'downloads' => '150M+',
                'stars' => '9k+'
            ]
        ];
        
        // Filter packages based on search term
        $results = [];
        foreach ($demoPackages as $package) {
            if (stripos($package['name'], $searchTerm) !== false || 
                stripos($package['description'], $searchTerm) !== false) {
                $results[] = $package;
            }
        }
        
        if (empty($results)) {
            $this->warning("No packages found matching '{$searchTerm}'");
            $this->info("Try a different search term or check the spelling.");
            return;
        }
        
        $this->displaySearchResults($results, $searchTerm);
    }
    
    /**
     * Display search results
     */
    private function displaySearchResults($results, $searchTerm)
    {
        echo "\n";
        $this->success("Found " . count($results) . " package(s) matching '{$searchTerm}':");
        echo "\n";
        
        foreach ($results as $package) {
            echo "📦 \033[32m" . $package['name'] . "\033[0m\n";
            echo "   " . $package['description'] . "\n";
            echo "   Version: " . $package['version'] . " | Downloads: " . $package['downloads'] . " | Stars: " . $package['stars'] . "\n";
            echo "\n";
        }
        
        $this->info("To install a package, run:");
        $this->info("  php console install <package-name>");
        echo "\n";
        
        $this->info("Examples:");
        foreach (array_slice($results, 0, 3) as $package) {
            echo "  php console install " . $package['name'] . "\n";
        }
        echo "\n";
    }
}
