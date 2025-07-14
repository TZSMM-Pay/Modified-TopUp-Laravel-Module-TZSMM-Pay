<?php

namespace App\Services\Gateway\tzsmmpay;

class TzsmmPay
{
    private $apiKey;
    private $apiBaseURL = 'https://tzsmmpay.com/api';

    /**
     * Initialize the TZSMM Pay client with API key.
     *
     * @param string $apiKey Your TZSMM Pay API key
     */
    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Build the full API URL for a given endpoint.
     *
     * @param string $endpoint API endpoint path
     * @return string Full API URL
     */
    private function buildURL($endpoint)
    {
        $endpoint = ltrim($endpoint, '/');
        return $this->apiBaseURL . '/' . $endpoint;
    }

    /**
     * Initiate a payment with TZSMM Pay API.
     *
     * @param array $requestData Payment data (e.g., api_key, amount, cus_name, cus_email, cus_number, success_url, cancel_url, callback_url, extra)
     * @param string $apiType Endpoint for payment initiation (default: 'payment/create')
     * @return string Payment URL for redirection
     * @throws \Exception If the payment creation fails
     */
    public function initPayment($requestData, $apiType = 'payment/create')
    {
        $apiUrl = $this->buildURL($apiType);
        $requestData['api_key'] = $this->apiKey;
        $response = $this->sendRequest('POST', $apiUrl, $requestData);

        $this->validateApiResponse($response, 'Payment creation failed');
        return $response['payment_url'];
    }

    /**
     * Verify a payment using the transaction ID.
     *
     * @param string $transactionId Transaction ID to verify
     * @return array Verification response data
     * @throws \Exception If the verification request fails
     */
    public function verifyPayment($transactionId)
    {
        $verifyUrl = $this->buildURL('payment/verify');
        $requestData = [
            'api_key' => $this->apiKey,
            'trx_id' => $transactionId,
        ];
        return $this->sendRequest('POST', $verifyUrl, $requestData);
    }

    /**
     * Handle webhook/callback for payment execution.
     *
     * @param array $data Webhook payload
     * @return array Verification response for the payment
     * @throws \Exception If the webhook validation or verification fails
     */
    public function executePayment($data)
    {
        $this->validateIpnResponse($data);

        $transactionId = $data['trx_id'] ?? null;
        if (!$transactionId) {
            throw new \Exception('Missing trx_id in webhook payload.');
        }

        $response = $this->verifyPayment($transactionId);

        if (!isset($response['status']) || $response['status'] !== 'Completed') {
            throw new \Exception('Payment verification failed or status is not Completed: ' . ($response['status'] ?? 'Unknown'));
        }

        return $response;
    }

    /**
     * Send an HTTP request to the TZSMM Pay API.
     *
     * @param string $method HTTP method (e.g., POST)
     * @param string $url API endpoint URL
  * @param array $data Request payload
     * @return array Decoded JSON response
     * @throws \Exception If the request fails or response is invalid
     */
    private function sendRequest($method, $url, $data)
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $curl = curl_init();

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST' && !empty($data)) {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response from TZSMM Pay API.");
        }

        if ($httpCode >= 400 || !isset($decodedResponse['success']) || !$decodedResponse['success']) {
            $message = $decodedResponse['messages'] ?? ($decodedResponse['message'] ?? 'Unknown error from TZSMM Pay API.');
            throw new \Exception($message);
        }

        return $decodedResponse;
    }

    /**
     * Validate the API response for payment initiation.
     *
     * @param array $response API response data
     * @param string $errorMessage Default error message if validation fails
     * @throws \Exception If the response is invalid
     */
    private function validateApiResponse($response, $errorMessage)
    {
        if (!isset($response['success']) || !$response['success'] || !isset($response['payment_url'])) {
            $message = $response['messages'] ?? ($response['message'] ?? $errorMessage);
            throw new \Exception($message);
        }
    }

    /**
     * Validate the webhook/callback response.
     *
     * @param array $data Webhook payload
     * @throws \Exception If the input is empty or invalid
     */
    private function validateIpnResponse($data)
    {
        if (empty($data)) {
            throw new \Exception("Invalid or empty response from TZSMM Pay API.");
        }

        $requiredFields = ['trx_id', 'status', 'amount', 'cus_name', 'cus_email', 'cus_number'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field '$field' in TZSMM Pay webhook response.");
            }
        }
    }
}
