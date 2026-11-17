<?php

namespace App\Services\Matching;

use App\Contracts\PodMatchingAlgorithm;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the configured matching algorithm (UNA-016). Only drivers listed in
 * config('pod_matching.algorithms') can be selected, and each must implement PodMatchingAlgorithm;
 * nothing user-supplied is ever instantiated.
 */
class PodMatchingAlgorithmResolver
{
    public function __construct(private Container $container)
    {
    }

    public function resolve(?string $key = null): PodMatchingAlgorithm
    {
        $key ??= (string) config('pod_matching.algorithm', DeterministicPodMatchingService::KEY);
        $class = config("pod_matching.algorithms.{$key}.class");

        if (!is_string($class) || $class === '' || !class_exists($class)) {
            throw new InvalidArgumentException("POD matching algorithm [{$key}] is not configured.");
        }
        if (!is_subclass_of($class, PodMatchingAlgorithm::class)) {
            throw new InvalidArgumentException("POD matching algorithm [{$key}] must implement " . PodMatchingAlgorithm::class . '.');
        }

        $algorithm = $this->container->make($class);
        if ($algorithm->key() !== $key) {
            throw new InvalidArgumentException("POD matching algorithm [{$key}] reports key [{$algorithm->key()}].");
        }

        return $algorithm;
    }
}
