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
 * @package Interfaces
 * @subpackage Resources
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 */

namespace Imbo\Resource;

use Imbo\Container,
    Imbo\EventManager\EventManagerInterface;

/**
 * Resource interface
 *
 * Available resources must implement this interface. They can also extend the abstract resource
 * class (Imbo\Resource\Resource) for convenience.
 *
 * @package Interfaces
 * @subpackage Resources
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 */
interface ResourceInterface {
    /**#@+
     * Resource types
     *
     * The values of the constants maps to the names (appended with Resource) used in
     * Imbo\Container for the resource entries.
     *
     * @var string
     */
    const STATUS   = 'status';
    const USER     = 'user';
    const IMAGES   = 'images';
    const IMAGE    = 'image';
    const METADATA = 'metadata';
    /**#@-*/

    /**
     * Return an array with the allowed (implemented) HTTP methods for the current resource
     *
     * @return string[]
     */
    function getAllowedMethods();

    /**
     * POST handler
     *
     * @param Container $container A container instance
     */
    function post(Container $container);

    /**
     * GET handler
     *
     * @param Container $container A container instance
     */
    function get(Container $container);

    /**
     * HEAD handler
     *
     * @param Container $container A container instance
     */
    function head(Container $container);

    /**
     * DELETE handler
     *
     * @param Container $container A container instance
     */
    function delete(Container $container);

    /**
     * PUT handler
     *
     * @param Container $container A container instance
     */
    function put(Container $container);

    /**
     * Set the event manager
     *
     * @param EventManagerInterface $eventManager An instance of an event manager
     * @return ResourceInterface
     */
    function setEventManager(EventManagerInterface $eventManager);
}
