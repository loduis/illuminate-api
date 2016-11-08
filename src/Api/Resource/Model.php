<?php

namespace Illuminate\Api\Resource;

use ArrayAccess;
use JsonSerializable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\AttributeAccess;
use Illuminate\Support\Traits\AttributeMutator;
use Illuminate\Support\Traits\AttributeFillable;
use Illuminate\Support\Traits\AttributeCastable;
use Illuminate\Support\Traits\AttributeSerialize;

class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    /**
     * Indicates whether attributes are snake cased on arrays.
     *
     * @var bool
     */
    public static $snakeAttributes = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = [
        'id' => 'int'
    ];

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Add ability for access attributes
     */
    use AttributeAccess;

    /**
     * Add ability for mutate attributes
     */
    use AttributeMutator;

    /**
     * Add ability for casting attributes
     */
    use AttributeCastable;

    /**
     * Add ability for convert to json
     */
    use AttributeSerialize;

    /**
     * Add ability for mass assignment attribute
     */
    use AttributeFillable;

    /**
     * Create a new Resource model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Get the value of the resource's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Set the value of the resource's primary key.
     *
     * @param mixed $value
     * @return $this
     */
    public function setKey($value)
    {
        $this->attributes[$this->getKeyName()] = $value;

        return $this;
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return key($this->primaryKey);
    }

    /**
     * Get the primary key type for the model.
     *
     * @return string
     */
    public function getKeyType()
    {
        return current($this->primaryKey);
    }

    /**
     * Set the primary key for the model.
     *
     * @param  string  $key
     * @return $this
     */
    public function setKeyName($key, $type = 'int')
    {
        $this->primaryKey = [
            $key => $type
        ];

        return $this;
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->getArrayableAttributes();

        return $this->attributesToArray($attributes);
    }

    /**
     * Export visible attributes that are used in the transform process
     *
     * @return array
     */
    public function toArrayVisible()
    {
        $attributes = $this->getArrayableVisibleAttributes();

        return $this->attributesToArray($attributes);
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value, Collection::class);
        }

        return $value;
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    protected function attributesToArray($values)
    {
        $attributes = [];

        foreach ($values as $key => $value) {
            // If the values implements the Arrayable interface we can just call this
            // toArray method on the instances which will convert both models and
            // collections to their proper array form and we'll set the values.
            if ($value instanceof Collection) {
                $value = $value->allVisible();
            } elseif ($value instanceof self) {
                $value = $value->toArrayVisible();
            } elseif ($value instanceof Arrayable) {
                $value = $value->toArray();
            }
            $attributes[$key] = $value;
        }

        $mutatedAttributes = $this->mutateAttributes($attributes);

        return $this->castAttributes($attributes, $mutatedAttributes);
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @return array
     */
    protected function getArrayableVisibleAttributes()
    {
        $visible   = method_exists(static::class, 'visible') ?
            static::visible() :
            static::getStaticProperty('visible', []);
        if ($visible == ['*']) {
            return $this->getArrayableAttributes();
        }
        $visible[] = $this->getKeyName();
        $visible   = array_unique($visible);

        return array_intersect_key($this->getArrayableAttributes(), array_flip($visible));
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {

        $this->validateFillable($key);

        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->mutatingAttribute($key, $value);
        }

        $this->attributes[$key] = $this->castSetAttribute($key, $value, Collection::class);

        return $this;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param  string $method
     * @param  array $params
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        return static::callMacro(static::class, $method, $params);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param  string $method
     * @param  array $params
     * @return mixed
     */
    public function __call($method, $params)
    {
        if (preg_match('/(?<=^|;)set([^;]+?)(;|$)/', $method, $match)) {
            $key = lcfirst($match[1]);
            $value = count($params) > 0 ? $params[0] : true;
            if ($this->hasSetMutator($key)) {
                return $this->mutatingAttribute($key, $value);
            }
            return $this->setAttribute($key, $value);
        }

        array_unshift($params, $this);

        return static::callMacro($this, $method, $params);
    }

    /**
     * Call a raw method
     *
     * @param  static|$this $objectOrClass
     * @param  string $method
     * @param  array $params
     * @return mixed
     */
    private static function callMacro($objectOrClass, $method, $params)
    {
        $method = 'macro' . ucfirst($method) . 'Handler';

        if (!method_exists($objectOrClass, $method)) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        return call_user_func_array([$objectOrClass, $method], $params);
    }
}
