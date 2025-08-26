<?php

// Load Composer autoloader first
require_once 'vendor/autoload.php';

// Load environment variables from .env file using Laravel's standard approach
if (file_exists('.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Set testing database connection for PHPUnit
$_ENV['DB_CONNECTION'] = $_ENV['DB_TESTING_CONNECTION'];
$_SERVER['DB_CONNECTION'] = $_ENV['DB_TESTING_CONNECTION'];
putenv('DB_CONNECTION=' . $_ENV['DB_TESTING_CONNECTION']);
