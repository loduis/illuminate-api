<?php

use PHPUnit\Framework\TestCase;
use Illuminate\Api\Resource\Model;
use Illuminate\Api\Resource\Collection;

class ApiResourceModelTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();
        Carbon\Carbon::resetToStringFormat();
    }

    public function testAttributeManipulation()
    {
        $model = new ResourceModelStub;
        $model->name = 'foo';
        $this->assertEquals('foo', $model->name);
        $this->assertTrue(isset($model->name));
        unset($model->name);
        $this->assertFalse(isset($model->name));

        // test mutation
        $model->list_items = ['name' => 'taylor'];
        $this->assertEquals(['name' => 'taylor'], $model->list_items);
        $attributes = $model->getAttributes();
        $this->assertEquals(json_encode(['name' => 'taylor']), $attributes['list_items']);
    }

    public function testCalculatedAttributes()
    {
        $model = new ResourceModelStub;
        $model->password = 'secret';
        $attributes = $model->getAttributes();

        // ensure password attribute was not set to null
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertEquals('******', $model->password);

        $hash = 'e5e9fa1ba31ecd1ae84f75caaa474f3a663f05f4';

        $this->assertEquals($hash, $attributes['password_hash']);
        $this->assertEquals($hash, $model->password_hash);
    }

    public function testFromDateTime()
    {
        $model = new ResourceModelStub();

        $value = Carbon\Carbon::parse('2015-04-17 22:59:01');
        $this->assertEquals('2015-04-17 22:59:01', $model->fromDateTime($value));

        $value = new DateTime('2015-04-17 22:59:01');
        $this->assertInstanceOf(DateTime::class, $value);
        $this->assertInstanceOf(DateTimeInterface::class, $value);
        $this->assertEquals('2015-04-17 22:59:01', $model->fromDateTime($value));

        $value = new DateTimeImmutable('2015-04-17 22:59:01');
        $this->assertInstanceOf(DateTimeImmutable::class, $value);
        $this->assertInstanceOf(DateTimeInterface::class, $value);
        $this->assertEquals('2015-04-17 22:59:01', $model->fromDateTime($value));

        $value = '2015-04-17 22:59:01';
        $this->assertEquals('2015-04-17 22:59:01', $model->fromDateTime($value));

        $value = '2015-04-17';
        $this->assertEquals('2015-04-17 00:00:00', $model->fromDateTime($value));

        $value = '2015-4-17';
        $this->assertEquals('2015-04-17 00:00:00', $model->fromDateTime($value));

        $value = '1429311541';
        $this->assertEquals('2015-04-17 22:59:01', $model->fromDateTime($value));
    }

    public function testGetKeyReturnsValueOfPrimaryKey()
    {
        $model = new ResourceModelStub;
        $model->id = 1;
        $this->assertEquals(1, $model->getKey());
        $this->assertEquals('id', $model->getKeyName());
    }

    public function testTheMutatorCacheIsPopulated()
    {
        ResourceModelStub::$snakeAttributes = true;
        $class = new ResourceModelStub;
        $expectedAttributes = [
            'list_items',
            'password'
        ];

        $this->assertEquals($expectedAttributes, $class->getMutatedAttributes());
    }

    public function testGetMutatedAttributes()
    {
        $model = new ResourceModelGetMutatorsStub;

        $this->assertEquals(['first_name', 'middle_name', 'last_name'], $model->getMutatedAttributes());

        ResourceModelGetMutatorsStub::resetMutatorCache();

        ResourceModelGetMutatorsStub::$snakeAttributes = false;
        $this->assertEquals(['first_name', 'firstName', 'middle_name', 'middleName', 'last_name', 'lastName'], $model->getMutatedAttributes());
    }

    public function testToArrayUsesMutators()
    {
        $model = new ResourceModelStub;
        $model->list_items = [1, 2, 3];
        $array = $model->toArray();

        $this->assertEquals([1, 2, 3], $array['list_items']);
    }

    public function testModelAttributesAreCastedWhenPresentInCastsArray()
    {
        $model = new ResourceModelCastingStub;
        $model->setDateFormat('Y-m-d H:i:s');
        $model->intAttribute = '3';
        $model->floatAttribute = '4.0';
        $model->stringAttribute = 2.5;
        $model->boolAttribute = 1;
        $model->booleanAttribute = 0;
        $model->objectAttribute = ['foo' => 'bar'];
        $obj = new StdClass;
        $obj->foo = 'bar';
        $model->arrayAttribute = $obj;
        $model->jsonAttribute = ['foo' => 'bar'];
        $model->dateAttribute = '1969-07-20';
        $model->datetimeAttribute = '1969-07-20 22:56:00';
        $model->timestampAttribute = '1969-07-20 22:56:00';

        $this->assertIsInt($model->intAttribute);
        $this->assertIsFloat($model->floatAttribute);
        $this->assertIsString($model->stringAttribute);
        $this->assertIsBool($model->boolAttribute);
        $this->assertIsBool($model->booleanAttribute);
        $this->assertIsObject($model->objectAttribute);
        $this->assertIsArray($model->arrayAttribute);
        $this->assertIsArray($model->jsonAttribute);
        $this->assertTrue($model->boolAttribute);
        $this->assertFalse($model->booleanAttribute);
        // $this->assertEquals($obj, $model->objectAttribute);
        $this->assertEquals(['foo' => 'bar'], $model->arrayAttribute);
        $this->assertEquals(['foo' => 'bar'], $model->jsonAttribute);
        $this->assertEquals('{"foo":"bar"}', $model->jsonAttributeValue());
        $this->assertInstanceOf('Carbon\Carbon', $model->dateAttribute);
        $this->assertInstanceOf('Carbon\Carbon', $model->datetimeAttribute);
        $this->assertEquals('1969-07-20', $model->dateAttribute->toDateString());
        $this->assertEquals('1969-07-20 22:56:00', $model->datetimeAttribute->toDateTimeString());
        $this->assertEquals(-14173440, $model->timestampAttribute);

        $arr = $model->toArray();
        $this->assertIsInt($arr['intAttribute']);
        $this->assertIsFloat($arr['floatAttribute']);
        $this->assertIsString($arr['stringAttribute']);
        $this->assertIsBool($arr['boolAttribute']);
        $this->assertIsBool($arr['booleanAttribute']);
        $this->assertIsObject($arr['objectAttribute']);
        $this->assertIsArray($arr['arrayAttribute']);
        $this->assertIsArray($arr['jsonAttribute']);
        $this->assertTrue($arr['boolAttribute']);
        $this->assertFalse($arr['booleanAttribute']);
        // $this->assertEquals($obj, $arr['objectAttribute']);
        $this->assertEquals(['foo' => 'bar'], $arr['arrayAttribute']);
        $this->assertEquals(['foo' => 'bar'], $arr['jsonAttribute']);
        $this->assertEquals('1969-07-20 00:00:00', $arr['dateAttribute']);
        $this->assertEquals('1969-07-20 22:56:00', $arr['datetimeAttribute']);
        $this->assertEquals(-14173440, $arr['timestampAttribute']);
    }

    public function testModelAttributeCastingPreservesNull()
    {
        $model = new ResourceModelCastingStub;
        $model->intAttribute = null;
        $model->floatAttribute = null;
        $model->stringAttribute = null;
        $model->boolAttribute = null;
        $model->booleanAttribute = null;
        $model->objectAttribute = null;
        $model->arrayAttribute = null;
        $model->jsonAttribute = null;
        $model->dateAttribute = null;
        $model->datetimeAttribute = null;
        $model->timestampAttribute = null;

        $attributes = $model->getAttributes();

        $this->assertNull($attributes['intAttribute']);
        $this->assertNull($attributes['floatAttribute']);
        $this->assertNull($attributes['stringAttribute']);
        $this->assertNull($attributes['boolAttribute']);
        $this->assertNull($attributes['booleanAttribute']);
        $this->assertNull($attributes['objectAttribute']);
        $this->assertNull($attributes['arrayAttribute']);
        $this->assertNull($attributes['jsonAttribute']);
        $this->assertNull($attributes['dateAttribute']);
        $this->assertNull($attributes['datetimeAttribute']);
        $this->assertNull($attributes['timestampAttribute']);

        $this->assertNull($model->intAttribute);
        $this->assertNull($model->floatAttribute);
        $this->assertNull($model->stringAttribute);
        $this->assertNull($model->boolAttribute);
        $this->assertNull($model->booleanAttribute);
        $this->assertNull($model->objectAttribute);
        $this->assertNull($model->arrayAttribute);
        $this->assertNull($model->jsonAttribute);
        $this->assertNull($model->dateAttribute);
        $this->assertNull($model->datetimeAttribute);
        $this->assertNull($model->timestampAttribute);

        $array = $model->toArray();

        $this->assertNull($array['intAttribute']);
        $this->assertNull($array['floatAttribute']);
        $this->assertNull($array['stringAttribute']);
        $this->assertNull($array['boolAttribute']);
        $this->assertNull($array['booleanAttribute']);
        $this->assertNull($array['objectAttribute']);
        $this->assertNull($array['arrayAttribute']);
        $this->assertNull($array['jsonAttribute']);
        $this->assertNull($array['dateAttribute']);
        $this->assertNull($array['datetimeAttribute']);
        $this->assertNull($array['timestampAttribute']);
    }

    public function testTransforms()
    {
        $model = new ResourceModelTransformStub();
        $model->date = date('Y-m-d');
        $model->contact = [
            'id' => 1,
            'name' => 'Test',
            'type' => 'client'
        ];

        $model->items = [
            [
                'id' => 1,
                'price' => 5
            ],
            [
                'id' => 2,
                'price' => 10
            ]
        ];

        $this->assertInstanceOf(ResourceModelVisibleStub::class, $model->contact);
        $this->assertInstanceOf(Collection::class, $model->items);

        $model->items->each(function ($item) {
            $this->assertInstanceOf(Model::class, $item);
        });

        $contact = new ResourceModelVisibleStub([
            'id' => 1,
            'name' => 'Test',
            'type' => 'client'
        ]);

        $array = $contact->toArray();
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('type', $array);
        $array = $model->toArray()['contact'];
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayNotHasKey('name', $array);
        $this->assertArrayHasKey('type', $array);

        // Test using add method
        $model = new ResourceModelTransformStub();
        $model->items->add([
            'id' => 1,
            'price' => 5
        ])->add([
            'id' => 2,
            'price' => 10
        ]);
        $this->assertInstanceOf(Collection::class, $model->items);

        $model->items->each(function ($item) {
            $this->assertInstanceOf(Model::class, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('price', $item);
        });
    }
}

