<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Listeners\Analys;

use Illuminate\Contracts\Queue\ShouldQueue;
use Infrastructure\Listeners\Analys\IndexUserAnalysListener;
use Infrastructure\Listeners\Analys\RemoveUserAnalysFromIndexListener;
use Tests\TestCase;

class AnalysSearchListenersTest extends TestCase
{
    public function test_index_listener_is_queued(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(IndexUserAnalysListener::class));
    }

    public function test_remove_listener_is_queued(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(RemoveUserAnalysFromIndexListener::class));
    }
}
