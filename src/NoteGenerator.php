<?php
declare(strict_types=1);

namespace App;

use DateTimeInterface;

class NoteGenerator {

    public function generateForCreation(string $entityName, string $responsibleUser, DateTimeInterface $createdAt): string
    {
        return sprintf(
            "Создан объект\nНазвание: %s\nОтветственный: %s\nВремя создания: %s",
            $entityName,
            $responsibleUser,
            $createdAt->format('Y-m-d H:i:s')
        );
    }

    public function generateForUpdate(array $changedFields, DateTimeInterface $updatedAt): string
    {
        if (empty($changedFields)) {
            throw new \InvalidArgumentException('Changed fields cannot be empty');
        }

        $changes = array_map(
            fn($field) => sprintf("%s: %s", $field['name'], $field['new_value']),
            $changedFields
        );

        return sprintf(
            "Обновление\nИзмененные поля:\n%s\nВремя изменения: %s",
            implode("\n", $changes),
            $updatedAt->format('Y-m-d H:i:s')
        );
    }
}
