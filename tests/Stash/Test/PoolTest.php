<?php

/*
 * This file is part of the Stash package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stash\Test;

use Stash\Pool;
use Stash\Driver\Ephemeral;

/**
 * @package Stash
 * @author  Robert Hafner <tedivm@tedivm.com>
 */
class PoolTest extends \PHPUnit_Framework_TestCase
{
    protected $data = array(array('test', 'test'));
    protected $multiData = array('key' => 'value',
                                 'key1' => 'value1',
                                 'key2' => 'value2',
                                 'key3' => 'value3');

    public function testSetDriver()
    {
        $pool = $this->getTestPool();

        $stash = $pool->getItem('test');
        $this->assertAttributeInstanceOf('Stash\Driver\Ephemeral', 'driver', $stash, 'set driver is pushed to new stash objects');
    }

    public function testSetItemClass()
    {
        $mockItem = $this->getMock('Stash\Interfaces\ItemInterface');
        $mockClassName = get_class($mockItem);
        $pool = $this->getTestPool();

        $this->assertTrue($pool->setItemClass($mockClassName));
        $this->assertAttributeEquals($mockClassName, 'itemClass', $pool);
    }

    public function testGetItem()
    {
        $pool = $this->getTestPool();

        $stash = $pool->getItem('base', 'one');
        $this->assertInstanceOf('Stash\Item', $stash, 'getItem returns a Stash\Item object');

        $stash->set($this->data);
        $storedData = $stash->get();
        $this->assertEquals($this->data, $storedData, 'getItem returns working Stash\Item object');

        $key = $stash->getKey();
        $this->assertEquals('base/one', $key, 'Pool sets proper Item key.');

        $pool->setNamespace('TestNamespace');
        $item = $pool->getItem(array('test', 'item'));

        $this->assertAttributeEquals('TestNamespace', 'namespace', $item, 'Pool sets Item namespace.');

    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Item constructor requires a key.
     */
    public function testGetItemInvalidKey()
    {
        $pool = $this->getTestPool();
        $item = $pool->getItem();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid or Empty Node passed to getItem constructor.
     */
    public function testGetItemInvalidKeyMissingNode()
    {
        $pool = $this->getTestPool();
        $item = $pool->getItem('This/Test//Fail');
    }

    public function testGetItemIterator()
    {
        $pool = $this->getTestPool();

        $keys = array_keys($this->multiData);

        $cacheIterator = $pool->getItemIterator($keys);
        $keyData = $this->multiData;
        foreach ($cacheIterator as $stash) {
            $key = $stash->getKey();
            $this->assertTrue($stash->isMiss(), 'new Cache in iterator is empty');
            $stash->set($keyData[$key]);
            unset($keyData[$key]);
        }
        $this->assertCount(0, $keyData, 'all keys are accounted for the in cache iterator');

        $cacheIterator = $pool->getItemIterator($keys);
        foreach ($cacheIterator as $stash) {
            $key = $stash->getKey();
            $data = $stash->get($key);
            $this->assertEquals($this->multiData[$key], $data, 'data put into the pool comes back the same through iterators.');
        }
    }

    public function testFlushCache()
    {
        $pool = $this->getTestPool();

        $stash = $pool->getItem('base', 'one');
        $stash->set($this->data);
        $this->assertTrue($pool->flush(), 'clear returns true');

        $stash = $pool->getItem('base', 'one');
        $this->assertNull($stash->get(), 'clear removes item');
        $this->assertTrue($stash->isMiss(), 'clear causes cache miss');
    }

    public function testFlushNamespacedCache()
    {
        $pool = $this->getTestPool();

        // No Namespace
        $item = $pool->getItem(array('base', 'one'));
        $item->set($this->data);

        // TestNamespace
        $pool->setNamespace('TestNamespace');
        $item = $pool->getItem(array('test', 'one'));
        $item->set($this->data);

        // TestNamespace2
        $pool->setNamespace('TestNamespace2');
        $item = $pool->getItem(array('test', 'one'));
        $item->set($this->data);

        // Flush TestNamespace
        $pool->setNamespace('TestNamespace');
        $this->assertTrue($pool->flush(), 'Flush succeeds with namespace selected.');

        // Return to No Namespace
        $pool->setNamespace();
        $item = $pool->getItem(array('base', 'one'));
        $this->assertFalse($item->isMiss(), 'Base item exists after other namespace was flushed.');
        $this->assertEquals($this->data, $item->get(), 'Base item returns data after other namespace was flushed.');

        // Flush All
        $this->assertTrue($pool->flush(), 'Flush succeeds with no namespace.');

        // Return to TestNamespace2
        $pool->setNamespace('TestNamespace2');
        $item = $pool->getItem(array('base', 'one'));
        $this->assertTrue($item->isMiss(), 'Namespaced item disappears after complete flush.');
    }

    public function testPurgeCache()
    {
        $pool = $this->getTestPool();

        $stash = $pool->getItem('base', 'one');
        $stash->set($this->data, -600);
        $this->assertTrue($pool->purge(), 'purge returns true');

        $stash = $pool->getItem('base', 'one');
        $this->assertNull($stash->get(), 'purge removes item');
        $this->assertTrue($stash->isMiss(), 'purge causes cache miss');
    }

    public function testNamespacing()
    {
        $pool = $this->getTestPool();

        $this->assertAttributeEquals(null, 'namespace', $pool, 'Namespace starts empty.');
        $this->assertTrue($pool->setNamespace('TestSpace'), 'setNamespace returns true.');
        $this->assertAttributeEquals('TestSpace', 'namespace', $pool, 'setNamespace sets the namespace.');
        $this->assertEquals('TestSpace', $pool->getNamespace(), 'getNamespace returns current namespace.');

        $this->assertTrue($pool->setNamespace(), 'setNamespace returns true when setting null.');
        $this->assertAttributeEquals(null, 'namespace', $pool, 'setNamespace() empties namespace.');
        $this->assertFalse($pool->getNamespace(), 'getNamespace returns false when no namespace is set.');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Namespace must be alphanumeric.
     */
    public function testInvalidNamespace()
    {
        $pool = $this->getTestPool();
        $pool->setNamespace('!@#$%^&*(');
    }

    public function testgetItemArrayConversion()
    {
        $pool = $this->getTestPool();

        $cache = $pool->getItem(array('base', 'one'));
        $this->assertEquals($cache->getKey(), 'base/one');
    }

    protected function getTestPool()
    {
        $driver = new Ephemeral(array());
        $pool = new Pool();
        $pool->setDriver($driver);

        return $pool;
    }
}
