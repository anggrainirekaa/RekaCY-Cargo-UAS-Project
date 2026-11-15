<?php

use App\Broadcasting\ChannelAuth;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn ($user, $id) => ChannelAuth::user($user, (int) $id));
Broadcast::channel('staff.live', fn ($user) => ChannelAuth::staff($user));
Broadcast::channel('shipment.{id}', fn ($user, $id) => ChannelAuth::shipment($user, (int) $id));
