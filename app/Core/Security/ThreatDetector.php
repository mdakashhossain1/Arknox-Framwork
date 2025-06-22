<?php

namespace App\Core\Security;

use App\Core\Request;
use App\Core\EventDispatcher;

/**
 * Advanced Threat Detection System
 * 
 * Banking-grade threat detection with:
 * - Real-time anomaly detection
 * - Behavioral analysis
 * - IP reputation checking
 * - SQL injection detection
 * - XSS attack prevention
 * - Brute force protection
 * - Bot detection
 */
class ThreatDetector
{
    private $cache;
    private $logger;
    private $events;
    private $config;
    private $riskScore = 0;
    private $threats = [];

    // Threat severity levels
    const SEVERITY_LOW = 1;
    const SEVERITY_MEDIUM = 2;
    const SEVERITY_HIGH = 3;
    const SEVERITY_CRITICAL = 4;

    public function __construct()
    {
        $this->cache = Cache::getInstance();
        $this->logger = Logger::getInstance();
        $this->events = EventDispatcher::getInstance();
        $this->config = config('security.threat_detection', []);
    }

    /**
     * Analyze request for threats
     */
    public function analyzeRequest(Request $request)
    {
        $this->riskScore = 0;
        $this->threats = [];

        // Run all threat detection modules
        $this->detectSQLInjection($request);
        $this->detectXSS($request);
        $this->detectBruteForce($request);
        $this->detectBotActivity($request);
        $this->checkIPReputation($request);
        $this->analyzeBehavior($request);
        $this->detectAnomalies($request);

        // Calculate final risk score
        $finalScore = $this->calculateRiskScore();

        // Log and respond to threats
        if ($finalScore >= $this->config['block_threshold'] ?? 80) {
            $this->blockRequest($request, $finalScore);
        } elseif ($finalScore >= $this->config['alert_threshold'] ?? 50) {
            $this->alertThreat($request, $finalScore);
        }

        return [
            'risk_score' => $finalScore,
            'threats' => $this->threats,
            'blocked' => $finalScore >= ($this->config['block_threshold'] ?? 80)
        ];
    }

