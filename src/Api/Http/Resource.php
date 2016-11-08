<?php

namespace Illuminate\Api\Http;

use BadMethodCallException;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Api\Resource\Model;
use Illuminate\Api\Resource\Filter;
use Illuminate\Api\Resource\Events;
use Illuminate\Api\Resource\Collection;

abstract class Resource extends Model
{
    use Events;

    /**
     * Create a new resource  instance.
     *
     * @param  mixed $attributes
     */
    public function __construct($attributes = [])
    {
        if (is_scalar($attributes)) {
            $attributes = [
                $this->getKeyName() => $attributes
            ];
        }

        parent::__construct($attributes);
    }

    /**
     * Create instance from reques
     *
     * @param  string $method
     * @param  null|int|string $id
     * @param  array|\Illuminate\Contracts\Support\Arrayable $params
     * @return $this|array
     */
    protected static function instanceFromRequest($method, $id = null, $params = [])
    {
        $response = static::requestToArray($method, $id, $params);

        if (Arr::isAssoc($response)) {
            return new static($response);
        }

        return Collection::makeOf(static::class, $response);
    }

    /**
     * Invoke the request of the current resource
     *
     * @param  string $method
     * @param  null|int|string $id
     * @param  array|\Illuminate\Contracts\Support\Arrayable $params
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function request($method, $id = null, $params = [])
    {
        $method = strtoupper($method);

        // For fix query string parameters

        if ($method == 'GET' && !$params instanceof Filter) {
            $params = static::filters($params);
        }

        $path = static::resolvePath()  . ($id ? "/$id" : '');

        $response = Client::request($method, $path, $params);

        return static::fireResourceEvent($method, $response);
    }

    protected static function filters($params)
    {
        if (method_exists(static::class, 'filterWith')) {
            $filterObject = static::filterWith();
        } else {
            $filterClass  = static::getStaticProperty('filterWith', Filter::class);
            $filterObject = new $filterClass;
        }

        return $filterObject->fill($params);
    }

    /**
     * Request to json object
     *
     * @param  string $method
     * @param  null|int|string $id
     * @param  array|\Illuminate\Contracts\Support\Arrayable $params
     * @return array
     */
    public static function requestToArray($method, $id = null, $params = [])
    {
        $response = static::request($method, $id, $params);

        return (array) json_decode($response->getBody(), true);
    }

    /**
     * Resolve the path of the resource for call request.
     *
     * @return string
     */
    public static function resolvePath()
    {
        static $resolved = [];

        $class = static::class;

        if (($path = Arr::get($resolved, $class)) !== null) {
            return $path;
        }

        if ($path = static::getStaticProperty('path')) {
            return $resolved[$class] = $path;
        }

        // Useful for namespaces: Foo\Charge
        $path = $class;
        if ($postfixNamespaces = strrchr($path, '\\')) {
            $path = substr($postfixNamespaces, 1);
        }

        $path = Str:: snake($path, '-');
        $path = Str::plural($path);

        return $resolved[$class] =  $path;
    }

    /**
     * Check if resouce is an instance
     *
     * @param  mixed  $resource
     * @return bool
     */
    protected static function isResource($resource)
    {
        return $resource instanceof static;
    }

    /**
     * Store and refresh object
     *
     * @param  string $method
     * @param  int|string $id
     *
     * @return $this
     */
    protected function store($method, $id = null)
    {
        $attributes = static::requestToArray($method, $id, $this);

        return $this->fill($attributes);
    }
}
