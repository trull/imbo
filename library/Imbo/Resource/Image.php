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
 * @package Resources
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 */

namespace Imbo\Resource;

use Imbo\Container,
    Imbo\Http\Request\RequestInterface,
    Imbo\Image\Image as ImageObject,
    Imbo\Image\ImageInterface,
    Imbo\Image\ImagePreparation,
    Imbo\Image\ImagePreparationInterface,
    Imbo\Exception\StorageException,
    Imbo\Exception\ResourceException,
    Imbo\Image\Transformation\Convert,
    Imbo\Image\Transformation\TransformationInterface,
    Imbo\Http\ContentNegotiation,
    Imbo\Resource\ImageInterface as ImageResourceInterface;

/**
 * Image resource
 *
 * @package Resources
 * @author Christer Edvartsen <cogo@starzinger.net>
 * @copyright Copyright (c) 2011-2012, Christer Edvartsen <cogo@starzinger.net>
 * @license http://www.opensource.org/licenses/mit-license MIT License
 * @link https://github.com/imbo/imbo
 */
class Image extends Resource implements ImageResourceInterface {
    /**
     * Image for the client
     *
     * @var ImageInterface
     */
    private $image;

    /**
     * Image prepation instance
     *
     * @var ImagePreparation
     */
    private $imagePreparation;

    /**
     * Content negotiation instance
     *
     * @var ContentNegotiation
     */
    private $contentNegotiation;

    /**
     * An array of registered transformation handlers
     *
     * @var array
     */
    private $transformationHandlers = array();

