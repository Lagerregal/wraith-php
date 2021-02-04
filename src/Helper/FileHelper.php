<?php

namespace WraithPhp\Helper;

class FileHelper
{
    /**
     * Returns a safe filename, for a given platform (OS), by replacing all
     * dangerous characters with an underscore.
     *
     * @param string $dangerousFilename The source filename to be "sanitized"
     *
     * @return string string A safe version of the input filename
     */
    public static function sanitizeFileName(string $dangerousFilename): string
    {
        $dangerousCharacters = [" ", '"', "'", "&", "/", "\\", "?", "#"];
        return str_replace($dangerousCharacters, '_', $dangerousFilename);
    }
}
