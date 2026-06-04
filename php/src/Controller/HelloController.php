<?php

namespace Src\Controller;




class HelloController
{
    public function index()
    {
        header('Content-Type: text/html');
        echo "<ul>";
        echo '<li>  <a href=./info>PHP Info</a> </li>';
        echo '<li>  <a href=./modules>PHP Erweiterungen</a> </li>';
        echo '<li>  <a href=./json>PHP Demo Json</a> </li>';
        echo "</ul>";
    }

    public function info()
    {
        header('Content-Type: text/html');
        phpinfo();
        die();
    }


    public function modules()
    {

        header('Content-Type: text/html');
        //phpinfo(INFO_MODULES);
        echo "<h2>geladene Erweiterungen:</h2>";
        print_r(get_loaded_extensions());
        die();
    }

    public function json()
    {
        return  [['zweck' => 'Demo', 'format' => 'JSON'], ['author' => 'Rolf Schettler', 'modulname' => 'RATIOserver']];
    }
}
