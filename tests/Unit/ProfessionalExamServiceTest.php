<?php

namespace Tests\Unit;

use App\Services\ProfessionalExamService;
use Tests\TestCase;

class ProfessionalExamServiceTest extends TestCase
{
    public function test_normalizes_storage_paths_to_public_urls(): void
    {
        $service = new ProfessionalExamService();

        $this->assertSame(url('/storage/certificate-logos/example.png'), $service->normalizeLogoUrl('storage/certificate-logos/example.png'));
        $this->assertSame(url('/storage/certificate-logos/example.png'), $service->normalizeLogoUrl('/storage/certificate-logos/example.png'));
        $this->assertSame('https://cdn.example.com/logo.png', $service->normalizeLogoUrl('https://cdn.example.com/logo.png'));
    }
}
