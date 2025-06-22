<?php

namespace App\Exceptions;

/**
 * File Upload Exception
 * 
 * Thrown when file upload operations fail.
 */
class FileUploadException extends BaseException
{
    protected $statusCode = 400;
    protected $errorCode = 'UPLOAD_ERROR';
    protected $uploadError;
    protected $fileName;
    protected $fileSize;

    public function __construct($message = 'File upload failed', $uploadError = null, $fileName = null, $fileSize = null)
    {
        parent::__construct($message);
        $this->uploadError = $uploadError;
        $this->fileName = $fileName;
        $this->fileSize = $fileSize;
    }

    /**
     * Get upload error code
     */
    public function getUploadError()
    {
        return $this->uploadError;
    }

    /**
     * Get file name
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * Get file size
     */
    public function getFileSize()
    {
        return $this->fileSize;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['upload_error'] = $this->uploadError;
        $array['file_name'] = $this->fileName;
        $array['file_size'] = $this->fileSize;
        return $array;
    }

    /**
     * Create from PHP upload error
     */
    public static function fromUploadError($uploadError, $fileName = null, $fileSize = null)
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        $message = $messages[$uploadError] ?? 'Unknown upload error';
        return new static($message, $uploadError, $fileName, $fileSize);
    }

    /**
     * Create for file too large
     */
    public static function fileTooLarge($fileName, $fileSize, $maxSize)
    {
        $message = "File '{$fileName}' is too large. Maximum size allowed: " . static::formatBytes($maxSize);
        return new static($message, null, $fileName, $fileSize);
    }

    /**
     * Create for invalid file type
     */
    public static function invalidFileType($fileName, $fileType, array $allowedTypes = [])
    {
        $message = "File type '{$fileType}' is not allowed for '{$fileName}'";
        if (!empty($allowedTypes)) {
            $message .= ". Allowed types: " . implode(', ', $allowedTypes);
        }
        return new static($message, null, $fileName);
    }

    /**
     * Create for upload directory not writable
     */
    public static function directoryNotWritable($directory)
    {
        return new static("Upload directory '{$directory}' is not writable");
    }

    /**
     * Create for file move failure
     */
    public static function moveFileFailed($fileName, $destination)
    {
        return new static("Failed to move uploaded file '{$fileName}' to '{$destination}'");
    }

    /**
     * Create for missing file
     */
    public static function fileNotFound($fileName)
    {
        return new static("Uploaded file '{$fileName}' not found");
    }

    /**
     * Create for virus detected
     */
    public static function virusDetected($fileName)
    {
        return new static("Virus detected in file '{$fileName}'");
    }

    /**
     * Create for invalid image
     */
    public static function invalidImage($fileName)
    {
        return new static("File '{$fileName}' is not a valid image");
    }

    /**
     * Create for image dimensions too large
     */
    public static function imageTooLarge($fileName, $width, $height, $maxWidth, $maxHeight)
    {
        return new static("Image '{$fileName}' dimensions ({$width}x{$height}) exceed maximum allowed ({$maxWidth}x{$maxHeight})");
    }

    /**
     * Format bytes to human readable format
     */
    private static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
