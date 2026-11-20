<?php

namespace App\Services\PodDocument\Extraction;

use App\Contracts\DocumentTextExtractionProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the configured document text extraction AI provider (UNA-022).
 *
 * Reads config('pod_materials.extraction.provider') to select the active provider
 * driver from config('pod_materials.extraction.providers'). Each driver must implement
 * App\Contracts\DocumentTextExtractionProvider.
 */
class DocumentTextExtractionResolver
{
    public function __construct(private Container $container)
    {
    }

    /**
     * Resolve the active or requested text extraction provider.
     *
     * @param string|null $key Optional provider key to override default configuration.
     * @return DocumentTextExtractionProvider
     *
     * @throws InvalidArgumentException When provider is unconfigured or does not implement DocumentTextExtractionProvider.
     */
    public function resolve(?string $key = null): DocumentTextExtractionProvider
    {
        $key ??= (string) config('pod_materials.extraction.provider', GeminiTextExtractionProvider::KEY);
        $providerConfig = config("pod_materials.extraction.providers.{$key}");

        if (!is_array($providerConfig) || empty($providerConfig['class'])) {
            throw new InvalidArgumentException("Document text extraction provider [{$key}] is not configured.");
        }

        $class = $providerConfig['class'];
        if (!is_string($class) || !class_exists($class)) {
            throw new InvalidArgumentException("Document text extraction provider class [{$class}] does not exist.");
        }

        if (!is_subclass_of($class, DocumentTextExtractionProvider::class)) {
            throw new InvalidArgumentException("Document text extraction provider [{$class}] must implement " . DocumentTextExtractionProvider::class . '.');
        }

        /** @var DocumentTextExtractionProvider $provider */
        $provider = $this->container->make($class, ['config' => $providerConfig]);

        if ($provider->key() !== $key) {
            throw new InvalidArgumentException("Document text extraction provider [{$key}] reports mismatched key [{$provider->key()}].");
        }

        return $provider;
    }
}
