<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

/** @noinspection CurlSslServerSpoofingInspection */

namespace Packlink\PacklinkPro\Helper;

use Magento\Framework\HTTP\Client\Curl;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Http\HttpResponse;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;

/**
 * Class CurlHelper
 *
 * @package Packlink\PacklinkPro\Helper
 */
class CurlHelper extends Curl
{
    /**
     * HTTP status code for continue.
     */
    const RESPONSE_STATUS_CONTINUE = 100;

    /**
     * Creates and sends request.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string $body
     *
     * @return HttpResponse
     * @throws HttpCommunicationException
     */
    public function sendHttpRequest($method, $url, array $headers = [], $body = '')
    {
        $this->removeCurlOptions();
        $this->setHeaders($headers);

        $curlOptions = [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        if ($method === 'POST') {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        try {
            $this->setOptions($curlOptions);
            $this->makeRequest($method, $url);
        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), 'Integration');
            throw new HttpCommunicationException('Request ' . $url . ' failed.', 0, $e);
        }

        return new HttpResponse($this->getStatus(), $this->getHeaders(), $this->getBody());
    }

    /**
     * Creates and sends request asynchronously.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string $body
     *
     * @return bool
     */
    public function sendHttpRequestAsync($method, $url, array $headers = [], $body = '')
    {
        $this->_ch = curl_init();

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT_MS => 1000,
        ];

        if ($method === 'DELETE' || $method === 'PUT') {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if ($method === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        $this->curlOptions($curlOptions);

        return curl_exec($this->_ch);
    }

    /**
     * Parse headers - CURL callback function
     *
     * @param resource $ch curl handle, not needed
     * @param string $data
     *
     * @return int
     * @throws \Exception
     */
    protected function parseHeaders($ch, $data)
    {
        $this->resetHeaderCountIfResponseStatusIsContinue();

        return parent::parseHeaders($ch, $data);
    }

    private function resetHeaderCountIfResponseStatusIsContinue()
    {
        if ($this->_responseStatus === self::RESPONSE_STATUS_CONTINUE && $this->_headerCount === 2) {
            $this->_headerCount = 0;
        }
    }

    /**
     * Removes all user defined curl options.
     */
    private function removeCurlOptions()
    {
        $this->_curlUserOptions = [];
    }
}
