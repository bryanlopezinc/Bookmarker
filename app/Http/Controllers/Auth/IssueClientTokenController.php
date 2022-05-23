<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Requests\IssueClientTokenRequest;
use Illuminate\Http\Response;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Psr\Http\Message\ServerRequestInterface;

final class IssueClientTokenController extends AccessTokenController
{
    public function __invoke(ServerRequestInterface $serverRequest, IssueClientTokenRequest $request): Response
    {
        return $this->issueToken($serverRequest);
    }
}