    /**
     * Class constructor
     *
     * @param ImageInterface $image An image instance
     * @param ImagePreparationInterface $imagePreparation An image preparation instance
     * @param ContentNegotiation $contentNegotiation Content negotiation instance
     */
    public function __construct(ImageInterface $image = null, ImagePreparationInterface $imagePreparation = null, ContentNegotiation $contentNegotiation = null) {
        if ($image === null) {
            $image = new ImageObject();
        }

        if ($imagePreparation === null) {
            $imagePreparation = new ImagePreparation();
        }

        if ($contentNegotiation === null) {
            $contentNegotiation = new ContentNegotiation();
        }

        $this->image = $image;
        $this->imagePreparation = $imagePreparation;
        $this->contentNegotiation = $contentNegotiation;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedMethods() {
        return array(
            RequestInterface::METHOD_GET,
            RequestInterface::METHOD_HEAD,
            RequestInterface::METHOD_DELETE,
            RequestInterface::METHOD_PUT,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function put(Container $container) {
        // Prepare the image based on the input stream in the request
        $this->imagePreparation->prepareImage($container->request, $this->image);
        $this->eventManager->trigger('image.put.imagepreparation.post');

        $request = $container->request;
        $response = $container->response;
        $database = $container->database;
        $storage = $container->storage;

        $publicKey = $request->getPublicKey();
        $imageIdentifier = $request->getRealImageIdentifier();

        // Insert the image to the database
        $database->insertImage($publicKey, $imageIdentifier, $this->image);

        // Store the image
        try {
            $storage->store($publicKey, $imageIdentifier, $this->image->getBlob());
        } catch (StorageException $e) {
            // Remove image from the database
            $database->deleteImage($publicKey, $imageIdentifier);

            throw $e;
        }

        $response->setStatusCode(201)
                 ->setBody(array('imageIdentifier' => $imageIdentifier));
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Container $container) {
        $request = $container->request;
        $response = $container->response;
        $database = $container->database;
        $storage = $container->storage;

        $publicKey = $request->getPublicKey();
        $imageIdentifier = $request->getImageIdentifier();

        $database->deleteImage($publicKey, $imageIdentifier);
        $storage->delete($publicKey, $imageIdentifier);

        $response->setBody(array(
            'imageIdentifier' => $imageIdentifier,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function get(Container $container) {
        $request = $container->request;
        $response = $container->response;
        $database = $container->database;
        $storage = $container->storage;

        $publicKey = $request->getPublicKey();
        $imageIdentifier = $request->getImageIdentifier();
        $serverContainer = $request->getServer();
        $requestHeaders = $request->getHeaders();
        $responseHeaders = $response->getHeaders();

        // Fetch information from the database (injects mime type, width and height to the
        // image instance)
        $database->load($publicKey, $imageIdentifier, $this->image);

        // Generate ETag using public key, image identifier, Accept headers of the user agent and
        // the requested URI
        $etag = '"' . md5(
            $publicKey .
            $imageIdentifier .
            $requestHeaders->get('Accept') .
            $serverContainer->get('REQUEST_URI')
        ) . '"';

        // Fetch formatted last modified timestamp from the storage driver
        $lastModified = $this->formatDate($storage->getLastModified($publicKey, $imageIdentifier));

        // Add the ETag to the response headers
        $responseHeaders->set('ETag', $etag);

        if (
            $lastModified === $requestHeaders->get('if-modified-since') &&
            $etag === $requestHeaders->get('if-none-match')
        ) {
            $response->setNotModified();
            return;
        }

        // Fetch the image data and store the data in the image instance
        $imageData = $storage->getImage($publicKey, $imageIdentifier);
        $this->image->setBlob($imageData);

        // Set some response headers before we apply optional transformations
        $responseHeaders
            // Set the last modification date
            ->set('Last-Modified', $lastModified)

            // Set the max-age to a year since the image never changes
            ->set('Cache-Control', 'max-age=31536000')

            // Custom Imbo headers
            ->set('X-Imbo-OriginalMimeType', $this->image->getMimeType())
            ->set('X-Imbo-OriginalWidth', $this->image->getWidth())
            ->set('X-Imbo-OriginalHeight', $this->image->getHeight())
            ->set('X-Imbo-OriginalFileSize', $this->image->getFilesize())
            ->set('X-Imbo-OriginalExtension', $this->image->getExtension());

        // Fetch and apply transformations
        $transformations = $request->getTransformations();

        foreach ($transformations as $transformation) {
            $name = $transformation['name'];

            if (!isset($this->transformationHandlers[$name])) {
                throw new ResourceException('Unknown transformation: ' . $name, 400);
            }

            $callback = $this->transformationHandlers[$name];
            $transformation = $callback($transformation['params']);

            if ($transformation instanceof TransformationInterface) {
                $transformation->applyToImage($this->image);
            } else if (is_callable($transformation)) {
                $transformation($this->image);
            }
        }

        // See if we want to trigger a conversion. This happens if the user agent has specified an
        // image type in the URI, or if the user agent does not accept the original content type of
        // the requested image.
        $extension = $request->getExtension();
        $imageType = $this->image->getMimeType();
        $acceptableTypes = $request->getAcceptableContentTypes();

        if (!$extension && !$this->contentNegotiation->isAcceptable($imageType, $acceptableTypes)) {
            $typesToCheck = ImageObject::$mimeTypes;

            $match = $this->contentNegotiation->bestMatch(array_keys($typesToCheck), $acceptableTypes);

            if (!$match) {
                throw new ResourceException('Not Acceptable', 406);
            }

            if ($match !== $imageType) {
                // The match is of a different type than the original image
                $extension = $typesToCheck[$match];
            }
        }

        if ($extension) {
            // Trigger a conversion
            $callback = $container->config['transformations']['convert'];

            $convert = $callback(array('type' => $extension));
            $convert->applyToImage($this->image);
        }

        // Set the content length and content-type after transformations have been applied
        $imageData = $this->image->getBlob();
        $responseHeaders->set('Content-Length', strlen($imageData))
                        ->set('Content-Type', $this->image->getMimeType());

        $response->setBody($imageData);
    }

    /**
     * {@inheritdoc}
     */
    public function head(Container $container) {
        $this->get($container);

        // Remove body from the response, but keep everything else
        $container->response->setBody(null);
    }

    /**
     * {@inheritdoc}
     */
    public function registerTransformationHandler($name, $callback) {
        $this->transformationHandlers[$name] = $callback;

        return $this;
    }
}
