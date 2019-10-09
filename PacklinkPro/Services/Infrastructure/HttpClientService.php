<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Services\Infrastructure;

use Packlink\PacklinkPro\Helper\CurlHelper;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\CurlHttpClient;

/**
 * Class HttpClientService
 *
 * @package Packlink\PacklinkPro\Services\Infrastructure
 */
class HttpClientService extends CurlHttpClient
{
    /**
     * @var CurlHelper
     */
    private $curlHelper;

    /**
     * HttpClientService constructor.
     *
     * @param CurlHelper $curlHelper
     */
    public function __construct(CurlHelper $curlHelper)
    {
        $this->curlHelper = $curlHelper;
    }

    /**
     * Create and send request asynchronously.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE etc.)
     * @param string $url Request URL. Full URL where request should be sent.
     * @param array|null $headers Request headers to send. Key as header name and value as header content. Optional.
     * @param string $body Request payload. String data to send as HTTP request payload. Optional.
     *
     * @return bool|string
     */
    protected function sendHttpRequestAsync($method, $url, $headers = [], $body = '')
    {
        $this->curlHelper->setAsync();

        return parent::sendHttpRequestAsync($method, $url, $headers, $body);
    }

    protected function setCurlSessionUrlHeadersAndBody($method, $url, array $headers, $body)
    {
        parent::setCurlSessionUrlHeadersAndBody($method, $url, $headers, $body);

        $this->curlHelper->setHttpRequestParams(
            $method,
            $this->curlOptions[CURLOPT_URL],
            $this->getFixedHeaders($headers),
            $body
        );
    }

    protected function executeCurlRequest()
    {
        return $this->curlHelper->sendHttpRequest($this->curlOptions);
    }

    private function getFixedHeaders($headers)
    {
        $newHeaders = [];

        foreach ($headers as $header) {
            // First element of this array is key and second is value for header.
            $headerArray = explode(':', $header);
            $newHeaders[$headerArray[0]] = $headerArray[1];
        }

        return $newHeaders;
    }
}
