<?php

declare(strict_types=1);

namespace Tests\Architecture;

class DomainArchitectureTest extends BaseArchitectureCase
{
    public function test_domain_not_depend_application(): void
    {
        $this->assertDoesNotDependOn($this->domain, $this->application);
    }

    public function test_domain_not_depend_infrastructure(): void
    {
        $this->assertDoesNotDependOn($this->domain, $this->infrastructure);
    }

    public function test_domain_not_depend_presentation(): void
    {
        $this->assertDoesNotDependOn($this->domain, $this->presentation);
    }
}
