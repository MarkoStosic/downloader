<?php

namespace CodingTask\Download\Provider;

use CodingTask\Download\Contract\ProviderInterface;
use CodingTask\Download\Exception\NetworkException;
use CodingTask\Download\Exception\ProviderErrorException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OneDriveProvider extends AbstractProvider implements ProviderInterface
{
    public function canHandle(string $url): bool
    {
        return (bool)preg_match('#https?://(1drv\.ms|onedrive\.live\.com)/#i', $url);
    }

    public function download(string $url): UploadedFile
    {
        try {
            // Ensure direct download variant when OneDrive UI link omitted it
            if (!preg_match('/[?&]download=1/i', $url)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'download=1';
            }

            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Downloader/1.0',
                    'Accept' => '*/*',
                ],
            ]);

            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                throw new NetworkException('Unexpected status code: ' . $status);
            }

            $headers = $response->getHeaders(false);
            $filename = $this->extractFilenameFromHeaders($headers) ?? 'onedrive_file.bin';
            $mimeType = $headers['content-type'][0] ?? null;

            // Stream response body to disk
            $tmp = tempnam(sys_get_temp_dir(), 'dl_') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('dl_', true);
            $dest = fopen($tmp, 'wb');

            if ($dest === false) {
                throw new NetworkException('Failed to create temp file');
            }

            foreach ($this->httpClient->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }

                $data = $chunk->getContent();

                if ($data !== '') {
                    fwrite($dest, $data);
                }
            }

            fclose($dest);

            return new UploadedFile($tmp, $filename, $mimeType, null, true);
        } catch (\Throwable $e) {
            if ($e instanceof ProviderErrorException || $e instanceof NetworkException) {
                throw $e;
            }

            throw new ProviderErrorException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
