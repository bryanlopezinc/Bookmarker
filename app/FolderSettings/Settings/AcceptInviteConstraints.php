<?php

declare(strict_types=1);

namespace App\FolderSettings\Settings;

use App\Rules\DistinctRule;
use App\FolderSettings\AcceptInviteConstraints as Constraints;

final class AcceptInviteConstraints extends AbstractSetting
{
    public function value(): Constraints
    {
        if ( ! $this->isSet()) {
            return new Constraints([]);
        }

        return new Constraints($this->value);
    }

    /**
     * @inheritdoc
     */
    public function id(): string
    {
        return 'accept_invite_constraints';
    }

    /**
     * @inheritdoc
     */
    protected function rules(): array
    {
        return [
            new DistinctRule(),
            'sometimes',
            'array',
            'in:InviterMustBeAnActiveCollaborator,InviterMustHaveRequiredPermission'
        ];
    }

    /**
     * @inheritdoc
     */
    public function toArray(): mixed
    {
        return [
            $this->id() => $this->value()->all()
        ];
    }
}
