<?php

declare(strict_types=1);

namespace App;

use Illuminate\Http\Request;

final class PaginationData
{
    /** The default items to be returned perPage */
    public const DEFAULT_PER_PAGE = 15;

    /** Maximum items that can be returned perPage */
    public const MAX_PER_PAGE = 39;

    /** The maximum page that can be requested */
    public const MAX_PAGE = 2000;

    public function __construct(private readonly int $page = 1, private readonly int $perPage = self::DEFAULT_PER_PAGE)
    {
    }

    public static function fromRequest(Request $request, string $page = 'page', string $perPage = 'per_page'): self
    {
        return new self(
            (int) $request->input($page, 1),
            (int) $request->input($perPage, self::DEFAULT_PER_PAGE)
        );
    }

    /**
     * @return array<string,array> where first key is the 'page' rules and second key the 'per_page' rules
     */
    public static function rules(): array
    {
        return [
            'page' => ['nullable', 'int', 'min:1', 'max:' . self::MAX_PAGE],
            'per_page' => ['nullable', 'int', 'min:' . self::DEFAULT_PER_PAGE, 'max:' . self::MAX_PER_PAGE],
        ];
    }

    public function page(): int
    {
        return ($this->page < 1 || $this->page > self::MAX_PAGE) ? 1 : $this->page;
    }

    public function perPage(): int
    {
        return ($this->perPage > self::MAX_PER_PAGE || $this->perPage < self::DEFAULT_PER_PAGE)
            ? self::DEFAULT_PER_PAGE
            : $this->perPage;
    }
}
