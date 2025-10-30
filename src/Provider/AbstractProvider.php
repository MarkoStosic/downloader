<?php

namespace CodingTask\Download\Provider;

use CodingTask\Download\Contract\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractProvider implements ProviderInterface
{
    protected HttpClientInterface $httpClient;

    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create([
            'max_redirects' => 10,
            'timeout' => 60,
        ]);
    }

    // Prefer Content-Disposition filename; fall back handled by callers
    protected function extractFilenameFromHeaders(array $headers): ?string
    {
        $disposition = $headers['content-disposition'][0] ?? null;

        if (!$disposition) {
            return null;
        }

        if (preg_match('/filename\*=UTF-8\'' . "'" . '?([^;\r\n]+)/i', $disposition, $m)) {
            return rawurldecode(trim($m[1], '"\''));
        }

        if (preg_match('/filename="?([^";\r\n]+)"?/i', $disposition, $m)) {
            return $m[1];
        }

        return null;
    }
}
