<?php

namespace App\Service;

use Exception;

class HttpService
{
    /**
     * @param string $url
     * @param array $postFields
     * @return bool|string
     * @throws Exception
     */
    public function authenticate(string $url, array $postFields)
    {
        return $this->post($url, $postFields);
    }

    /**
     * @param string $url
     * @param string $jwt
     * @return string
     * @throws Exception
     */
    public function getContext(string $url, string $jwt): string
    {
        return $this->get($url, $jwt);
    }

    /**
     * @param string $url
     * @param string $jwt
     * @return string
     * @throws Exception
     */
    public function getEmailContext(string $url, string $jwt): string
    {
        return $this->get($url, $jwt);
    }

    /**
     * @param string $url
     * @param string $jwt
     * @return string
     * @throws Exception
     */
    public function getTemplateTextModules(string $url, string $jwt): string
    {
        return $this->get($url, $jwt);
    }

    /**
     * @param string $url
     * @param string $jwt
     * @return string
     * @throws Exception
     */
    public function getTypes(string $url, string $jwt): string
    {
        return $this->get($url, $jwt);
    }

    /**
     * @param string $url
     * @param string $jwt
     * @return string
     * @throws Exception
     */
    public function getLanguages(string $url, string $jwt): string
    {
        return $this->get($url, $jwt);
    }

    /**
     * @param string $url
     * @param string $jwt
     * @return string
     * @throws Exception
     */
    public function get(string $url, string $jwt = ''): string
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
        ];

        if (!empty($jwt)) {
            $options[CURLOPT_HTTPHEADER] = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $jwt,
            ];
        }

        return $this->_request($url, $options);
    }

    /**
     * @param string $url
     * @param array $postFields
     * @return bool|mixed|string
     * @throws Exception
     */
    public function post(string $url, array $postFields)
    {
        $options = [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $postFields,
        ];

        return $this->_request($url, $options);
    }

    /**
     * @param string $url
     * @param array $options
     * @return mixed|bool|string
     * @throws Exception
     */
    private function _request(string $url, array $options)
    {
        if ((int)strpos($url, '?') > 0) {
            $url .= '&XDEBUG_SESSION_START=PHPSTORM';
        } else {
            $url .= '?XDEBUG_SESSION_START=PHPSTORM';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        $success = true;
        $errorMessage = '';
        if (false === $response) {
            $success = false;
            $errorMessage = curl_error($ch);
        }

        if (!$success) {
            throw new Exception(sprintf('request failed: "%s"', $errorMessage));
        }

        curl_close($ch);

        return $response;
    }
}
