<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\AmoCRMApiClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

session_start();

try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();

    $logger = new Logger('auth');
    $logger->pushHandler(new StreamHandler(dirname(__DIR__) . '/var/logs/auth.log'));

    $apiClient = new AmoCRMApiClient(
        $_ENV['CLIENT_ID'],
        $_ENV['CLIENT_SECRET'],
        $_ENV['REDIRECT_URI'],
        $logger
    );

    if (!isset($_GET['code'])) {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        header('Location: ' . $apiClient->getAuthorizationUrl($state));
        exit;
    }

    if ($_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
        throw new \RuntimeException('Invalid state parameter');
    }

    $apiClient->handleAuthorizationCode($_GET['code']);
    echo "Success! Tokens have been saved.";

} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Authentication failed',
        'message' => $exception->getMessage()
    ]);
}
