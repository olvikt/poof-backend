<?php

namespace Tests\Unit\Auth;

use App\Support\Auth\PhoneNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_phone_to_digits_only(): void
    {
        $this->assertSame('380501234567', PhoneNormalizer::normalize('+380 (50) 123-45-67'));
    }

    #[Test]
    public function it_prepends_country_code_only_when_needed(): void
    {
        $this->assertSame('380501234567', PhoneNormalizer::normalize('0501234567', '+380'));
        $this->assertSame('380501234567', PhoneNormalizer::normalize('+380501234567', '+380'));
    }
}
