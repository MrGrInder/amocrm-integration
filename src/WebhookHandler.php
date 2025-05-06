<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class WebhookHandler {
    public function __construct(
        private AmoCRMApiClient $apiClient,
        private NoteGenerator $noteGenerator,
        private LoggerInterface $logger
    ) {}

    public function process(array $data): void
    {
        $this->validateData($data);

        $entityType = $this->normalizeEntityType($data['entity_type']);
        $entityId = (int)$data['entity_id'];

        $noteText = match($data['event_type']) {
            'add' => $this->handleCreation($entityType, $entityId, $data),
            'update' => $this->handleUpdate($entityType, $entityId, $data),
            default => throw new \InvalidArgumentException('Unsupported event type')
        };

        $this->apiClient->createEntityNote($entityType, $entityId, $noteText);
    }

    private function handleCreation(string $entityType, int $entityId, array $data): string
    {
        $entity = $this->apiClient->getEntityDetails($entityType, $entityId);
        return $this->noteGenerator->generateForCreation(
            $entity['name'],
            $entity['responsible_user']['name'],
            new DateTimeImmutable($data['created_at'])
        );
    }

    private function validateData(array $data): void
    {
        $requiredFields = ['entity_type', 'entity_id', 'event_type'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $this->logger->error('Missing required field', ['field' => $field]);
                throw new \InvalidArgumentException("Missing required field: $field");
            }
        }
    }
}
