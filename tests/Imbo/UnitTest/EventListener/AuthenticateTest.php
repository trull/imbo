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

namespace Imbo\UnitTest\EventListener;

use Imbo\EventListener\Authenticate;

/**
 * @package TestSuite\UnitTests
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 * @covers Imbo\EventListener\Authenticate
 */
class AuthenticateTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Imbo\EventListener\Authenticate
     */
    private $listener;

    /**
     * @var Imbo\EventManager\EventInterface
     */
    private $event;

    /**
     * @var Imbo\Http\Request\RequestInterface
     */
    private $request;

    /**
     * @var Imbo\Http\Response\ResponseInterface
     */
    private $response;

    /**
     * @var Imbo\Http\ParameterContainerInterface
     */
    private $query;

    /**
     * @var Imbo\Container
     */
    private $container;

    /**
     * Set up method
     */
    public function setUp() {
        $this->query = $this->getMock('Imbo\Http\ParameterContainerInterface');

        $request = $this->getMock('Imbo\Http\Request\RequestInterface');
        $request->expects($this->any())->method('getQuery')->will($this->returnValue($this->query));
        $response = $this->getMock('Imbo\Http\Response\ResponseInterface');

        $config = array('auth' => array('publicKey' => 'privateKey'));

        $this->container = $this->getMock('Imbo\Container');
        $this->container->expects($this->any())->method('get')->will($this->returnCallback(function($key) use($request, $response, $config) {
            return $$key;
        }));

        $this->request = $request;
        $this->response = $response;

        $this->event = $this->getMock('Imbo\EventManager\EventInterface');
        $this->event->expects($this->any())->method('getContainer')->will($this->returnValue($this->container));

        $this->listener = new Authenticate();
    }

    /**
     * Tear down method
     */
    public function tearDown() {
        $this->request = null;
        $this->response = null;
        $this->event = null;
        $this->query = null;
        $this->listener = null;
        $this->container = null;
    }

    /**
     * @covers Imbo\EventListener\Authenticate::invoke
     * @expectedException Imbo\Exception\RuntimeException
     * @expectedExceptionMessage Missing required authentication parameter: signature
     * @expectedExceptionCode 400
     */
    public function testAuthWithMissingSignature() {
        $this->query->expects($this->any())->method('has')->with('signature')->will($this->returnValue(false));
        $this->listener->invoke($this->event);
    }

    /**
     * @covers Imbo\EventListener\Authenticate::invoke
     * @expectedException Imbo\Exception\RuntimeException
     * @expectedExceptionMessage Missing required authentication parameter: timestamp
     * @expectedExceptionCode 400
     */
    public function testAuthWithMissingTimestamp() {
        $this->query->expects($this->any())->method('has')->will($this->returnCallback(function($arg) {
            if ($arg === 'signature') {
                return true;
            }

            return false;
        }));

        $this->listener->invoke($this->event);
    }

    /**
     * @covers Imbo\EventListener\Authenticate::invoke
     * @expectedException Imbo\Exception\RuntimeException
     * @expectedExceptionMessage Invalid timestamp:
     * @expectedExceptionCode 400
     */
    public function testAuthWithInvalidTimestamp() {
        $this->query->expects($this->any())->method('has')->will($this->returnValue(true));
        $this->query->expects($this->any())->method('get')->will($this->returnCallback(function($arg) {
            if ($arg === 'timestamp') {
                return 'some string';
            }

            return 'signature';
        }));

        $this->query->expects($this->any())->method('remove')->will($this->returnSelf());

        $this->listener->invoke($this->event);
    }

    /**
     * @covers Imbo\EventListener\Authenticate::invoke
     * @expectedException Imbo\Exception\RuntimeException
     * @expectedExceptionMessage Timestamp has expired: 2010-10-10T20:10:10Z
     * @expectedExceptionCode 400
     */
    public function testAuthWithExpiredTimestamp() {
        $this->query->expects($this->any())->method('has')->will($this->returnValue(true));
        $this->query->expects($this->any())->method('get')->will($this->returnCallback(function($arg) {
            if ($arg === 'timestamp') {
                return '2010-10-10T20:10:10Z';
            }

            return 'signature';
        }));

        $this->query->expects($this->any())->method('remove')->will($this->returnSelf());

        $this->listener->invoke($this->event);
    }

    /**
     * @covers Imbo\EventListener\Authenticate::invoke
     * @expectedException Imbo\Exception\RuntimeException
     * @expectedExceptionMessage Signature mismatch
     * @expectedExceptionCode 400
     */
    public function testAuthWithSignatureMismatch() {
        $this->query->expects($this->any())->method('has')->will($this->returnValue(true));
        $this->query->expects($this->any())->method('remove')->will($this->returnSelf());
        $this->query->expects($this->any())->method('get')->will($this->returnCallback(function($arg) {
            if ($arg === 'timestamp') {
                return gmdate('Y-m-d\TH:i:s\Z');
            }

            return 'signature';
        }));

        $this->response->expects($this->once())->method('getHeaders')->will($this->returnValue($this->getMock('Imbo\Http\HeaderContainer')));

        $this->listener->invoke($this->event);
    }
}
