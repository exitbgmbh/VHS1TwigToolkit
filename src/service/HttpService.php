<?php

class HttpService
{
    /**
     * @param string $url
     * @return string
     * @throws Exception
     */
    public function get(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $context = curl_exec($ch);

        $success = true;
        $errorMessage = '';
        if (false === $context) {
            $success = false;
            $errorMessage = curl_error($ch);
        }

        curl_close($ch);

        if (!$success) {
            throw new Exception(sprintf('request failed: "%s"', $errorMessage));
        }

        return $context;
    }
}
