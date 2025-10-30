<?php

namespace CodingTask\Download;

use CodingTask\Download\Provider\DirectUrlProvider;
use CodingTask\Download\Provider\GoogleDriveProvider;
use CodingTask\Download\Provider\OneDriveProvider;
use CodingTask\Download\Provider\ProviderRegistry;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Downloader
{
    private ProviderRegistry $registry;

    public function __construct(?ProviderRegistry $registry = null, ?HttpClientInterface $httpClient = null)
    {
        if ($registry === null) {
            $registry = new ProviderRegistry([
                new GoogleDriveProvider($httpClient),
                new OneDriveProvider($httpClient),
                new DirectUrlProvider($httpClient),
            ]);
        }

        $this->registry = $registry;
    }

    public function download(string $url): UploadedFile
    {
        $provider = $this->registry->resolve($url);

        return $provider->download($url);
    }
}
