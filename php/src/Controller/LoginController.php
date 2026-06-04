<?php

namespace Src\Controller;

use Exception;

class LoginController
{

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'http://localhost:' . ($_SERVER['SERVER_PORT'] ?? '80') . '/ibapi';
    }


    public function form(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        include __DIR__ . '/../Views/login.php';
    }

    public function authenticate(): mixed
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (empty($data['user'])) {
            throw new Exception('Benutzername erforderlich');
        }

        $payload = json_encode([
            'user'     => $data['user'] ?? '',
            'password' => $data['password'] ?? ''
        ]);

        $ch = curl_init($this->baseUrl . '/login');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'Accept: application/json'
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;

        if ($curlError) {
            throw new Exception($curlError);
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $result;
        }

        throw new Exception($result['message'] ?? 'Benutzername oder Passwort falsch');
    }
}
