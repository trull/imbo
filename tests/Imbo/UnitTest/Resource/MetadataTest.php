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

namespace Imbo\UnitTest\Resource;

use Imbo\Resource\Metadata;

/**
 * @package TestSuite\UnitTests
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 * @covers Imbo\Resource\Metadata
 */
class MetadataTest extends ResourceTests {
    protected function getNewResource() {
        return new Metadata();
    }

    /**
     * @covers Imbo\Resource\Metadata::delete
     */
    public function testDelete() {
        $this->request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue($this->imageIdentifier));
        $this->request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $this->database->expects($this->once())->method('deleteMetadata')->with($this->publicKey, $this->imageIdentifier);

        $this->response->expects($this->once())->method('setBody')->with($this->isType('array'));

        $this->getNewResource()->delete($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::post
     * @expectedException Imbo\Exception\InvalidArgumentException
     * @expectedExceptionMessage Missing JSON data
     * @expectedExceptionCode 400
     */
    public function testPostWithNoMetadata() {
        $paramContainer = $this->getMock('Imbo\Http\ParameterContainerInterface');
        $paramContainer->expects($this->once())->method('has')->with('metadata')->will($this->returnValue(false));

        $this->request->expects($this->any())->method('getRequest')->will($this->returnValue($paramContainer));
        $this->request->expects($this->any())->method('getRawData')->will($this->returnValue(null));

        $this->getNewResource()->post($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::post
     * @expectedException Imbo\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid JSON data
     * @expectedExceptionCode 400
     */
    public function testPostWithInvalidMetadata() {
        $paramContainer = $this->getMock('Imbo\Http\ParameterContainerInterface');
        $paramContainer->expects($this->once())->method('has')->with('metadata')->will($this->returnValue(false));

        $this->request->expects($this->any())->method('getRequest')->will($this->returnValue($paramContainer));
        $this->request->expects($this->any())->method('getRawData')->will($this->returnValue('some string'));

        $this->getNewResource()->post($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::post
     */
    public function testPostWithDataInPostParams() {
        $this->request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue($this->imageIdentifier));

        $paramContainer = $this->getMock('Imbo\Http\ParameterContainerInterface');
        $paramContainer->expects($this->once())->method('has')->with('metadata')->will($this->returnValue(true));
        $paramContainer->expects($this->once())->method('get')->with('metadata')->will($this->returnValue('{"foo":"bar"}'));

        $this->request->expects($this->any())->method('getRequest')->will($this->returnValue($paramContainer));
        $this->database->expects($this->once())->method('updateMetadata')->with($this->publicKey, $this->imageIdentifier, array('foo' => 'bar'));
        $this->request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));

        $this->response->expects($this->once())->method('setBody')->with($this->isType('array'));

        $this->getNewResource()->post($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::post
     */
    public function testPostWithDataInRawBody() {
        $this->request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue($this->imageIdentifier));

        $paramContainer = $this->getMock('Imbo\Http\ParameterContainerInterface');
        $paramContainer->expects($this->once())->method('has')->with('metadata')->will($this->returnValue(false));

        $this->request->expects($this->once())->method('getRequest')->will($this->returnValue($paramContainer));
        $this->request->expects($this->once())->method('getRawData')->will($this->returnValue('{"some":"value"}'));

        $this->database->expects($this->once())->method('updateMetadata')->with($this->publicKey, $this->imageIdentifier, array('some' => 'value'));
        $this->request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));

        $this->response->expects($this->once())->method('setBody')->with($this->isType('array'));

        $this->getNewResource()->post($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::put
     * @expectedException Imbo\Exception\InvalidArgumentException
     * @expectedExceptionMessage Missing JSON data
     * @expectedExceptionCode 400
     */
    public function testPutWithNoMetadata() {
        $this->request->expects($this->any())->method('getRawData')->will($this->returnValue(null));

        $this->getNewResource()->put($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::put
     * @expectedException Imbo\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid JSON data
     * @expectedExceptionCode 400
     */
    public function testPutWithInvalidMetadata() {
        $this->request->expects($this->any())->method('getRawData')->will($this->returnValue('some string'));
        $this->getNewResource()->put($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::put
     */
    public function testSuccessfulPut() {
        $this->request->expects($this->any())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $this->request->expects($this->any())->method('getImageIdentifier')->will($this->returnValue($this->imageIdentifier));
        $this->request->expects($this->any())->method('getRawData')->will($this->returnValue('{"key":"value"}'));

        $this->database->expects($this->once())->method('deleteMetadata')->with($this->publicKey, $this->imageIdentifier);
        $this->database->expects($this->once())->method('updateMetadata')->with($this->publicKey, $this->imageIdentifier, array('key' => 'value'));

        $this->response->expects($this->once())->method('setBody')->with($this->isType('array'));

        $this->getNewResource()->put($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::get
     */
    public function testGetWhenResponseIsNotModified() {
        $formattedDate = 'Mon, 10 Jan 2011 13:37:00 GMT';
        $lastModified = $this->getMock('DateTime');
        $lastModified->expects($this->once())->method('format')->will($this->returnValue(substr($formattedDate, 0, -4)));

        $etag = '"' . md5($this->publicKey . $this->imageIdentifier . $formattedDate) . '"';

        $this->request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $this->request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue($this->imageIdentifier));

        $requestHeaders = $this->getMock('Imbo\Http\HeaderContainer');
        $requestHeaders->expects($this->any())->method('get')->will($this->returnCallback(function($param) use ($formattedDate, $etag) {
            if ($param === 'if-modified-since') {
                return $formattedDate;
            } else if ($param === 'if-none-match') {
                return $etag;
            }
        }));

        $this->request->expects($this->once())->method('getHeaders')->will($this->returnValue($requestHeaders));

        $responseHeaders = $this->getMock('Imbo\Http\HeaderContainer');
        $responseHeaders->expects($this->once())->method('set')->with('ETag', $etag);

        $this->response->expects($this->once())->method('getHeaders')->will($this->returnValue($responseHeaders));
        $this->database->expects($this->once())->method('getLastModified')->with($this->publicKey, $this->imageIdentifier)->will($this->returnValue($lastModified));

        $this->response->expects($this->once())->method('setNotModified');

        $this->getNewResource()->get($this->container);
    }

    /**
     * @covers Imbo\Resource\Metadata::get
     */
    public function testGetWhenResponseIsModified() {
        $formattedDate = 'Mon, 10 Jan 2011 13:37:00 GMT';

        $lastModified = $this->getMock('DateTime');
        $lastModified->expects($this->once())->method('format')->will($this->returnValue(substr($formattedDate, 0, -4)));

        $etag = '"' . md5($this->publicKey . $this->imageIdentifier . $formattedDate) . '"';
        $metadataInDatabase = array('foo' => 'bar');

        $this->request->expects($this->once())->method('getPublicKey')->will($this->returnValue($this->publicKey));
        $this->request->expects($this->once())->method('getImageIdentifier')->will($this->returnValue($this->imageIdentifier));

        $requestHeaders = $this->getMock('Imbo\Http\HeaderContainer');
        $this->request->expects($this->once())->method('getHeaders')->will($this->returnValue($requestHeaders));

        $responseHeaders = $this->getMock('Imbo\Http\HeaderContainer');
        $responseHeaders->expects($this->at(0))->method('set')->with('ETag', $etag);
        $responseHeaders->expects($this->at(1))->method('set')->with('Last-Modified', $formattedDate);
        $this->response->expects($this->once())->method('getHeaders')->will($this->returnValue($responseHeaders));

        $this->database->expects($this->once())->method('getLastModified')->with($this->publicKey, $this->imageIdentifier)->will($this->returnValue($lastModified));

        $this->database->expects($this->once())->method('getMetadata')->with($this->publicKey, $this->imageIdentifier)->will($this->returnValue($metadataInDatabase));

        $this->response->expects($this->once())->method('setBody')->with($metadataInDatabase);

        $this->getNewResource()->get($this->container);
    }
}
