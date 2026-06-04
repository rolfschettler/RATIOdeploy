<?php

namespace Core;

use Src\Controller\DelphiApiController;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, bool $auth = true)
    {
        // $this->routes['GET'][$path] = $handler;
        $this->routes['GET'][$path] = ['handler' => $handler, 'auth' => $auth];
    }

    public function post(string $path, array $handler, bool $auth = true)
    {
        //$this->routes['POST'][$path] = $handler;
        $this->routes['POST'][$path] = ['handler' => $handler, 'auth' => $auth];
    }


    public function getBearerToken()
    {
        // Ermitteln der Tokens aus dem Auth. Header
        // -------------------------------------------
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $header = $headers['Authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } else {
            return null;
        }
        if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }




    private function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Accept-Language');
        header('Access-Control-Allow-Credentials: false');
        header('Access-Control-Max-Age: 86400');
        header('Vary: Origin');
    }

    public function dispatch(string $uri)
    {
        $this->setCorsHeaders();

        $method = $_SERVER['REQUEST_METHOD'];

        // OPTIONS-Preflight sofort beantworten
        if ($method === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        $path = parse_url($uri, PHP_URL_PATH);

        // Basisordner entfernen
        $basePath = '/php';
        if (str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        // index.php entfernen
        $path = str_replace('/index.php', '', $path);

        // leeren Pfad zu "/" machen
        if ($path === '') {
            $path = '/';
        }

        try {
            if (isset($this->routes[$method][$path])) {

                $route = $this->routes[$method][$path];
                //Wenn eine Authentifizerung bei der Route erforderlich ist:
                if ($route['auth']) {
                    $token = $this->getBearerToken();
                    if (!$token) {
                        http_response_code(401);
                        echo json_encode(['status' => 'error', 'message' => 'Kein Bearer-Token übermittelt'], JSON_UNESCAPED_UNICODE);
                        return;
                    }
                    //ÜBERPRÜFUNG DES TOKEN:
                    $DelphiApiController = new DelphiApiController();
                    $tokeninfo = $DelphiApiController->verifytoken($token);

                    //Überprüfung des Tokens negativ:
                    if ($tokeninfo['status'] !== 'OK') {
                        http_response_code(401);
                        echo  json_encode(['status' => 'error', 'message' => $tokeninfo['message']]);
                        return;
                    }
                    //Der entschlüsselte Content des Tokens wird in "$_SERVER['APP_AUTH']" gespeichert. D.h. !Überall! verfügbar.
                    $_SERVER['APP_AUTH'] = [
                        'token' =>  $token,
                        'user' => $tokeninfo['user'] ?? null,
                        'role' => $tokeninfo['role'] ?? null,

                    ];
                } else {
                    //KEINE AUTHENTIFIZIERUNG NOTWENDIG
                    $_SERVER['APP_AUTH'] = [
                        'token' =>  null,
                        'user' =>  null,
                        'role' =>  null,
                    ];
                }



                //[$class, $methodName] = $this->routes[$method][$path];
                [$class, $methodName] = $route['handler'];

                $controller = new $class();
                $result = $controller->$methodName();
                // Nur JSON-Wrapper wenn der Controller selbst keinen Output erzeugt hat
                if ($result !== null) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'OK',
                        'data' => $result
                    ]);
                }
            } else {
                http_response_code(200);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'ERROR', 'message' => 'Route ' . $path . ' nicht gefunden']);
            }
        } catch (\Throwable $e) {
            // Alle Fehler abfangen und als JSON ausgeben
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'ERROR',
                'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8')
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
