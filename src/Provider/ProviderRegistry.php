<?php

namespace CodingTask\Download\Provider;

use CodingTask\Download\Contract\ProviderInterface;
use CodingTask\Download\Exception\UnsupportedUrlException;

class ProviderRegistry
{
    /** @var ProviderInterface[] */
    private array $providers;

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    // Return first provider that claims it can handle the URL
    public function resolve(string $url): ProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->canHandle($url)) {
                return $provider;
            }
        }

        throw new UnsupportedUrlException('No provider supports the given URL.');
    }
}
