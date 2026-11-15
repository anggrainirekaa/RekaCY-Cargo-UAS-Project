<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;

/** Global search. Scope rules: customers only ever see their own shipments and nothing else. */
class SearchService
{
    /** @return array{shipments: list<array>, customers: list<array>, carriers: list<array>, locations: list<array>} */
    public function search(User $user, string $term, int $limit = 6): array
    {
        $term = trim($term);
        $out = ['shipments' => [], 'customers' => [], 'carriers' => [], 'locations' => []];

        if (mb_strlen($term) < 2) {
            return $out;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $out['shipments'] = Shipment::query()->visibleTo($user)
            ->where(fn ($q) => $q->where('shipments.shipment_number', 'like', $like)->orWhere('shipments.cargo_type', 'like', $like))
            ->with(['origin:id,city', 'destination:id,city'])->latest('id')->limit($limit)->get()
            ->map(fn (Shipment $s) => ['title' => $s->shipment_number, 'sub' => $s->origin->city.' → '.$s->destination->city.' · '.$s->status->label(), 'url' => route('shipments.show', $s)])->all();

        if ($user->isStaff()) {
            $out['customers'] = User::query()->where('role', 'customer')
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('company', 'like', $like)->orWhere('email', 'like', $like))
                ->limit($limit)->get()
                ->map(fn (User $u) => ['title' => $u->name, 'sub' => ($u->company ?? $u->email), 'url' => route('shipments.index', ['q' => $u->name])])->all();

            $out['carriers'] = Carrier::query()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->limit($limit)->get()
                ->map(fn (Carrier $c) => ['title' => $c->name, 'sub' => $c->code.' · '.$c->mode, 'url' => route('shipments.index', ['carrier_id' => $c->id])])->all();

            $out['locations'] = Location::query()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('city', 'like', $like)->orWhere('country', 'like', $like))
                ->limit($limit)->get()
                ->map(fn (Location $l) => ['title' => $l->name, 'sub' => $l->city.', '.$l->country, 'url' => route('locations.index')])->all();
        }

        return $out;
    }
}
