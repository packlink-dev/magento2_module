<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Services\Infrastructure;

use Packlink\PacklinkPro\Helper\CurlHelper;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\HttpClient;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\HttpResponse;

/**
 * Class HttpClientService
 *
 * @package Packlink\PacklinkPro\Services\Infrastructure
 */
class HttpClientService extends HttpClient
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
     * Create and send request.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE etc.)
     * @param string $url Request URL. Full URL where request should be sent.
     * @param array|null $headers Request headers to send. Key as header name and value as header content. Optional.
     * @param string $body Request payload. String data to send as HTTP request payload. Optional.
     *
     * @return HttpResponse Response object.
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\Exceptions\HttpCommunicationException
     *      Only in situation when there is no connection or no response.
     */
    protected function sendHttpRequest($method, $url, $headers = [], $body = '')
    {
        return $this->curlHelper->sendHttpRequest($method, $url, $this->getFixedHeaders($headers), $body);
    }

    /**
     * Create and send request asynchronously.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE etc.)
     * @param string $url Request URL. Full URL where request should be sent.
     * @param array|null $headers Request headers to send. Key as header name and value as header content. Optional.
     * @param string $body Request payload. String data to send as HTTP request payload. Optional.
     */
    protected function sendHttpRequestAsync($method, $url, $headers = [], $body = '')
    {
        $this->curlHelper->sendHttpRequestAsync($method, $url, $this->getFixedHeaders($headers), $body);
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
