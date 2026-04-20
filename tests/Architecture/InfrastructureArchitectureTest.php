<?php

declare(strict_types=1);

namespace Tests\Architecture;

class InfrastructureArchitectureTest extends BaseArchitectureCase
{
    public function test_infrastructure_depends_on_domain(): void
    {
        // Repository implementations implement Domain contracts (e.g., Domain\Analys\Repositories\AnalysRepositoryContract).
        $this->assertDependOn($this->infrastructure, $this->domain);
    }

    public function test_infrastructure_does_not_depend_on_presentation(): void
    {
        $this->assertDoesNotDependOn($this->infrastructure, $this->presentation);
    }
}
