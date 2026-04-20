<?php

declare(strict_types=1);

namespace Tests\Architecture;

class ApplicationArchitectureTest extends BaseArchitectureCase
{
    public function test_application_does_not_depend_infrastructure(): void
    {
        $this->assertDoesNotDependOn($this->application, $this->infrastructure);
    }

    public function test_application_does_not_depend_presentation(): void
    {
        $this->assertDoesNotDependOn($this->application, $this->presentation);
    }

    public function test_application_depend_domain(): void
    {
        $this->assertDependOn($this->application, $this->domain);
    }
}
