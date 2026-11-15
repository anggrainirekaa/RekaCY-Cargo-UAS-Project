<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Console\Command;

class DetectDelays extends Command
{
    protected $signature = 'rekacy:detect-delays';

    protected $description = 'Re-evaluate the delay rule for every active shipment (scheduled every 15 minutes).';

    public function handle(ShipmentService $shipments): int
    {
        $checked = 0;
        $flagged = 0;

        Shipment::query()->active()->with('customer')->chunkById(100, function ($chunk) use ($shipments, &$checked, &$flagged) {
            foreach ($chunk as $shipment) {
                $result = $shipments->reevaluateDelay($shipment);
                $checked++;
                $flagged += $result->isDelayed ? 1 : 0;
            }
        });

        $this->info("Checked {$checked} active shipments; {$flagged} currently delayed.");

        return self::SUCCESS;
    }
}
