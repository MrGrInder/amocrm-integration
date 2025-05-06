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

        $noteText = $this->generateNoteText($data, $entityType, $entityId);
        $this->apiClient->createEntityNote($entityType, $entityId, $noteText);
    }

    private function generateNoteText(array $data, string $entityType, int $entityId): string
    {
        return match($data['event_type']) {
            'add' => $this->handleCreation($entityType, $entityId, $data),
            'update' => $this->handleUpdate($data),
            default => throw new \InvalidArgumentException('Unsupported event type')
        };
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

    private function handleUpdate(array $data): string
    {
        return $this->noteGenerator->generateForUpdate(
            $data['changed_fields'] ?? [],
            new DateTimeImmutable($data['updated_at'] ?? 'now')
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

    private function normalizeEntityType(string $type): string
    {
        $allowed = ['leads', 'contacts'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid entity type');
        }
        return $type;
    }
}
