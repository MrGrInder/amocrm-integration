<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\AmoCRMApiClient;
use App\NoteGenerator;
use App\WebhookHandler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

try {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();

    $logger = new Logger('webhook');
    $logger->pushHandler(new StreamHandler(dirname(__DIR__) . '/var/logs/webhook.log'));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \RuntimeException('Invalid request method');
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $apiClient = new AmoCRMApiClient(
        $_ENV['CLIENT_ID'],
        $_ENV['CLIENT_SECRET'],
        $_ENV['REDIRECT_URI'],
        $logger
    );

    $handler = new WebhookHandler(
        $apiClient,
        new NoteGenerator(),
        $logger
    );

    $handler->process($data);

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (\Throwable $exception) {
    $logger->error($exception->getMessage(), ['trace' => $exception->getTrace()]);
    http_response_code(400);
    echo json_encode([
        'error' => 'Webhook processing failed',
        'message' => $exception->getMessage()
    ]);
}