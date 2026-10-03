<?php

namespace App\Domain\Strategy\Services;

use App\Domain\Strategy\Contracts\StrategyInterface;
use InvalidArgumentException;

class StrategyRegistry
{
    /** @var array<string, StrategyInterface> */
    protected array $strategies = [];

    public function __construct()
    {
        $registered = config('strategies.registered', []);
        foreach ($registered as $key => $class) {
            if (class_exists($class)) {
                $this->register(new $class());
            }
        }
    }

    public function register(StrategyInterface $strategy): void
    {
        $this->strategies[$strategy->key()] = $strategy;
    }

    public function get(string $key): StrategyInterface
    {
        if (!isset($this->strategies[$key])) {
            throw new InvalidArgumentException("Strategy '{$key}' is not registered.");
        }
        return $this->strategies[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->strategies[$key]);
    }

    /**
     * @return array<string, StrategyInterface>
     */
    public function all(): array
    {
        return $this->strategies;
    }

    /**
     * Return list for UI selectors / dropdowns.
     */
    public function toList(): array
    {
        $list = [];
        foreach ($this->strategies as $key => $strategy) {
            $list[] = [
                'key' => $strategy->key(),
                'name' => $strategy->name(),
                'description' => $strategy->description(),
                'category' => $strategy->category(),
                'characteristics' => $strategy->characteristics()->toArray(),
            ];
        }
        return $list;
    }
}
