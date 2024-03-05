<?php

declare(strict_types=1);

namespace App\Http\Handlers\SendInvite;

use App\Models\Folder;
use Illuminate\Support\Str;
use App\Cache\FolderInviteDataRepository;
use App\Contracts\FolderRequestHandlerInterface;
use App\DataTransferObjects\SendInviteRequestData;
use App\Mail\FolderCollaborationInviteMail as InvitationMail;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Mail;

final class SendInvitationToInvitee implements FolderRequestHandlerInterface, InviteeAwareInterface, Scope
{
    use Concerns\HasInviteeData;

    private readonly FolderInviteDataRepository $repository;
    private readonly Mailer $mailer;
    private readonly SendInviteRequestData $data;

    public function __construct(
        SendInviteRequestData $data,
        FolderInviteDataRepository $repository = null,
        Mailer $mailer = null
    ) {
        $this->data = $data;
        $this->repository = $repository ?: app(FolderInviteDataRepository::class);
        $this->mailer = $mailer ?: Mail::mailer();
    }

    public function apply(Builder $builder, Model $model)
    {
        $builder->addSelect(['name']);
    }

    /**
     * @inheritdoc
     */
    public function handle(Folder $folder): void
    {
        $this->repository->store(
            $token = (string) Str::uuid(),
            $this->data->authUser->id,
            $this->invitee->id,
            $folder->id,
            $this->data->permissionsToBeAssigned
        );

        $this->mailer
            ->to($this->data->inviteeEmail)
            ->later(5, new InvitationMail($this->data->authUser, $folder, $token));
    }
}