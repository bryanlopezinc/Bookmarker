<?php

declare(strict_types=1);

namespace App\Mail;

use App\AcceptInviteUrl;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Mail\Mailable;

final class FolderCollaborationInviteMail extends Mailable
{
    public function __construct(private User $inviter, private Folder $folder, private string $token)
    {
    }

    public function build(): self
    {
        return $this->subject('Folder Collaboration Invitation')
            ->view('emails.folderInvite', [
                'inviterFirstName'  => $this->inviter->first_name,
                'inviterSecondName' => $this->inviter->last_name,
                'folderName'        => $this->folder->name,
                'InviteLink'        => $this->inviteUrl()
            ]);
    }

    public function inviteUrl(): string
    {
        return Str::of((string)new AcceptInviteUrl())
            ->replace(':invite_hash', $this->token)
            ->toString();
    }
}
