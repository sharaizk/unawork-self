<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use App\Models\Utility;

class FileHelper
{
    public static function getUrl(string $path, string $size = ''): string
    {
        $settings = Utility::getAdminPaymentSetting();
        $disk = $settings['storage_setting'];

        // Get the file name and directory
        $pathInfo = pathinfo($path);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['basename'];
        
        // Add the size prefix to the file name if specified
        if (!empty($size)) {
            $filename = $size . '_' . $filename;
        }
        
        // Generate the full path
        $fullPath = $directory . '/' . $filename;

        // Get the URL from the storage disk
        return Storage::disk($disk)->url($fullPath);
    }
}