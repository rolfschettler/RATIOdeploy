<?php

/**
 * $router->get( $path, $handler, $auth )
 * $router->post( $path, $handler, $auth )
 *
 * @param string $path     URL-Pfad der Route, z.B. '/select'
 * @param array  $handler  [ 'Vollständiger\Klassenname', 'methodenname' ]
 * @param bool   $auth     true  = Bearer-Token erforderlich (Standard)
 *                         false = kein Token erforderlich
 */



$router->get('/', ['Src\Controller\HelloController', 'index'],false);
$router->get('/info', ['Src\Controller\HelloController', 'info']);
$router->get('/modules', ['Src\Controller\HelloController', 'modules']);
$router->get('/json', ['Src\Controller\HelloController', 'json']);

$router->get('/showroute',    ['Src\Controller\GeoController', 'showroute'],    false);
$router->post('/showroute',   ['Src\Controller\GeoController', 'showroute'],    false);
$router->post('/travelroute',    ['Src\Controller\GeoController', 'travelroute'],    false);
$router->post('/calculateroute', ['Src\Controller\GeoController', 'calculateroute']);


$router->post('/calculatedistance', ['Src\Controller\GeoController', 'calculateDistance']);
$router->post('/select', ['Src\Controller\DelphiApiController', 'select']);
$router->post('/insert', ['Src\Controller\DelphiApiController', 'insert']);
$router->post('/delete', ['Src\Controller\DelphiApiController', 'delete']);
$router->post('/update', ['Src\Controller\DelphiApiController', 'update']);
$router->post('/verifytoken', ['Src\Controller\DelphiApiController', 'verifytoken']);

$router->get('/login',  ['Src\Controller\LoginController', 'form'],         false);
$router->post('/login', ['Src\Controller\LoginController', 'authenticate'], false);

$router->get('/adressen2',         ['Src\Controller\AdresseController', 'index'],  false);
$router->get('/adressen2/load',    ['Src\Controller\AdresseController', 'load']);
$router->get('/adressen2/get',        ['Src\Controller\AdresseController', 'get']);
$router->get('/adressen2/kategorien',    ['Src\Controller\AdresseController', 'kategorien']);
$router->get('/adressen2/nextkennziffer',['Src\Controller\AdresseController', 'nextkennziffer']);
$router->post('/adressen2/insert',       ['Src\Controller\AdresseController', 'insert']);
$router->post('/adressen2/delete', ['Src\Controller\AdresseController', 'delete']);
$router->post('/adressen2/update', ['Src\Controller\AdresseController', 'update']);

//Email zum Versenden eines Links
$router->post('/einladung/senden',  ['Src\Controller\EinladungController', 'senden']);
$router->post('/einladung/oeffnen', ['Src\Controller\EinladungController', 'oeffnen'], false);



//=====Endpunkte um die config.ini auszulesen ===========================
$router->get('/config',     ['Src\Controller\ConfigController', 'index'],    false);
$router->get('/config/get', ['Src\Controller\ConfigController', 'getValue'], false);