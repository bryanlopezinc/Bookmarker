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
    //The keys of the attributes that will be returned when the "invite_hash"
    // input is decrypted bt the accept invite request handler.
    public const INVITER_ID = 'inviterID';
    public const INVITEE_ID = 'inviteeID';
    public const FOLDER_ID = 'folderID';
    public const PERMISSIONS = 'permission';

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
                'inviterFirstname'  => $this->inviter->firstName->value,
                'inviterSecondName' => $this->inviter->lastName->value,
                'folderName' => $this->folder->name->safe(),
                'InviteLink' => $this->inviteUrl()
            ]);
    }

    public function inviteUrl(): string
    {
        $signedUrl = URL::temporarySignedRoute('acceptFolderCollaborationInvite', now()->addDay(), [
            'invite_hash' => Crypt::encrypt([
                self::INVITER_ID => $this->inviter->id->toInt(),
                self::INVITEE_ID => $this->invitee->id->toInt(),
                self::FOLDER_ID => $this->folder->folderID->toInt(),
                self::PERMISSIONS => $this->permissions->serialize()
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
