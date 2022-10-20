<?php

declare(strict_types=1);

namespace App\Mail;

use App\AcceptInviteUrl;
use Illuminate\Support\Str;
use Illuminate\Mail\Mailable;
use App\DataTransferObjects\User;
use App\DataTransferObjects\Folder;
use App\ValueObjects\Uuid;

final class FolderCollaborationInviteMail extends Mailable
{
    public function __construct(private User $inviter, private Folder $folder, private Uuid $token)
    {
    }

    public function build(): self
    {
        return $this->subject('Folder Collaboration Invitation')
            ->view('emails.folderInvite', [
                'inviterFirstname'  => $this->inviter->firstName->value,
                'inviterSecondName' => $this->inviter->lastName->value,
                'folderName' => $this->folder->name->safe(),
                'InviteLink' => $this->inviteUrl()
            ]);
    }

    public function inviteUrl(): string
    {
        return Str::of((string)new AcceptInviteUrl)
            ->replace(':invite_hash', $this->token->value)
            ->toString();
    }
}
