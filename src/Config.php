<?php

declare(strict_types=1);

namespace PhpSoftBox\Config;

use ArrayAccess;
use LogicException;
use PhpSoftBox\Collection\Collection;
use PhpSoftBox\Config\Contracts\ConfigMutableInterface;
use PhpSoftBox\Encryptor\Contracts\EncryptedValueResolverInterface;
use PhpSoftBox\Encryptor\EncryptedValue;

use function is_array;
use function is_callable;

final class Config implements ArrayAccess, ConfigMutableInterface
{
    private Collection $data;
    private bool $readOnly;
    private ?EncryptedValueResolverInterface $encryptedValueResolver;
    private ?string $baseDir;

    /** @var array{recursive?: bool, 'list'?: 'replace'|'append'|'append_unique'} */
    private array $mergeOptions;

    /**
     * @param array<array|callable|self> $sources
     * @param array{'recursive'?: bool, 'list'?: 'replace'|'append'|'append_unique'} $mergeOptions
     */
    public function __construct(
        array $sources = [],
        bool $readOnly = true,
        array $mergeOptions = [],
        ?EncryptedValueResolverInterface $encryptedValueResolver = null,
        ?string $baseDir = null,
    ) {
        $this->mergeOptions           = $mergeOptions;
        $this->data                   = $this->mergeSources($sources, $this->mergeOptions);
        $this->readOnly               = $readOnly;
        $this->encryptedValueResolver = $encryptedValueResolver;
        $this->baseDir                = $baseDir;
    }

    /**
     * @param array<array|callable|self> $sources
     * @param array{recursive?:bool,'list'?:'replace'|'append'|'append_unique'} $mergeOptions
     */
    private function mergeSources(array $sources, array $mergeOptions = []): Collection
    {
        $result = new Collection();

        foreach ($sources as $src) {
            if ($src instanceof self) {
                $src = $src->all();
            } elseif (is_callable($src)) {
                $resolved = $src();
                $src      = $resolved instanceof self ? $resolved->all() : (array) $resolved;
            }
            $result = $result->merge((array) $src, $mergeOptions + ['recursive' => true]);
        }

        return $result;
    }

    public function all(): array
    {
        return $this->data->all();
    }

    public function baseDir(): ?string
    {
        return $this->baseDir;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data->getPath($key, $default);

        return $this->resolveEncryptedValues($value);
    }

    public function has(string $key): bool
    {
        return $this->data->hasPath($key);
    }

    public function withReadOnly(bool $readOnly = true): self
    {
        $clone           = clone $this;
        $clone->readOnly = $readOnly;

        return $clone;
    }

    public function mutable(): ConfigMutableInterface
    {
        return new ConfigMutable($this);
    }

    public function override(string $key, mixed $value): void
    {
        $wasReadOnly    = $this->readOnly;
        $this->readOnly = false;

        try {
            $this->set($key, $value);
        } finally {
            $this->readOnly = $wasReadOnly;
        }
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->readOnly) {
            throw new LogicException('Config is read-only');
        }
        $this->data->setPath($key, $value);
    }

    // ArrayAccess
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->set((string) $offset, null);
    }

    private function resolveEncryptedValues(mixed $value): mixed
    {
        if ($value instanceof EncryptedValue) {
            if ($this->encryptedValueResolver === null) {
                throw new LogicException('EncryptedValueResolver is not configured.');
            }

            return $this->encryptedValueResolver->resolve($value);
        }

        if (is_array($value)) {
            return Collection::from($value)
                ->map(fn (mixed $item): mixed => $this->resolveEncryptedValues($item))
                ->all();
        }

        return $value;
    }
}
