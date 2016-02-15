<?php

namespace Projek\Id;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use InvalidArgumentException;
use LogicException;

class Nik
{
	const END_POINT = 'http://data.kpu.go.id/ss8.php';

	/**
	 * @var ClientInterface
	 */
	private $client;

	/**
	 * @var callable
	 */
	private $responseHandler;

	/**
	 * Create new Nik Instance
	 *
	 * @param ClientInterface $client
	 * @param callable|null   $handler
	 */
	public function __construct(ClientInterface $client = null, callable $handler = null)
	{
        if (is_null($client)) {
            $client = new CLient;
        }

		$this->client = $client;

		$this->setResponseHanlder($handler);
	}

	/**
	 * Setup response handler
	 *
	 * @param callable $handler
	 */
	public function setResponseHanlder(callable $handler)
	{
		$this->responseHandler = $handler;
	}

	/**
	 * Callable
	 *
	 * @param  ServerRequestInterface $request
	 * @param  ResponseInterface      $response
	 * @return ResponseInterface
	 */
	public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
	{
		return $this->handle($request, $response);
	}

	/**
	 * Request Handler
	 *
	 * @param  ServerRequestInterface $request
	 * @param  ResponseInterface      $response
     * @return ResponseInterface
	 * @throws LogicException If response handler is null
	 */
	public function handle(ServerRequestInterface $request, ResponseInterface $response)
	{
		try {
            // Verify request headers
            $request = $this->assertRequestHeader($request);
            // Verify Query parameters
            $params = $this->assertQueryParams($request);
        } catch (InvalidArgumentException $e) {
            return $this->handleResponse([
                'message' => $e->getMessage()
            ], 400);
        }

        try {
            // Send a client request
            $apiResponse = $this->sendClientRequest($params['nik']);
        } catch (GuzzleException $e) {
            return $this->handleResponse([
                'message' => $e->getMessage()
            ], 500);
        }

        // Parse "API"-think responses
		$result = $this->getParsedResponse($apiResponse);

		return $this->handleResponse(
			$result ?: ['message' => 'Not found'],
			$result ? 200 : 404
		);
	}

	/**
	 * Assert request header
	 *
	 * @param  ServerRequestInterface $request
	 * @return ServerRequestInterface
	 * @throws InvalidArgumentException If Request Accept header not application/json
	 */
	protected function assertRequestHeader(ServerRequestInterface $request)
	{
		$accepts = explode(',', $request->getHeaderLine('Accept'));

		if (! in_array('application/json', $accepts)) {
			throw new InvalidArgumentException('Invalid request');
		}

		return $request->withHeader('X-Requested-With', 'XMLHttpRequest');
	}

	/**
	 * Assert query params
	 *
	 * @param  ServerRequestInterface $request
	 * @return array
	 * @throws InvalidArgumentException If has no 'nik' defined in query params or Invalid 'nik' lenght
	 */
	protected function assertQueryParams(ServerRequestInterface $request)
	{
		$params = $request->getQueryParams();

		if (! isset($params['nik'])) {
			throw new InvalidArgumentException('Please specify your NIK');
		}

		if (strlen($params['nik']) !== 16) {
			throw new InvalidArgumentException('Invalid NIK');
		}

		return $params;
	}

	/**
	 * Send request NIK
	 *
     * @param  string $nik
	 * @return ResponseInterface
	 * @throws GuzzleException
	 */
	protected function sendClientRequest($nik)
	{
		$form_params = [
			'nik_global' => $nik,
			'g-recaptcha-response' => ' ',
			'wilayah_id' => '0',
			'cmd' => 'Cari.',
		];

		return $this->client->request('POST', self::END_POINT, compact('form_params'));
	}

	/**
	 * Parse client response
	 *
	 * @param  ResponseInterface $apiResponse
	 * @return array
	 */
	protected function getParsedResponse(ResponseInterface $apiResponse)
	{
		$parser = new Crawler((string) $apiResponse->getBody());
		$result = $parser->filter('div.form')->each(function ($el, $i) {
			$label = $el->filter('.label')->text();
			$value = $el->filter('.field')->text();

			if (htmlentities(trim($label), null, 'utf-8') !== '&nbsp;') {
				return [strtolower($label) => $value];
			}

			return [];
		});

		if ($result) {
			$result = call_user_func_array('array_merge', $result);

			return $this->normalizeResult($result);
		}

		return [];
	}

	/**
	 * Normalize response output
	 *
	 * @param  array  $result
	 * @return array
	 */
	protected function normalizeResult(array $result)
	{
		$normalized = [];

		foreach ($result as $label => $value) {
			$label = str_replace([' ', '/'], '_', trim($label));
			$label = preg_replace("/\s|:/", '', $label);

			$normalized[$label] = trim($value);
		}

		return $normalized;
	}

	/**
	 * Handle response data
	 *
	 * @param  array $data
	 * @param  int   $status
	 * @return ResponseInterface
	 */
	protected function handleResponse(array $data, $status)
	{
		if (is_null($this->responseHandler)) {
			throw new LogicException("Can't handle response object with null");
		}

		return call_user_func($this->responseHandler, $data, $status);
	}
}
