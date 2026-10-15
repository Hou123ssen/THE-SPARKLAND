<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\MediaDerivativeGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessMediaAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $mediaAssetId)
    {
    }

    public function handle(MediaDerivativeGenerator $generator): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if ($asset === null) {
            return;
        }

        $asset->update(['processing_status' => 'processing', 'processing_error' => null]);

        try {
            $generator->generate($asset);
            $asset->update(['processing_status' => 'ready', 'processed_at' => now(), 'processing_error' => null]);
        } catch (Throwable $exception) {
            $asset->update([
                'processing_status' => 'failed',
                'processing_error' => mb_substr($exception->getMessage(), 0, 1000),
            ]);

            throw $exception;
        }
    }
}
