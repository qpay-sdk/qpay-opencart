<?php

class QPay {
    private string $base_url;
    private string $username;
    private string $password;
    private ?string $token = null;
    private int $token_expiry = 0;

    public function __construct(string $base_url, string $username, string $password) {
        $this->base_url = rtrim($base_url, '/');
        $this->username = $username;
        $this->password = $password;
    }

    private function getToken(): string {
        if ($this->token && time() < $this->token_expiry) return $this->token;

        $ch = curl_init($this->base_url . '/v2/auth/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $this->token = $data['access_token'] ?? '';
        $this->token_expiry = time() + ($data['expires_in'] ?? 3600) - 30;
        return $this->token;
    }

    private function request(string $method, string $endpoint, array $body = []): ?array {
        $token = $this->getToken();
        $ch = curl_init($this->base_url . $endpoint);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function createInvoice(array $data): ?array {
        return $this->request('POST', '/v2/invoice', $data);
    }

    public function checkPayment(string $invoiceId): ?array {
        return $this->request('POST', '/v2/payment/check', [
            'object_type' => 'INVOICE',
            'object_id' => $invoiceId,
        ]);
    }
}
