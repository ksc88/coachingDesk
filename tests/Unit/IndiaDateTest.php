<?php

namespace Tests\Unit;

use App\Support\Format\IndiaDate;
use Tests\TestCase;

class IndiaDateTest extends TestCase
{
    public function test_formats_iso_date_as_dd_mm_yyyy(): void
    {
        $this->assertSame('05-08-2026', IndiaDate::format('2026-08-05'));
    }

    public function test_parses_display_date_to_storage(): void
    {
        $this->assertSame('2026-08-05', IndiaDate::toStorage('05-08-2026'));
        $this->assertSame('2026-08-05', IndiaDate::toStorage('2026-08-05'));
    }

    public function test_display_returns_dash_for_empty(): void
    {
        $this->assertSame('—', IndiaDate::display(null));
    }
}
