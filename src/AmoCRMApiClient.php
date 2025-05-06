<?php
declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class AmoCRMApiClient {
    private Client $client;
    private string $tokensPath;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private LoggerInterface $logger
    ) {
        $this->client = new Client([
            'base_uri' => 'https://www.amocrm.ru/',
            'timeout' => 10,
            'allow_redirects' => false
        ]);

        $this->tokensPath = __DIR__ . '/../../var/tokens.json';
    }

    public function getAuthorizationUrl(string $state): string
    {
        return $this->client->getConfig('base_uri') . 'oauth?' . http_build_query([
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'state' => $state,
                'mode' => 'post_message'
            ]);
    }

    public function handleAuthorizationCode(string $code): void
    {
        try {
            $response = $this->client->post('/oauth2/access_token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri
                ]
            ]);

            $this->storeTokens(json_decode($response->getBody()->getContents(), true));

        } catch (GuzzleException $exception) {
            $this->logger->error('Authorization failed', ['error' => $exception->getMessage()]);
            throw new \RuntimeException('Authorization error');
        }
    }

    private function refreshTokens(): void
    {
        $tokens = $this->getStoredTokens();

        try {
            $response = $this->client->post('/oauth2/access_token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $tokens['refresh_token'],
                    'redirect_uri' => $this->redirectUri
                ]
            ]);

            $this->storeTokens(json_decode($response->getBody()->getContents(), true));

        } catch (GuzzleException $exception) {
            $this->logger->critical('Token refresh failed', ['error' => $exception->getMessage()]);
            throw new \RuntimeException('Token refresh error');
        }
    }

    private function storeTokens(array $tokens): void
    {
        file_put_contents(
            $this->tokensPath,
            json_encode([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => time() + $tokens['expires_in'] - 300
            ], JSON_PRETTY_PRINT)
        );
    }

    public function getEntityDetails(string $entityType, int $entityId): array
    {
        $this->refreshTokensIfNeeded();

        try {
            $response = $this->client->get("/api/v4/{$entityType}s/{$entityId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getStoredTokens()['access_token']
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (GuzzleException $exception) {
            $this->logger->error('API request failed', ['error' => $exception->getMessage()]);
            throw new \RuntimeException('Entity fetch error');
        }
    }
}
