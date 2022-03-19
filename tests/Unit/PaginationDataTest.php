<?php

namespace Tests\Unit;

use App\PaginationData;
use Tests\TestCase;

class PaginationDataTest extends TestCase
{
    public function testWillReturnDefaultsWhenNoDataIsGiven(): void
    {
        $data = PaginationData::fromRequest(request());

        $this->assertEquals([1, $data::DEFAULT_PER_PAGE], [$data->page(), $data->perPage()]);
    }

    public function testWillReturnOneIfPageIsLessThanOne(): void
    {
        request()->merge(['page' => -1]);

        $data = PaginationData::fromRequest(request());
        $this->assertEquals(1, $data->page());
    }

    public function testWillReturnOneIfPageIsGreaterThanMaxPage(): void
    {
        request()->merge(['page' => PaginationData::MAX_PAGE + 1]);

        $data = PaginationData::fromRequest(request());
        $this->assertEquals(1, $data->page());

        request()->merge(['page' => PHP_INT_MAX + 1]);

        $data = PaginationData::fromRequest(request());
        $this->assertEquals(1, $data->page());
    }

    public function testWillReturnPageValueIfPageIsValid(): void
    {
        request()->merge(['page' => 2]);

        $data = PaginationData::fromRequest(request());
        $this->assertEquals(2, $data->page());
    }

    public function testWillReturnDefaultIfPerPageIsGreaterThanMaxPerPage(): void
    {
        request()->merge(['per_page' => PaginationData::MAX_PER_PAGE + 1]);

        $data = PaginationData::fromRequest(request());
        $this->assertEquals($data::DEFAULT_PER_PAGE, $data->perPage());

        request()->merge(['per_page' => PHP_INT_MAX + 1]);

        $data = PaginationData::fromRequest(request());
        $this->assertEquals($data::DEFAULT_PER_PAGE, $data->perPage());
    }

    public function testWillReturnDefaultIfPerPageIsLessThanDefaultPerPage(): void
    {
        request()->merge(['per_page' => PaginationData::DEFAULT_PER_PAGE - 1]);

        $data = PaginationData::fromRequest(request());
        $this->assertEquals($data::DEFAULT_PER_PAGE, $data->perPage());
    }
}
