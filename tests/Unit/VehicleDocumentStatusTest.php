<?php

namespace Tests\Unit;

use App\Support\VehicleDocumentStatus;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VehicleDocumentStatusTest extends TestCase
{
    #[Test]
    public function it_labels_expired_documents_as_days_overdue(): void
    {
        $today = Carbon::parse('2026-07-06');
        $status = VehicleDocumentStatus::compute(
            Carbon::parse('2026-06-18'),
            7,
            $today,
        );

        $this->assertSame('expired', $status['status_tier']);
        $this->assertSame('18 Days Overdue', $status['status_label']);
        $this->assertSame('18d', $status['status_sublabel']);
        $this->assertSame(-18, $status['days_remaining']);
        $this->assertSame('danger', $status['badge_color']);
        $this->assertSame('danger', $status['row_color']);
    }

    #[Test]
    public function it_labels_expiring_today(): void
    {
        $today = Carbon::parse('2026-07-06');
        $status = VehicleDocumentStatus::compute(
            Carbon::parse('2026-07-06'),
            7,
            $today,
        );

        $this->assertSame('today', $status['status_tier']);
        $this->assertSame('Expires Today', $status['status_label']);
        $this->assertSame(0, $status['days_remaining']);
    }

    #[Test]
    public function it_labels_reminder_window_documents(): void
    {
        $today = Carbon::parse('2026-07-06');
        $status = VehicleDocumentStatus::compute(
            Carbon::parse('2026-07-08'),
            7,
            $today,
        );

        $this->assertSame('reminder', $status['status_tier']);
        $this->assertSame('2 Days Left', $status['status_label']);
        $this->assertSame(2, $status['days_remaining']);
    }

    #[Test]
    public function it_labels_valid_documents_beyond_reminder_window(): void
    {
        $today = Carbon::parse('2026-07-06');
        $status = VehicleDocumentStatus::compute(
            Carbon::parse('2026-08-01'),
            7,
            $today,
        );

        $this->assertSame('valid', $status['status_tier']);
        $this->assertSame('Valid', $status['status_label']);
        $this->assertGreaterThan(7, $status['days_remaining']);
    }

    #[Test]
    public function it_sorts_by_urgency_with_most_overdue_first(): void
    {
        $sorted = VehicleDocumentStatus::sortByUrgency([
            ['id' => 'a', 'days_remaining' => 5],
            ['id' => 'b', 'days_remaining' => -20],
            ['id' => 'c', 'days_remaining' => -100],
            ['id' => 'd', 'days_remaining' => 0],
        ]);

        $this->assertSame(['c', 'b', 'd', 'a'], array_column($sorted, 'id'));
    }
}
