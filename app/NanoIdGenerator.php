<?php

declare(strict_types=1);

namespace App;

use App\Contracts\IdGeneratorInterface;
use Hidehalo\Nanoid\Client;

final class NanoIdGenerator implements IdGeneratorInterface
{
    private const ALPHABETS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @inheritdoc
     */
    public function generate(): string
    {
        $client = new Client();

        return $client->formattedId(self::ALPHABETS, self::LENGTH);
    }

    /**
     * @inheritdoc
     */
    public function isValid(string $Id): bool
    {
        return strlen($Id) === self::LENGTH && ctype_alnum($Id);
    }
}
