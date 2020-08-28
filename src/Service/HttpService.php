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
     * @return string[]
     */
    public function getPdfTypes(): array
    {
        return [
            'invoice' => 'Rechnung',
            'delivery' => 'Lieferschein',
            'return' => 'Retourenschein',
            'offer' => 'Angebot',
            'orderConfirmation' => 'Auftragsbestätigung',
            'pickBox' => 'Pick-Box Label',
            'picklist' => 'Pick-Liste',
            'posReport' => 'Kassenabschluss',
            'productLabel' => 'Produkt Label',
            'stockInventory' => 'Inventurbericht',
            'stockRelocation' => 'Lagernachfüllauftrag',
            'supplierOrder' => 'Lieferantenbestellung',
            'supplyNote' => 'Lieferantenbegleitdokument',
            'trayLabel' => 'Lager-Fach Label',
            'userCard' => 'Benutzer Login-Card',
        ];
    }

    /**
     * @return string[]
     */
    public function getEmailTypes(): array
    {
        return [
            'order-income' => 'Bestellbestätigung',
            'return-processed' => 'Retoure verarbeitet',
            'return-received' => 'Retoure erhalten',
            'order-shipped' => 'Bestellung verschickt',
        ];
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
