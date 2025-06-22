<?php

namespace App\Core;

/**
 * Input Sanitizer
 * 
 * Provides comprehensive input sanitization and validation
 * to prevent XSS, SQL injection, and other security vulnerabilities.
 */
class InputSanitizer
{
    private $allowedTags = [];
    private $allowedAttributes = [];

    public function __construct()
    {
        // Default allowed HTML tags for rich text
        $this->allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre'
        ];

        // Default allowed attributes
        $this->allowedAttributes = [
            'class', 'id', 'style'
        ];
    }

    /**
     * Sanitize string input
     */
    public function sanitizeString($input, $options = [])
    {
        if (is_null($input)) {
            return null;
        }

        $defaults = [
            'trim' => true,
            'strip_tags' => true,
            'decode_entities' => true,
            'remove_null_bytes' => true,
            'normalize_whitespace' => true,
            'max_length' => null
        ];

        $options = array_merge($defaults, $options);

        // Remove null bytes
        if ($options['remove_null_bytes']) {
            $input = str_replace("\0", '', $input);
        }

        // Decode HTML entities
        if ($options['decode_entities']) {
            $input = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Strip HTML tags
        if ($options['strip_tags']) {
            $input = strip_tags($input);
        }

        // Normalize whitespace
        if ($options['normalize_whitespace']) {
            $input = preg_replace('/\s+/', ' ', $input);
        }

        // Trim whitespace
        if ($options['trim']) {
            $input = trim($input);
        }

        // Limit length
        if ($options['max_length'] && strlen($input) > $options['max_length']) {
            $input = substr($input, 0, $options['max_length']);
        }

        return $input;
    }

    /**
     * Sanitize HTML input (for rich text editors)
     */
    public function sanitizeHtml($input, $allowedTags = null, $allowedAttributes = null)
    {
        if (is_null($input)) {
            return null;
        }

        $allowedTags = $allowedTags ?: $this->allowedTags;
        $allowedAttributes = $allowedAttributes ?: $this->allowedAttributes;

        // Create allowed tags string for strip_tags
        $allowedTagsString = '<' . implode('><', $allowedTags) . '>';

        // Strip unwanted tags
        $input = strip_tags($input, $allowedTagsString);

        // Remove dangerous attributes
        $input = $this->removeUnsafeAttributes($input, $allowedAttributes);

        // Remove javascript: and data: URLs
        $input = preg_replace('/javascript:/i', '', $input);
        $input = preg_replace('/data:/i', '', $input);

        // Remove on* event handlers
        $input = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $input);

        return $input;
    }

    /**
     * Sanitize email address
     */
    public function sanitizeEmail($email)
    {
        if (is_null($email)) {
            return null;
        }

        $email = trim(strtolower($email));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    /**
     * Sanitize phone number
     */
    public function sanitizePhone($phone)
    {
        if (is_null($phone)) {
            return null;
        }

        // Remove all non-numeric characters except + at the beginning
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure + is only at the beginning
        if (strpos($phone, '+') !== false) {
            $phone = '+' . str_replace('+', '', $phone);
        }

        return $phone;
    }

    /**
     * Sanitize URL
     */
    public function sanitizeUrl($url)
    {
        if (is_null($url)) {
            return null;
        }

        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Check for allowed protocols
        $allowedProtocols = ['http', 'https', 'ftp', 'ftps'];
        $protocol = parse_url($url, PHP_URL_SCHEME);

        if (!in_array($protocol, $allowedProtocols)) {
            return null;
        }

        return $url;
    }

    /**
     * Sanitize integer
     */
    public function sanitizeInt($value, $min = null, $max = null)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        $value = (int) $value;

        if ($min !== null && $value < $min) {
            return $min;
        }

        if ($max !== null && $value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * Sanitize float
     */
    public function sanitizeFloat($value, $min = null, $max = null, $decimals = null)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $value = (float) $value;

        if ($decimals !== null) {
            $value = round($value, $decimals);
        }

        if ($min !== null && $value < $min) {
            return $min;
        }

        if ($max !== null && $value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * Sanitize boolean
     */
    public function sanitizeBool($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on']);
        }

        return (bool) $value;
    }

    /**
     * Sanitize array
     */
    public function sanitizeArray($array, $sanitizer = 'sanitizeString', $options = [])
    {
        if (!is_array($array)) {
            return [];
        }

        $sanitized = [];

        foreach ($array as $key => $value) {
            $sanitizedKey = $this->sanitizeString($key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value, $sanitizer, $options);
            } else {
                $sanitized[$sanitizedKey] = $this->$sanitizer($value, $options);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize filename
     */
    public function sanitizeFilename($filename)
    {
        if (is_null($filename)) {
            return null;
        }

        // Get just the filename, no path
        $filename = basename($filename);

        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Remove leading dots (hidden files)
        $filename = ltrim($filename, '.');

        // Ensure it's not empty
        if (empty($filename)) {
            $filename = 'file_' . uniqid();
        }

        // Limit length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $name = substr($name, 0, 255 - strlen($extension) - 1);
            $filename = $name . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Sanitize SQL input (for dynamic queries - use prepared statements instead)
     */
    public function sanitizeSql($input)
    {
        if (is_null($input)) {
            return null;
        }

        // Remove SQL injection patterns
        $patterns = [
            '/(\s*(union|select|insert|update|delete|drop|create|alter|exec|execute)\s+)/i',
            '/(\s*(or|and)\s+\d+\s*=\s*\d+)/i',
            '/(\s*;\s*)/i',
            '/(\s*--\s*)/i',
            '/(\s*\/\*.*?\*\/\s*)/i'
        ];

        foreach ($patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        return $input;
    }

    /**
     * Sanitize JSON input
     */
    public function sanitizeJson($input)
    {
        if (is_null($input)) {
            return null;
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            $input = $decoded;
        }

        if (is_array($input)) {
            return $this->sanitizeArray($input);
        }

        return $this->sanitizeString($input);
    }

    /**
     * Remove unsafe attributes from HTML
     */
    private function removeUnsafeAttributes($html, $allowedAttributes)
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[@*]');

        foreach ($nodes as $node) {
            $attributesToRemove = [];
            
            foreach ($node->attributes as $attribute) {
                if (!in_array($attribute->name, $allowedAttributes)) {
                    $attributesToRemove[] = $attribute->name;
                }
            }
            
            foreach ($attributesToRemove as $attr) {
                $node->removeAttribute($attr);
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Set allowed HTML tags
     */
    public function setAllowedTags(array $tags)
    {
        $this->allowedTags = $tags;
        return $this;
    }

    /**
     * Set allowed HTML attributes
     */
    public function setAllowedAttributes(array $attributes)
    {
        $this->allowedAttributes = $attributes;
        return $this;
    }

    /**
     * Get allowed HTML tags
     */
    public function getAllowedTags()
    {
        return $this->allowedTags;
    }

    /**
     * Get allowed HTML attributes
     */
    public function getAllowedAttributes()
    {
        return $this->allowedAttributes;
    }
}