class ResourceModelStub extends Model
{
    public function getListItemsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setListItemsAttribute($value)
    {
        $this->attributes['list_items'] = json_encode($value);
    }

    public function getPasswordAttribute()
    {
        return '******';
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = sha1($value);
    }
}

class ResourceModelGetMutatorsStub extends Model
{
    public static function resetMutatorCache()
    {
        static::$mutatorCache = [];
    }

    public function getFirstNameAttribute()
    {
    }

    public function getMiddleNameAttribute()
    {
    }

    public function getLastNameAttribute()
    {
    }

    public function doNotgetFirstInvalidAttribute()
    {
    }

    public function doNotGetSecondInvalidAttribute()
    {
    }

    public function doNotgetThirdInvalidAttributeEither()
    {
    }

    public function doNotGetFourthInvalidAttributeEither()
    {
    }
}

class ResourceModelCastingStub extends Model
{
    protected static $casts = [
        'intAttribute' => 'int',
        'floatAttribute' => 'float',
        'stringAttribute' => 'string',
        'boolAttribute' => 'bool',
        'booleanAttribute' => 'boolean',
        'objectAttribute' => 'object',
        'arrayAttribute' => 'array',
        'jsonAttribute' => 'json',
        'dateAttribute' => 'date',
        'datetimeAttribute' => 'datetime',
        'timestampAttribute' => 'timestamp',
    ];

    public function jsonAttributeValue()
    {
        return $this->attributes['jsonAttribute'];
    }
}

class ResourceModelTransformStub extends Model
{
    protected static $casts = [
        'contact' => ResourceModelVisibleStub::class,
        'items' => Model::class . '[]',
    ];
}


class ResourceModelVisibleStub extends Model
{
    protected static $visible = [
        'type'
    ];
}
