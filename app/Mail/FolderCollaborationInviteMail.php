<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Folder;
use App\Models\User;
use App\Utils\UrlPlaceholders;
use App\ValueObjects\Url;
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
                'inviterName'  => $this->inviter->full_name,
                'folderName'   => $this->folder->name,
                'InviteLink'   => $this->inviteUrl()
            ]);
    }

    public function inviteUrl(): string
    {
        $url = config('settings.ACCEPT_INVITE_URL');

        //Ensure is Valid.
        new Url($url);

        if (UrlPlaceholders::missing($url, [':invite_hash'])) {
            throw new \Exception("The verification url  must contain the [:invite_hash] placeholder/s");
        }

        return Str::of($url)
            ->replace(':invite_hash', $this->token)
            ->toString();
    }
}
