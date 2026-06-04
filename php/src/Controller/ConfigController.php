<?php

namespace Src\Controller;

use Core\Config;

class ConfigController
{
    /**
     * Erlaubte Section/Key-Kombinationen.
     * true  = Wert wird im Klartext zurückgegeben
     * false = Wert wird als "***" maskiert
     */
    private const WHITELIST = [
        'APACHE' => [
            'port'      => true,
            'sslport'   => true,
            'rootdir'   => true,
        ],
        'DB' => [
            'database'  => true,
            'username'  => true,
            'password'  => false,   // sensitiv
            'port'      => true,
            'server'    => true,
        ],
        'security' => [
            'jwt_secret'     => false,  // sensitiv
            'issuer'         => true,
            'minutes_valid'  => true,
        ],
        'KI' => [
            'token'     => false,   // sensitiv
        ],
        'MAIL' => [
            'driver'          => true,
            'file_output_dir' => true,
            'from_address'    => true,
            'from_name'       => true,
            'smtp_host'       => true,
            'smtp_port'       => true,
            'smtp_secure'     => true,
            'smtp_user'       => true,
            'smtp_pass'       => false, // sensitiv
        ],
    ];

    /**
     * GET /config-test
     * Zeigt die geladenen Konfigurationswerte (sensible Felder werden ausgeblendet).
     */
    public function index(): array
    {
        return [
            'mail_driver'      => Config::get('MAIL', 'driver', 'NICHT GESETZT'),
            'mail_output_dir'  => Config::get('MAIL', 'file_output_dir', 'NICHT GESETZT'),
            'mail_from'        => Config::get('MAIL', 'from_address', 'NICHT GESETZT'),
            'db_server'        => Config::get('DB', 'server', 'NICHT GESETZT'),
            'db_database'      => Config::get('DB', 'database', 'NICHT GESETZT'),
            'jwt_issuer'       => Config::get('security', 'issuer', 'NICHT GESETZT'),
            'jwt_secret_set'   => Config::has('security', 'jwt_secret') ? 'JA' : 'NEIN',
            'rootdir'   => Config::get('APACHE', 'rootdir'),
        ];
    }

    /**
     * GET /config/get?section=SECTION&key=KEY
     * Liest einen einzelnen Schlüssel aus einer Section der config.ini.
     */
    public function getValue(): array
    {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $section = trim($_GET['section'] ?? $body['section'] ?? '');
        $key     = trim($_GET['key']     ?? $body['key']     ?? '');

        if ($section === '' || $key === '') {
            http_response_code(400);
            return ['error' => 'Parameter "section" und "key" sind erforderlich.'];
        }

        if (!isset(self::WHITELIST[$section][$key])) {
            http_response_code(403);
            return ['error' => "Schlüssel [{$section}] {$key} ist nicht freigegeben."];
        }

        if (!Config::has($section, $key)) {
            http_response_code(404);
            return ['error' => "Schlüssel [{$section}] {$key} nicht gefunden."];
        }

        $plain = self::WHITELIST[$section][$key];

        return [
            'section' => $section,
            'key'     => $key,
            'value'   => $plain ? Config::get($section, $key) : '***',
        ];
    }
}