    /**
     * Detect SQL injection attempts
     */
    private function detectSQLInjection(Request $request)
    {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b.*\bWHERE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(\'|\")(\s*)(OR|AND)(\s*)(\'|\")/i',
            '/(\-\-|\#|\/\*)/i'
        ];

        $allInput = array_merge($request->all(), $request->json());
        
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->addThreat('sql_injection', self::SEVERITY_HIGH, [
                            'field' => $key,
                            'value' => substr($value, 0, 100),
                            'pattern' => $pattern
                        ]);
                        $this->riskScore += 30;
                        break;
                    }
                }
            }
        }
    }

    /**
     * Detect XSS attempts
     */
    private function detectXSS(Request $request)
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<img[^>]+src[^>]*>/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i'
        ];

        $allInput = array_merge($request->all(), $request->json());
        
        foreach ($allInput as $key => $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->addThreat('xss_attempt', self::SEVERITY_HIGH, [
                            'field' => $key,
                            'value' => substr($value, 0, 100),
                            'pattern' => $pattern
                        ]);
                        $this->riskScore += 25;
                        break;
                    }
                }
            }
        }
    }

    /**
     * Detect brute force attacks
     */
    private function detectBruteForce(Request $request)
    {
        $ip = $request->ip();
        $path = $request->path();
        
        // Check for login attempts
        if (strpos($path, 'login') !== false || strpos($path, 'auth') !== false) {
            $key = "brute_force_{$ip}";
            $attempts = $this->cache->get($key, 0);
            
            if ($attempts >= ($this->config['brute_force_threshold'] ?? 5)) {
                $this->addThreat('brute_force', self::SEVERITY_CRITICAL, [
                    'ip' => $ip,
                    'attempts' => $attempts,
                    'path' => $path
                ]);
                $this->riskScore += 50;
            }
            
            // Increment attempt counter
            $this->cache->set($key, $attempts + 1, 3600); // 1 hour window
        }
    }

    /**
     * Detect bot activity
     */
    private function detectBotActivity(Request $request)
    {
        $userAgent = $request->userAgent();
        $ip = $request->ip();
        
        // Known bot patterns
        $botPatterns = [
            '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
            '/curl/i', '/wget/i', '/python/i', '/java/i'
        ];
        
        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $this->addThreat('bot_activity', self::SEVERITY_MEDIUM, [
                    'user_agent' => $userAgent,
                    'ip' => $ip
                ]);
                $this->riskScore += 15;
                break;
            }
        }
        
        // Check request frequency
        $key = "request_freq_{$ip}";
        $requests = $this->cache->get($key, 0);
        
        if ($requests > ($this->config['request_threshold'] ?? 100)) {
            $this->addThreat('high_frequency', self::SEVERITY_HIGH, [
                'ip' => $ip,
                'requests' => $requests
            ]);
            $this->riskScore += 20;
        }
        
        $this->cache->set($key, $requests + 1, 60); // 1 minute window
    }

    /**
     * Check IP reputation
     */
    private function checkIPReputation(Request $request)
    {
        $ip = $request->ip();
        
        // Check internal blacklist
        if ($this->cache->get("blacklist_ip_{$ip}")) {
            $this->addThreat('blacklisted_ip', self::SEVERITY_CRITICAL, [
                'ip' => $ip
            ]);
            $this->riskScore += 60;
        }
        
        // Check for private/local IPs in production
        if (config('app.environment') === 'production' && $this->isPrivateIP($ip)) {
            $this->addThreat('private_ip', self::SEVERITY_MEDIUM, [
                'ip' => $ip
            ]);
            $this->riskScore += 10;
        }
    }

    /**
     * Analyze behavioral patterns
     */
    private function analyzeBehavior(Request $request)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Track user behavior patterns
        $behaviorKey = "behavior_{$ip}";
        $behavior = $this->cache->get($behaviorKey, [
            'paths' => [],
            'user_agents' => [],
            'request_times' => []
        ]);
        
        $behavior['paths'][] = $request->path();
        $behavior['user_agents'][] = $userAgent;
        $behavior['request_times'][] = time();
        
        // Keep only recent data
        $behavior['paths'] = array_slice($behavior['paths'], -50);
        $behavior['user_agents'] = array_slice(array_unique($behavior['user_agents']), -10);
        $behavior['request_times'] = array_slice($behavior['request_times'], -100);
        
        // Analyze patterns
        if (count(array_unique($behavior['user_agents'])) > 5) {
            $this->addThreat('user_agent_switching', self::SEVERITY_MEDIUM, [
                'ip' => $ip,
                'user_agents' => count(array_unique($behavior['user_agents']))
            ]);
            $this->riskScore += 15;
        }
        
        $this->cache->set($behaviorKey, $behavior, 3600);
    }

    /**
     * Detect anomalies in request patterns
     */
    private function detectAnomalies(Request $request)
    {
        // Check for unusual request sizes
        $contentLength = $request->header('content-length', 0);
        if ($contentLength > ($this->config['max_content_length'] ?? 1048576)) { // 1MB
            $this->addThreat('large_request', self::SEVERITY_MEDIUM, [
                'content_length' => $contentLength
            ]);
            $this->riskScore += 10;
        }
        
        // Check for unusual headers
        $suspiciousHeaders = ['x-forwarded-for', 'x-real-ip', 'x-originating-ip'];
        foreach ($suspiciousHeaders as $header) {
            if ($request->header($header)) {
                $this->addThreat('suspicious_header', self::SEVERITY_LOW, [
                    'header' => $header,
                    'value' => $request->header($header)
                ]);
                $this->riskScore += 5;
            }
        }
    }

    /**
     * Add threat to collection
     */
    private function addThreat($type, $severity, $data = [])
    {
        $this->threats[] = [
            'type' => $type,
            'severity' => $severity,
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * Calculate final risk score
     */
    private function calculateRiskScore()
    {
        // Apply severity multipliers
        $severityMultipliers = [
            self::SEVERITY_LOW => 1,
            self::SEVERITY_MEDIUM => 1.5,
            self::SEVERITY_HIGH => 2,
            self::SEVERITY_CRITICAL => 3
        ];
        
        $adjustedScore = 0;
        foreach ($this->threats as $threat) {
            $adjustedScore += $threat['severity'] * ($severityMultipliers[$threat['severity']] ?? 1);
        }
        
        return min(100, $this->riskScore + $adjustedScore);
    }

    /**
     * Block request
     */
    private function blockRequest(Request $request, $score)
    {
        $this->logger->critical('Request blocked by threat detector', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'risk_score' => $score,
            'threats' => $this->threats
        ]);
        
        $this->events->fire('security.request_blocked', [
            'request' => $request,
            'score' => $score,
            'threats' => $this->threats
        ]);
        
        // Add IP to temporary blacklist
        $this->cache->set("blacklist_ip_{$request->ip()}", true, 3600);
    }

    /**
     * Alert about threat
     */
    private function alertThreat(Request $request, $score)
    {
        $this->logger->warning('Threat detected', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'risk_score' => $score,
            'threats' => $this->threats
        ]);
        
        $this->events->fire('security.threat_detected', [
            'request' => $request,
            'score' => $score,
            'threats' => $this->threats
        ]);
    }

    /**
     * Check if IP is private
     */
    private function isPrivateIP($ip)
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
