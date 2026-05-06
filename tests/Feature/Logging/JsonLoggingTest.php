<?php

declare(strict_types=1);

namespace Tests\Feature\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class JsonLoggingTest extends TestCase
{
    private TestHandler $handler;

    private Logger $channel;

    private MonologLogger $monolog;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestHandler();
        $this->handler->setFormatter(new JsonFormatter());

        $this->assertArrayHasKey(
            'json',
            config('logging.channels'),
            'The "json" log channel is not configured. Add it to config/logging.php.'
        );

        /** @var Logger $channel */
        $this->channel = app(\Illuminate\Log\LogManager::class)->channel('json');

        /** @var MonologLogger $monolog */
        $this->monolog = $this->channel->getLogger();
        $this->monolog->pushHandler($this->handler);
    }

    public function tearDown(): void
    {
        // Pop the probe handler to avoid leaking it into other tests via the singleton LogManager.
        $this->monolog->popHandler();

        parent::tearDown();
    }

    public function test_json_formatter_produces_valid_json(): void
    {
        $this->channel->info('test message', ['key' => 'value']);

        $this->assertTrue($this->handler->hasInfoRecords());

        $formatted = $this->handler->getRecords()[0]->formatted;

        $decoded = json_decode($formatted, true);
        $this->assertNotNull($decoded, 'Formatted log record is not valid JSON');
    }

    public function test_json_formatter_contains_required_fields(): void
    {
        $this->channel->info('test message', ['key' => 'value']);

        $formatted = $this->handler->getRecords()[0]->formatted;
        $decoded = json_decode($formatted, true);

        $this->assertArrayHasKey('datetime', $decoded);
        $this->assertArrayHasKey('level_name', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('context', $decoded);
    }

    public function test_json_formatter_sets_correct_level_and_message(): void
    {
        $this->channel->info('hello from json logger', ['user_id' => 42]);

        $formatted = $this->handler->getRecords()[0]->formatted;
        $decoded = json_decode($formatted, true);

        $this->assertSame('INFO', $decoded['level_name']);
        $this->assertSame('hello from json logger', $decoded['message']);
        $this->assertSame(42, $decoded['context']['user_id']);
    }

    public function test_json_formatter_datetime_is_valid_iso8601(): void
    {
        $this->channel->info('timestamp check');

        $formatted = $this->handler->getRecords()[0]->formatted;
        $decoded = json_decode($formatted, true);

        $this->assertNotEmpty($decoded['datetime']);
        $this->assertNotFalse(
            \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $decoded['datetime']),
            'datetime field is not valid ISO 8601'
        );
    }
}
