<?php

declare(strict_types=1);

namespace App;

use Illuminate\Http\Request;

final class PaginationData
{
    /** The default items to be returned perPage */
    public const DEFAULT_PER_PAGE = 15;

    /** The maximum page that can be requested */
    public const MAX_PAGE = 2000;

    /** Maximum items that can be returned perPage */
    private int $maxPerPage = 39;

    public function __construct(private readonly int $page = 1, private readonly int $perPage = self::DEFAULT_PER_PAGE)
    {
    }

    public static function new(): self
    {
        return new self();
    }

    public static function fromRequest(Request $request, string $page = 'page', string $perPage = 'per_page'): self
    {
        return new self(
            (int) $request->input($page, 1),
            (int) $request->input($perPage, self::DEFAULT_PER_PAGE)
        );
    }

    /**
     * @return array<string,array<string>>
     */
    public function asValidationRules(): array
    {
        return [
            'page' => ['nullable', 'filled', 'int', 'min:1', 'max:' . self::MAX_PAGE],
            'per_page' => ['nullable', 'filled', 'int', 'min:' . self::DEFAULT_PER_PAGE, 'max:' . $this->getMaxPerPage()],
        ];
    }

    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    public function maxPerPage(int $value): self
    {
        $this->maxPerPage = $value;

        return $this;
    }

    public function page(): int
    {
        return ($this->page < 1 || $this->page > self::MAX_PAGE) ? 1 : $this->page;
    }

    public function perPage(): int
    {
        return ($this->perPage > $this->maxPerPage || $this->perPage < self::DEFAULT_PER_PAGE)
            ? self::DEFAULT_PER_PAGE
            : $this->perPage;
    }
}
