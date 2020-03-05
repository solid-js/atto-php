<?php

// Declare application root path and load Application bootstrap
define('APP_ROOT', __DIR__.'/app/');
require_once('./app/core/App.php');

// Get requested path from web server
$path = ( isset($_GET['path']) ? $_GET['path'] : '' );

// Start Application bootstrap
$app = App::instance();
$app->start( $path );