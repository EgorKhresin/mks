<?php

namespace AppBundle\Util;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadedDataStringFile extends UploadedFile {
    public function __construct(string $dataString, string $originalName)
    {
        preg_match('/data:([^;]*);base64,(.*)/', $dataString, $matches);
        $mimeType = $matches[1];

        $filePath = tempnam(sys_get_temp_dir(), 'UploadedFile');
        $data = base64_decode($matches[2]);
        file_put_contents($filePath, $data);
        $error = null;

        parent::__construct($filePath, $originalName, $mimeType, filesize($filePath), $error, true);
    }
}