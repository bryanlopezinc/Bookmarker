<?php

declare(strict_types=1);

namespace App\Rules;

class FolderFieldsRule extends AbstractFieldsRule
{
    /**
     * {@inheritdoc}
     */
    protected array $allowedFields = [
        'id',
        'name',
        'description',
        'has_description',
        'date_created',
        'last_updated',
        'visibility',
        'storage',
        'storage.items_count',
        'storage.capacity',
        'storage.is_full',
        'storage.available',
        'storage.percentage_used'
    ];

    /**
     * {@inheritdoc}
     */
    protected array $parentChildrenMap = [
        'storage' => [
            'storage.items_count',
            'storage.capacity',
            'storage.is_full',
            'storage.available',
            'storage.percentage_used'
        ],
    ];
}
