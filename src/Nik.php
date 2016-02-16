<?php

namespace Projek\Id;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception as ClientException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\DomCrawler\Crawler;
use InvalidArgumentException;
use LogicException;

class Nik
{
    const END_POINT = 'http://data.kpu.go.id/ss8.php';

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    private $client;

    /**
     * @var callable|null
     */
    private $responseHandler = null;

    /**
     * Create new Nik Instance.
     *
     * @param \GuzzleHttp\ClientInterface $client
     * @param callable|null               $handler
     */
    public function __construct(ClientInterface $client = null)
    {
        $this->client = $client ?: new CLient;
    }

    /**
     * Setup final handler.
     *
     * @param callable $handler
     */
    public function setResponseHanlder(callable $handler)
    {
        $this->responseHandler = $handler;
    }

    /**
     * Simply make this class callable.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request
     * @param  \Psr\Http\Message\ResponseInterface      $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response)
    {
        if (is_null($this->responseHandler)) {
            $this->setupDefualtHandler();
        }

        try {
            // Verify request headers
            $request = $this->assertRequestHeader($request);

            // Verify Query parameters
            $nik = $this->assertQueryParams($request);

            // Send a client request
            $apiResponse = $this->sendRequest($nik);
        } catch (InvalidArgumentException $e) {
            return $this->handleResponse([
                'message' => $e->getMessage()
            ], 406);
        } catch (GuzzleException $e) {
            return $this->handleResponse([
                'message' => $e->getMessage()
            ], $e instanceof ClientException\ServerException ? 500 : 400);
        }

        // Parse "API"-think responses
        $result = $this->parseResponse($apiResponse);

        return $this->handleResponse(
            $result ?: ['message' => 'Not found'],
            $result ? 200 : 404
        );
    }

    /**
     * Send request NIK
     *
     * @param  string $nik
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function sendRequest($nik)
    {
        // Verify NIK
        $nik = $this->assertValidateNik($nik);

        $form_params = [
            'nik_global' => $nik,
            'g-recaptcha-response' => ' ',
            'wilayah_id' => '0',
            'cmd' => 'Cari.',
        ];

        return $this->client->request('POST', self::END_POINT, compact('form_params'));
    }

    /**
     * Parse response
     *
     * @param  \Psr\Http\Message\ResponseInterface $apiResponse
     * @return array
     */
    public function parseResponse(Response $apiResponse)
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
     * Assert validate nik formats
     *
     * @param  string $nik
     * @return array
     * @throws \InvalidArgumentException If invalid 'nik' lenght or format.
     */
    protected function assertValidateNik($nik)
    {
        // Make sure NIK is 16 in lenght.
        if (strlen($nik) !== 16) {
            throw new InvalidArgumentException('Invalid NIK lenght');
        }

        // Make sure NIK is numeric.
        if (! ctype_digit($nik)) {
            throw new InvalidArgumentException('Invalid NIK format');
        }

        // More validations? let me know.

        return $nik;
    }

    /**
     * Get NIK request from query params.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request
     * @return string
     * @throws \InvalidArgumentException If has no 'nik' defined in query params.
     */
    protected function assertQueryParams(Request $request)
    {
        $params = $request->getQueryParams();

        // Make sure NIK is in request query param
        if (! isset($params['nik'])) {
            throw new InvalidArgumentException('Please specify your NIK');
        }

        return $params['nik'];
    }

    /**
     * Assert request header and we only accept application/json or ajax request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \InvalidArgumentException If Request Accept header not application/json.
     */
    protected function assertRequestHeader(Request $request)
    {
        // If it's an AJAX request, return as is
        if ($request->getHeaderLine('X-Requested-With') == 'XMLHttpRequest') {
            return $request;
        }

        // Otherwise, split the Accept header
        $accepts = explode(',', $request->getHeaderLine('Accept'));

        // Make sure it's accept application/json
        if (in_array('application/json', $accepts)) {
            return $request->withHeader('X-Requested-With', 'XMLHttpRequest');
        }

        // Otherwise, throw it
        throw new InvalidArgumentException('Invalid request');
    }

    /**
     * Normalize response output
     *
     * @param  array $result
     * @return array
     */
    protected function normalizeResult(array $result)
    {
        $normalized = [];

        foreach ($result as $label => $value) {
            $value = str_replace('&nbsp;', ' ', htmlentities($value, null, 'utf-8'));
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
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \LogicException If no response handler is set
     */
    protected function handleResponse(array $data, $status)
    {
        if (is_null($this->responseHandler)) {
            throw new LogicException("Can't handle response object with null");
        }

        return call_user_func($this->responseHandler, $data, $status);
    }

    /**
     * Setup default handler only of no handler found
     *
     * @return void
     */
    private function setupDefualtHandler()
    {
        $this->setResponseHanlder(function (array $data, $code) use ($response) {
            $body = $response->getBody();
            $body->write(json_encode($data));

            return $response->withBody($body)->withStatus($code)
                ->withHeader('Content-Type', 'application/json');
        });
    }
}
