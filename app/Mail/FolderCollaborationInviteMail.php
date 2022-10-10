<?php

declare(strict_types=1);

namespace App\Mail;

use App\AcceptInviteUrl;
use Illuminate\Support\Str;
use Illuminate\Mail\Mailable;
use App\DataTransferObjects\User;
use App\DataTransferObjects\Folder;
use App\FolderPermissions;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Crypt;
use App\ValueObjects\Url as UrlValueObject;

final class FolderCollaborationInviteMail extends Mailable
{
    public function __construct(
        private User $inviter,
        private Folder $folder,
        private User $invitee,
        private FolderPermissions $permissions
    ) {
    }

    public function build(): self
    {
        return $this->subject('Folder Collaboration Invitation')
            ->view('emails.folderInvite', [
                'inviterFirstname'  => $this->inviter->firstname->value,
                'inviterSecondName' => $this->inviter->lastname->value,
                'folderName' => $this->folder->name->safe(),
                'InviteLink' => $this->inviteUrl()
            ]);
    }

    private function inviteUrl(): string
    {
        $signedUrl = URL::temporarySignedRoute('acceptFolderCollaborationInvite', now()->addDay(), [
            'invite_hash' => Crypt::encrypt([
                'from' => $this->inviter->id->toInt(),
                'to' => $this->invitee->id->toInt(),
                'permissions' => $this->permissions->serialize()
            ])
        ]);

        $components = (new UrlValueObject($signedUrl))->parseQuery();

        return Str::of((string)new AcceptInviteUrl)
            ->replace(':expires', $components['expires'])
            ->replace(':signature', $components['signature'])
            ->replace(':invite_hash', $components['invite_hash'])
            ->toString();
    }
}
