<?php
namespace Shel\EmbedContent\Service;

/*                                                                        *
 * This script belongs to the Flow package "Shel.EmbedContent".           *
 *                                                                        *
 * @author Sebastian Helzle <sebastian@helzle.it>                         *
 *                                                                        */

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Neos\Flow\Http\Uri;
use QueryPath\DOMQuery;
use QueryPath\Exception;
use Shel\EmbedContent\Log\RequestLoggerInterface;

/**
 * Provides CLI features for academy events handling
 *
 * @Flow\Scope("singleton")
 */
class RequestContentService
{

    /**
     * @Flow\InjectConfiguration(path="requestService", package="Shel.EmbedContent")
     * @var array
     */
    protected $settings;

    /**
     * @var array
     */
    protected $requestEngineOptions = [
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];

    /**
     * @Flow\Inject
     * @var RequestLoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var VariableFrontend
     */
    protected $dataCache;

    /**
     * @var array
     */
    protected $objectCache = [];

    /**
     * Creates a valid cache identifier.
     *
     * @param $identifier
     * @return string
     */
    protected function getCacheKey($identifier)
    {
        return sha1(self::class . '__' . strtolower($identifier));
    }

    /**
     * @param string $cacheKey
     * @return mixed
     */
    protected function getItem($cacheKey)
    {
        if (array_key_exists($cacheKey, $this->objectCache)) {
            return $this->objectCache[$cacheKey];
        }
        $item = $this->dataCache->get($cacheKey);
        $this->objectCache[$cacheKey] = $item;

        return $item;
    }

    /**
     * @param string $cacheKey
     * @param mixed $value
     * @param array $tags
     */
    protected function setItem($cacheKey, $value, $tags = [])
    {
        $this->objectCache[$cacheKey] = $value;
        $this->dataCache->set($cacheKey, $value, $tags);
    }

    /**
     * @param string $cacheKey
     */
    protected function unsetItem($cacheKey)
    {
        unset($this->objectCache[$cacheKey]);
        $this->dataCache->remove($cacheKey);
    }

    /**
     * Extracts text only nodes from a query
     *
     * @param DOMQuery $query
     * @return bool|DOMQuery
     */
    protected function extractTextNodes(DOMQuery $query)
    {
        try {
            return $query->contents()->filterCallback(function ($i, \DOMNode $item) {
                // Extract text only nodes
                return $item->nodeType === 3;
            });
        } catch (Exception $e) {
            return new DOMQuery();
        }
    }

    /**
     * Formats values from html for storing
     *
     * @param string $value
     * @return string
     */
    protected function formatValue($value)
    {
        return trim($value);
    }

    /**
     * @param string $html
     * @return \QueryPath\DOMQuery
     */
    protected function getParsedHtml($html = '')
    {
        return html5qp($html);
    }

    /**
     * Retrieves data from the given action uri
     *
     * @param string $actionName
     * @param array $additionalParameters
     * @return array|bool
     */
    protected function fetchData($actionName, array $additionalParameters = [])
    {
        $requestUri = $this->buildRequestUri($actionName, $additionalParameters);
        $response = $this->fetchDataInternal($requestUri);

        return $response !== false ? $response->getContent() : '';
    }

    /**
     * @param string $requestUri
     * @return bool|\Neos\Flow\Http\Response
     */
    protected function fetchDataInternal($requestUri)
    {
        $browser = $this->getBrowser();
        try {
            $response = $browser->request($requestUri, 'GET');
        } catch (\Exception $e) {
            $this->logger->log(sprintf('Get request to Api failed with exception "%s"!', $e->getMessage()),
                LOG_ERR, 1512153846);
            $response = false;
        }

        if ($response !== false && $response->getStatusCode() !== 200) {
            $this->logger->log(sprintf('Get request to Api failed with code "%s"!', $response->getStatus()),
                LOG_ERR, 1512153847);
        }

        return $response;
    }

    /**
     * Returns a browser instance with curl engine and authentication parameters set
     *
     * @return Browser
     */
    protected function getBrowser()
    {
        $browser = new Browser();
        $curlEngine = new CurlEngine();
        foreach ($this->requestEngineOptions as $option => $value) {
            $curlEngine->setOption($option, $value);
        }
        $curlEngine->setOption(CURLOPT_CONNECTTIMEOUT, intval($this->settings['timeout']));
        $browser->setRequestEngine($curlEngine);

        if (array_key_exists('username', $this->settings) && !empty($this->settings['username'])
            && array_key_exists('password', $this->settings) && !empty($this->settings['password'])
        ) {
            $browser->addAutomaticRequestHeader('Authorization',
                'Basic ' . base64_encode($this->settings['username'] . ':' . $this->settings['password']));
        }

        return $browser;
    }

    /**
     * @param string $actionName
     * @param array $additionalParameters
     *
     * @return Uri
     */
    protected function buildRequestUri($actionName, array $additionalParameters = [])
    {
        $requestUri = new Uri($this->settings['baseUrl']);
        $requestUri->setPath($requestUri->getPath() . $this->settings['actions'][$actionName]);
        $requestUri->setQuery(http_build_query(array_merge($this->settings['parameters'], $additionalParameters)));
        return $requestUri;
    }
}
