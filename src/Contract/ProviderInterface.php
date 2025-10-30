<?php

namespace CodingTask\Download\Contract;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ProviderInterface
{
    public function canHandle(string $url): bool;

    public function download(string $url): UploadedFile;
}
