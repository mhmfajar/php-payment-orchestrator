<?php

namespace Mhmfajar\PaymentOrchestrator\Support;

/**
 * Tiny dot-notation config reader for framework-free configuration arrays.
 */
class Config
{
    /**
     * Raw configuration values.
     *
     * @var array
     */
    private $items;

    /**
     * Store the raw configuration array.
     *
     * @param array $items Raw configuration values.
     * @return void
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Read a configuration value using dot notation.
     *
     * @param string $key Dot-notation configuration key.
     * @param string|int|float|bool|array|null $default Default value when key is absent.
     * @return string|int|float|bool|array|null Configuration value.
     */
    public function get($key, $default = null)
    {
        // $segments contains each nested array key requested by dot notation.
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Return the complete configuration array.
     *
     * @return array Raw configuration values.
     */
    public function all()
    {
        return $this->items;
    }
}
