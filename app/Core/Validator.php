<?php

namespace App\Core;

use App\Exceptions\ValidationException;

/**
 * Validator Class
 * 
 * Provides input validation functionality with common validation rules.
 */
class Validator
{
    private $errors = [];

    /**
     * Validate data against rules
     */
    public function validate($data, $rules)
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleSet) {
            $fieldRules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply validation rule
     */
    private function applyRule($field, $value, $rule, $data)
    {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, "The {$field} field is required.");
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;

            case 'min':
                $min = (int)$ruleParams[0];
                if (!empty($value) && strlen($value) < $min) {
                    $this->addError($field, "The {$field} must be at least {$min} characters.");
                }
                break;

            case 'max':
                $max = (int)$ruleParams[0];
                if (!empty($value) && strlen($value) > $max) {
                    $this->addError($field, "The {$field} may not be greater than {$max} characters.");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, "The {$field} must be a number.");
                }
                break;

            case 'integer':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "The {$field} must be an integer.");
                }
                break;

            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, "The {$field} may only contain letters.");
                }
                break;

            case 'alpha_num':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, "The {$field} may only contain letters and numbers.");
                }
                break;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value !== ($data[$confirmField] ?? null)) {
                    $this->addError($field, "The {$field} confirmation does not match.");
                }
                break;

            case 'unique':
                // This would require database checking
                // Implementation depends on your specific needs
                break;

            case 'exists':
                // This would require database checking
                // Implementation depends on your specific needs
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $ruleParams)) {
                    $allowed = implode(', ', $ruleParams);
                    $this->addError($field, "The selected {$field} is invalid. Allowed values: {$allowed}");
                }
                break;

            case 'regex':
                $pattern = $ruleParams[0];
                if (!empty($value) && !preg_match($pattern, $value)) {
                    $this->addError($field, "The {$field} format is invalid.");
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$field} must be a valid URL.");
                }
                break;

            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, "The {$field} is not a valid date.");
                }
                break;

            case 'file':
                if (!empty($value) && !is_array($value)) {
                    $this->addError($field, "The {$field} must be a file.");
                }
                break;

            case 'image':
                if (!empty($value) && is_array($value)) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($value['type'], $allowedTypes)) {
                        $this->addError($field, "The {$field} must be an image (JPEG, PNG, GIF, WebP).");
                    }
                }
                break;

            case 'max_size':
                $maxSize = (int)$ruleParams[0] * 1024 * 1024; // Convert MB to bytes
                if (!empty($value) && is_array($value) && $value['size'] > $maxSize) {
                    $maxMB = $ruleParams[0];
                    $this->addError($field, "The {$field} may not be greater than {$maxMB}MB.");
                }
                break;
        }
    }

    /**
     * Add validation error
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get all errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get errors for specific field
     */
    public function getError($field)
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if validation has errors
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Get first error message
     */
    public function getFirstError()
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }
        return null;
    }

    /**
     * Validate and throw exception if fails
     */
    public function validateOrFail($data, $rules, $redirectUrl = null)
    {
        if (!$this->validate($data, $rules)) {
            throw new ValidationException('Validation failed', $this->errors, $redirectUrl);
        }
        return true;
    }

    /**
     * Check if validation passes
     */
    public function passes()
    {
        return empty($this->errors);
    }

    /**
     * Check if validation fails
     */
    public function fails()
    {
        return !empty($this->errors);
    }

    /**
     * Static helper to create validator instance
     */
    public static function make()
    {
        return new static();
    }
}
