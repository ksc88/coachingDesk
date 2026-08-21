<?php

namespace Tests\Unit;

use App\Support\Validation\ContactRules;
use PHPUnit\Framework\TestCase;

class ContactRulesTest extends TestCase
{
    public function test_sms_recipient_adds_india_country_code(): void
    {
        $this->assertSame('919876543210', ContactRules::toSmsRecipient('9876543210'));
        $this->assertSame('919876543210', ContactRules::toSmsRecipient('+91 98765 43210'));
        $this->assertSame('919876543210', ContactRules::toSmsRecipient('09876543210'));
        $this->assertSame('', ContactRules::toSmsRecipient('12345'));
    }
}
