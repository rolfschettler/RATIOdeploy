<?php

// Composer-Autoloader fuer externe Pakete (PHPMailer etc.)
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/core/Autoloader.php';

$router = new Core\Router();

require 'routes.php';

$router->dispatch($_SERVER['REQUEST_URI']);