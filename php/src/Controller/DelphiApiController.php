<?php

namespace Src\Controller;

use Exception;

class DelphiApiController
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'http://localhost:' . ($_SERVER['SERVER_PORT'] ?? '80') . '/ibapi';
    }

    public function verifytoken(string $token): mixed
    {
        return $this->request('/verifytoken', '', $token);
    }






    //---------------------------------------SQL-API-------------------------------------------------------------------------------

    public function select(string $sql = ''): mixed
    {
        if (!$sql) {
            $sql = file_get_contents("php://input");
        }

        if (!json_decode($sql, true)) {
            throw new Exception("php-select: Leer oder ungültiges JSON-Format");
        }

        return $this->request('/select', $sql);
    }

    public function insert(string $table = '', string $content = ''): mixed
    {
        if (!$table)   $table   = $_GET['table'] ?? throw new Exception("php-insert: Parameter 'table' fehlt");
        if (!$content) $content = file_get_contents("php://input");

        if (!json_decode($content, true)) {
            throw new Exception("php-insert: Leer oder ungültiges JSON-Format");
        }

        return $this->request("/insert?table={$table}", $content);
    }

    public function delete(string $table = '', string $key = '', string $content = ''): mixed
    {
        if (!$table)   $table   = $_GET['table'] ?? throw new Exception("php-delete: Parameter 'table' fehlt");
        if (!$key)     $key     = $_GET['key']   ?? throw new Exception("php-delete: Parameter 'key' fehlt");
        if (!$content) $content = file_get_contents("php://input");

        if (!json_decode($content, true)) {
            throw new Exception("php-delete: Leer oder ungültiges JSON-Format");
        }

        return $this->request("/delete?table={$table}&key={$key}", $content);
    }

    public function update(string $table = '', string $key = '', string $content = ''): mixed
    {
        if (!$table)   $table   = $_GET['table'] ?? throw new Exception("php-update: Parameter 'table' fehlt");
        if (!$key)     $key     = $_GET['key']   ?? throw new Exception("php-update: Parameter 'key' fehlt");
        if (!$content) $content = file_get_contents("php://input");

        if (!json_decode($content, true)) {
            throw new Exception("php-update: Leer oder ungültiges JSON-Format");
        }

        return $this->request("/update?table={$table}&key={$key}", $content);
    }



    public function execute(string $sql = ''): mixed
    {
        if (!$sql) {
            $sql = file_get_contents("php://input");
        }

        if (!json_decode($sql, true)) {
            throw new Exception("php-execute: Leer oder ungültiges JSON-Format");
        }

        return $this->request('/execute', $sql);
    }



    public function checkMailToken(string $tokenHash, ?string $clientIp): mixed
    {
        $payload = json_encode([
            'token_hash' => $tokenHash,
            'client_ip'  => $clientIp ?? '',
        ]);

        return $this->request('/public/checkmailtoken', $payload, '');
    }



    //---------------------------------------cURL-Core-------------------------------------------------------------------------------

    protected function request(string $endpoint, string $content, string $token = ''): mixed
    {
        if (!$token) {
            $token = $_SERVER['APP_AUTH']['token'];
        }

        $ch = curl_init($this->baseUrl . $endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);



        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch) ? curl_error($ch) : null;
   

        if ($curlError) {

            throw new Exception($curlError);
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $data;
        }

        throw new Exception($data['message'] ?? "HTTP-Fehler $httpCode");
    }
}
