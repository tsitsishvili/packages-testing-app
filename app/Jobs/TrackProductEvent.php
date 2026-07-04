<?php

namespace App\Jobs;

use App\Models\ProductEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TrackProductEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $productId,
        public ?int $userId,
        public string $eventType,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ProductEvent::create([
            'product_id' => $this->productId,
            'user_id' => $this->userId,
            'event_type' => $this->eventType,
            'created_at' => now(),
        ]);
    }
}
