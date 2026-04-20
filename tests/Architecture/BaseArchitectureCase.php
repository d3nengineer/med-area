<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Architecture\ArchitectureAsserts;
use PHPUnit\Architecture\Elements\Layer\Layer;
use Tests\TestCase;

abstract class BaseArchitectureCase extends TestCase
{
    use ArchitectureAsserts;

    public Layer $application;
    public Layer $domain;
    public Layer $infrastructure;
    public Layer $presentation;
    public Layer $shared;

    public function setUp(): void
    {
        parent::setUp();

        $this->application = $this->layer()->leaveByNameStart('Application\\');
        $this->domain = $this->layer()->leaveByNameStart('Domain\\');
        $this->infrastructure = $this->layer()->leaveByNameStart('Infrastructure\\');
        $this->presentation = $this->layer()->leaveByNameStart('Presentation\\');
        $this->shared = $this->layer()->leaveByNameStart('Shared\\');
    }
}
