<?php
/**
 * Imbo
 *
 * Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * * The above copyright notice and this permission notice shall be included in
 *   all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package TestSuite\UnitTests
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 */

namespace Imbo\UnitTest\Database;

use Imbo\Database\MongoDB,
    MongoException,
    InvalidArgumentException,
    ReflectionMethod;

/**
 * @package TestSuite\UnitTests
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 * @covers Imbo\Database\MongoDB
 */
class MongoDBTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Imbo\Database\MongoDB
     */
    private $driver;

    /**
     * @var Mongo
     */
    private $mongo;

    /**
     * @var MongoCollection
     */
    private $collection;

    /**
     * Set up the mongo and collection mocks and the driver that we want to test
     */
    public function setUp() {
        if (!extension_loaded('mongo')) {
            $this->markTestSkipped('pecl/mongo is required to run this test');
        }

        $this->mongo = $this->getMockBuilder('Mongo')->disableOriginalConstructor()->getMock();
        $this->collection = $this->getMockBuilder('MongoCollection')->disableOriginalConstructor()->getMock();
        $this->driver = new MongoDB(array(), $this->mongo, $this->collection);
    }

    /**
     * Teardown the instances
     */
    public function tearDown() {
        $this->mongo = null;
        $this->collection = null;
        $this->driver = null;
    }

    /**
     * @covers Imbo\Database\MongoDB::getStatus
     */
    public function testGetStatusWhenMongoIsNotConnectable() {
        $this->mongo->expects($this->once())->method('connect')->will($this->returnValue(false));
        $this->assertFalse($this->driver->getStatus());
    }

    /**
     * @covers Imbo\Database\MongoDB::getStatus
     */
    public function testGetStatusWhenMongoIsConnectable() {
        $this->mongo->expects($this->once())->method('connect')->will($this->returnValue(true));
        $this->assertTrue($this->driver->getStatus());
    }

    /**
     * @covers Imbo\Database\MongoDB::getStatus
     */
    public function testDottedNotationForMetadataQuery() {
        $publicKey = 'key';

        $query = $this->getMock('Imbo\Resource\Images\QueryInterface');
        $query->expects($this->once())->method('from')->will($this->returnValue(null));
        $query->expects($this->once())->method('to')->will($this->returnValue(null));
        $query->expects($this->once())->method('metadataQuery')->will($this->returnValue(array(
            'style' => 'IPA',
            'brewery' => 'Nøgne Ø',
        )));
        $query->expects($this->any())->method('limit')->will($this->returnValue(10));

        $cursor = $this->getMockBuilder('MongoCursor')->disableOriginalConstructor()->getMock();
        $cursor->expects($this->once())->method('limit')->with(10)->will($this->returnSelf());
        $cursor->expects($this->once())->method('sort')->will($this->returnSelf());

        $this->collection->expects($this->once())->method('find')->with(array(
            'publicKey' => $publicKey,
            'metadata.style' => 'IPA',
            'metadata.brewery' => 'Nøgne Ø',
        ), $this->isType('array'))->will($this->returnValue($cursor));

        $this->assertSame(array(), $this->driver->getImages($publicKey, $query));
    }

    /**
     * @covers Imbo\Database\MongoDB::insertImage
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to save image data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringInsertImage() {
        $this->collection->expects($this->once())
                         ->method('findOne')
                         ->will($this->throwException(new MongoException()));

        $this->driver->insertImage('key', 'identifier', $this->getMock('Imbo\Image\ImageInterface'));
    }

    /**
     * @covers Imbo\Database\MongoDB::deleteImage
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to delete image data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringDeleteImage() {
        $this->collection->expects($this->once())
                         ->method('findOne')
                         ->will($this->throwException(new MongoException()));

        $this->driver->deleteImage('key', 'identifier');
    }

    /**
     * @covers Imbo\Database\MongoDB::updateMetadata
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to update meta data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringUpdateMetadata() {
        $this->collection->expects($this->once())
                         ->method('findOne')
                         ->will($this->returnValue(array('some' => 'data')));

        $this->collection->expects($this->once())
                         ->method('update')
                         ->will($this->throwException(new MongoException()));

        $this->driver->updateMetadata('key', 'identifier', array('key' => 'value'));
    }

    /**
     * @covers Imbo\Database\MongoDB::getMetadata
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to fetch meta data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringGetMetadata() {
        $this->collection->expects($this->once())
                         ->method('findOne')
                         ->will($this->throwException(new MongoException()));

        $this->driver->getMetadata('key', 'identifier');
    }

    /**
     * @covers Imbo\Database\MongoDB::deleteMetadata
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to delete meta data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringDeleteMetadata() {
        $this->collection->expects($this->once())
                         ->method('findOne')
                         ->will($this->throwException(new MongoException()));

        $this->driver->deleteMetadata('key', 'identifier');
    }

    /**
     * @covers Imbo\Database\MongoDB::getImages
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to search for images
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringGetImages() {
        $this->collection->expects($this->once())
                         ->method('find')
                         ->will($this->throwException(new MongoException()));

        $this->driver->getImages('key', $this->getMock('Imbo\Resource\Images\QueryInterface'));
    }

    /**
     * @covers Imbo\Database\MongoDB::load
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to fetch image data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringLoad() {
        $this->collection->expects($this->once())
                         ->method('findOne')
                         ->will($this->throwException(new MongoException()));

        $this->driver->load('key', 'identifier', $this->getMock('Imbo\Image\ImageInterface'));
    }

    /**
     * @covers Imbo\Database\MongoDB::getLastModified
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to fetch image data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringGetLastModified() {
        $this->collection->expects($this->once())
                         ->method('find')
                         ->will($this->throwException(new MongoException()));

        $this->driver->getLastModified('key');
    }

    /**
     * @covers Imbo\Database\MongoDB::getNumImages
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to fetch information from the database
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringGetNumImages() {
        $this->collection->expects($this->once())
                         ->method('find')
                         ->will($this->throwException(new MongoException()));

        $this->driver->getNumImages('key');
    }

    /**
     * @covers Imbo\Database\MongoDB::getImageMimeType
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Unable to fetch image meta data
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenMongoFailsDuringGetImageMimeType() {
        $this->collection->expects($this->once())
                         ->method('findOne')
                         ->will($this->throwException(new MongoException()));

        $this->driver->getImageMimeType('key', 'identifier');
    }

    /**
     * @covers Imbo\Database\MongoDB::getCollection
     * @expectedException Imbo\Exception\DatabaseException
     * @expectedExceptionMessage Could not select collection
     * @expectedExceptionCode 500
     */
    public function testThrowsExceptionWhenNotAbleToGetCollection() {
        $driver = new MongoDB(array(), $this->mongo);

        $this->mongo->expects($this->once())
                    ->method('selectCollection')
                    ->will($this->throwException(new InvalidArgumentException()));

        $method = new ReflectionMethod('Imbo\Database\MongoDB', 'getCollection');
        $method->setAccessible(true);
        $method->invoke($driver);
    }
}
