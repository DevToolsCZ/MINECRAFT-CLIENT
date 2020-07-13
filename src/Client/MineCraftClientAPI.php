<?php

declare(strict_types=1);

namespace App\Client;

use App\Client\Exception\ServerOfflineException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Nette\Http\IResponse;

class MineCraftClientAPI
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    protected function injectGuzzle(Client $client): void
    {
        $this->client = $client;
    }

    public function __construct(string $ip = '127.0.0.1', int $port = 20059, string $username, string $password) 
    {
        $this->client   = new Client([
            'base_uri' => "http://$ip:$port/api/2/",
            'timeout'  => 2,
        ]);
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Create key for API call
     *
     * @param string $method
     *
     * @return string
     */
    private function getKey(string $method)
    {
        return hash('sha256', $this->username . $method . $this->password);
    }

    /**
     * Make the API call
     *
     * @param string $commandName
     * @param array  $commandArguments
     *
     * @return mixed
     * @throws GuzzleException
     * @throws ServerOfflineException
     */
    public function call(string $commandName, array $commandArguments = [])
    {
        try {
            $response = $this->client->request('GET', 'call', [
                'query' => [
                    'json' => json_encode([
                        'name'      => $commandName,
                        'arguments' => $commandArguments,
                        'key'       => $this->getKey($commandName),
                        'username'  => $this->username
                    ])
                ],
            ]);
        } catch (ConnectException $e) {
            throw new ServerOfflineException('Server is offline, please enable him.', IResponse::S503_SERVICE_UNAVAILABLE);
        }

        if ($response->getStatusCode() >= 400) {
            throw new \Exception('Call failed to minecraft server', $response->getStatusCode());
        }

        $body = (string) $response->getBody();
        if (!($bodyDecoded = json_decode($body, true))) {
            throw new \Exception('Cannot decode response of minecraft server', 500);
        }

        return $bodyDecoded[0]['success'];
    }

}
