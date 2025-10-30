<?php

namespace CodingTask\Download\Provider;

use CodingTask\Download\Contract\ProviderInterface;
use CodingTask\Download\Exception\NetworkException;
use CodingTask\Download\Exception\ProviderErrorException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GoogleDriveProvider extends AbstractProvider implements ProviderInterface
{
    public function canHandle(string $url): bool
    {
        return (bool)preg_match('#https?://(drive|docs)\.google\.com/#i', $url);
    }

    public function download(string $url): UploadedFile
    {
        $fileId = $this->extractFileId($url);

        if (!$fileId) {
            throw new ProviderErrorException('Unable to determine Google Drive file ID from URL.');
        }

        // First attempt: Google "uc" export endpoint
        $downloadUrl = 'https://drive.google.com/uc?export=download&id=' . urlencode($fileId);

        try {
            $response = $this->httpClient->request('GET', $downloadUrl, [
                'headers' => [
                    'User-Agent' => 'Downloader/1.0',
                    'Accept' => '*/*',
                ],
            ]);

            // For large files Google may return an HTML confirm page; detect and retry with token
            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
            $content = $response->getContent();

            if (stripos($contentType, 'text/html') !== false && preg_match('/confirm=([0-9A-Za-z_\-]+)/', $content, $m)) {
                $confirm = $m[1];
                $response = $this->httpClient->request('GET', $downloadUrl . '&confirm=' . $confirm);
            }

            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {
                throw new NetworkException('Unexpected status code: ' . $status);
            }

            $headers = $response->getHeaders(false);
            $filename = $this->extractFilenameFromHeaders($headers) ?? ($fileId . '.bin');
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

    private function extractFileId(string $url): ?string
    {
        $patterns = [
            '#/file/d/([^/]+)/#',
            '#id=([^&]+)#',
            '#/uc\?export=download&id=([^&]+)#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
