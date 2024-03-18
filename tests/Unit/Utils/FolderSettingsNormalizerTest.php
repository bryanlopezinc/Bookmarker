<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\FolderSettingsNormalizer;
use App\ValueObjects\FolderSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FolderSettingsNormalizerTest extends TestCase
{
    #[Test]
    public function fromRequest(): void
    {
        $normalizer = new FolderSettingsNormalizer();

        $settings = [
            'max_collaborators_limit'   => '3',
            'max_bookmarks_limit'       => '100',
            'accept_invite_constraints' => ['InviterMustBeAnActiveCollaborator'],
            'notifications'              => [
                'enabled'           => true,
                'folder_updated'    => ['enabled' => '0'],
                'new_bookmarks'     => ['enabled' => 0],
                'bookmarks_removed' => ['enabled' => true],
                'new_collaborator'  => [
                    'enabled' => '1',
                    'mode'    => 'invitedByMe'
                ],
                'collaborator_exit' => [
                    'enabled' => 1,
                    'mode'    => 'hasWritePermission'
                ],
            ]
        ];

        $this->assertEquals($normalizer->fromRequest($settings), [
            'max_collaborators_limit'   => 3,
            'max_bookmarks_limit'       => 100,
            'accept_invite_constraints' => ['InviterMustBeAnActiveCollaborator'],
            'notifications' => [
                'enabled'           => true,
                'folder_updated'    => ['enabled' => false],
                'new_bookmarks'     => ['enabled' => false],
                'bookmarks_removed' => ['enabled' => true],
                'new_collaborator'  => [
                    'enabled' => true,
                    'mode'    => 'invitedByMe'
                ],
                'collaborator_exit' => [
                    'enabled' => true,
                    'mode'    => 'hasWritePermission'
                ],
            ]
        ]);
    }

    #[Test]
    public function willConvertOnlySetKeys(): void
    {
        $normalizer = new FolderSettingsNormalizer();

        $settings = [
            'max_collaborators_limit' => 3,
            'notifications'            => [
                'enabled' => true,
                'bookmarks_removed' => ['enabled' => true]
            ]
        ];

        $this->assertEquals($normalizer->fromRequest($settings), [
            'max_collaborators_limit' => 3,
            'notifications'          => [
                'enabled'           => true,
                'bookmarks_removed' => ['enabled' => true]
            ]
        ]);

        new FolderSettings($normalizer->fromRequest($settings));
    }
}
