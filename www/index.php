<?php

require __DIR__ . '/../vendor/autoload.php';

use Zend\Diactoros\Server;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\ServerRequestFactory;

/**
 * Setup handler instance
 *
 * @var \Projek\Id\Nik
 */
$requestHandler = new Projek\Id\Nik;

/**
 * Setup response handler,
 */
$requestHandler->setResponseHanlder(function ($data, $status) {
	return new JsonResponse($data, $status);
});

/**
 * Create new server
 *
 * @var Server
 */
$server = Server::createServerfromRequest(
	$requestHandler,
	ServerRequestFactory::fromGlobals()
);

/**
 * Run the server
 */
$server->listen();
