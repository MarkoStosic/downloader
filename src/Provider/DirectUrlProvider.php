<?php

namespace CodingTask\Download\Provider;

use CodingTask\Download\Contract\ProviderInterface;
use CodingTask\Download\Exception\NetworkException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DirectUrlProvider extends AbstractProvider implements ProviderInterface
{
    public function canHandle(string $url): bool
    {
        return preg_match('#^https?://#i', $url)
            && !preg_match('#(drive\.google\.com|docs\.google\.com)#i', $url)
            && !preg_match('#(1drv\.ms|onedrive\.live\.com)#i', $url);
    }

    public function download(string $url): UploadedFile
    {
        try {
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
            $filename = $this->extractFilenameFromHeaders($headers) ?? basename(parse_url($url, PHP_URL_PATH) ?: 'download');
            $mimeType = $headers['content-type'][0] ?? null;

            // Stream response body to a temp file to avoid high memory usage
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
            if ($e instanceof NetworkException) {
                throw $e;
            }

            throw new NetworkException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
