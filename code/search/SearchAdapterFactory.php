<?php

class SearchAdapterFactory
{
    /**
     * @param string $driver
     * @return SearchAdapterInterface mixed
     */
    public static function create($driver)
    {
        if (!$driver) {
            throw new InvalidArgumentException('No driver provided. Please check your config.');
        }

        if (!class_exists($adapterClass = ucfirst($driver) . 'SearchAdapter')) {
            throw new InvalidArgumentException(sprintf('SearchAdapter class does not exist: "%s".', $adapterClass));
        }

        return new $adapterClass();
    }
}
