<?php

namespace WraithPhp\Helper;

class FileHelper
{
    /**
     * Returns a safe filename, for a given platform (OS), by replacing all
     * dangerous characters with an underscore.
     *
     * @param string $fileName The source filename to be "sanitized"
     * @param int $maxLength The max length of the filename (optional)
     *
     * @return string string A safe version of the input filename
     */
    public static function sanitizeFileName(string $fileName, int $maxLength = 0): string
    {
        $dangerousCharacters = [" ", '"', "'", "&", "/", "\\", "?", "#"];
        $fileName = str_replace($dangerousCharacters, '_', $fileName);
        if ($maxLength !== 0) {
            $fileName = substr($fileName, 0, $maxLength);
        }
        return $fileName;
    }
}
