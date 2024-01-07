<?php

namespace Tests\Unit;

use App\PaginationData;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PaginationDataTest extends TestCase
{
    public function testWillReturnDefaultsWhenNoDataIsGiven(): void
    {
        $data = PaginationData::fromRequest(new Request());

        $this->assertEquals([1, $data::DEFAULT_PER_PAGE], [$data->page(), $data->perPage()]);
    }

    public function testWillReturnOneIfPageIsLessThanOne(): void
    {
        $data = PaginationData::fromRequest(new Request(['page' => -1]));
        $this->assertEquals(1, $data->page());
    }

    public function testWillReturnOneIfPageIsGreaterThanMaxPage(): void
    {
        $data = PaginationData::fromRequest(new Request(['page' => PaginationData::MAX_PAGE + 1]));
        $this->assertEquals(1, $data->page());

        $data = PaginationData::fromRequest(new Request(['page' => PHP_INT_MAX + 1]));
        $this->assertEquals(1, $data->page());
    }

    public function testWillReturnPageValueIfPageIsValid(): void
    {
        $data = PaginationData::fromRequest(new Request(['page' => 2]));
        $this->assertEquals(2, $data->page());
    }

    public function testWillReturnDefaultIfPerPageIsGreaterThanMaxPerPage(): void
    {
        $request = new Request(['per_page' => PaginationData::new()->getMaxPerPage() + 1]);
        $data = PaginationData::fromRequest($request);
        $this->assertEquals($data::DEFAULT_PER_PAGE, $data->perPage());

        $request = new Request(['per_page' => PHP_INT_MAX + 1]);
        $data = PaginationData::fromRequest($request);
        $this->assertEquals($data::DEFAULT_PER_PAGE, $data->perPage());

        $data = new PaginationData(1, 50);
        $data->maxPerPage(50);
        $this->assertEquals(50, $data->perPage());
    }

    public function testWillReturnDefaultIfPerPageIsLessThanDefaultPerPage(): void
    {
        $request = new Request(['per_page' => PaginationData::DEFAULT_PER_PAGE - 1]);

        $data = PaginationData::fromRequest($request);
        $this->assertEquals($data::DEFAULT_PER_PAGE, $data->perPage());
    }
}
