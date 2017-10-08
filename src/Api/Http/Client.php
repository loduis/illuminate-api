<?php

namespace Illuminate\Api\Http;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Contracts\Support\Arrayable;

class Client
{

    /**
    * Instance of the GuzzleHttp\Client
    *
    * @var \GuzzleHttp\ClientInterface
    */
    protected static $transport;

    protected static $options = [];

    public static function request($method, $path, $params = [])
    {
        return static::transport()->request(
            $method = strtoupper($method),
            $path,
            static::resolveParameters($method, $params)
        );
    }

    public static function toArray($method, $path, $params = [])
    {
        $response = static::request($method, $path, $params);

        return json_decode((string) $response->getBody(), true);
    }

    protected static function options(array $options)
    {
        if (!isset(static::$options['headers']['Accept'])) {
            static::$options['headers']['Content-Type'] =
            static::$options['headers']['Accept']       = 'application/json';
        }

        return static::$options = array_merge(static::$options, $options);
    }

    /**
     * Set the htpp client for request resource.
     *
     * @param array $options
     * @return self
     */
    public static function create(array $options = [])
    {
        static::$transport = new HttpClient(static::options($options));

        return static::class;
    }

    /**
     * Get instance of the http client
     *
     * @return \GuzzleHttp\ClientInterface
     */
    protected static function transport()
    {
        return static::$transport ?: static::create();
    }

    /**
     * Resolve parameters for request
     *
     * @param  array|Arrayable $params
     * @return array
     */
    private static function resolveParameters($method, $params)
    {
        $options = [];
        if ($params instanceof Arrayable) {
            $params = $params->toArray();
        } else {
            $params = (array) $params;
        }
        if ($params) {
            $options[$method == 'GET' ? 'query' : 'json'] = $params;
        }

        return $options;
    }
}
