<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Read-only system overview: secrets live in .env and are never shown or edited from the browser. */
class SettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-users');

        $db = rescue(fn () => DB::connection()->getPdo() !== null, false, false);

        return view('settings.index', [
            'checks' => [
                ['Application', 'Running ('.app()->environment().')', true],
                ['Database ('.config('database.default').')', $db ? 'Connected' : 'Unreachable', $db],
                ['Queue driver', config('queue.default'), true],
                ['Mail driver', config('mail.default').(config('mail.default') === 'log' ? ' (messages are written to the log)' : ''), true],
                ['Private document storage', is_writable(storage_path('app')) ? 'Writable' : 'Not writable', is_writable(storage_path('app'))],
            ],
            'config' => [
                'Group scenario' => config('rekacy.group.name').' — '.config('rekacy.group.route'),
                'Delay LOW up to' => config('rekacy.delay.low_max_hours').' h',
                'Delay MEDIUM up to' => config('rekacy.delay.medium_max_hours').' h',
                'Escalation threshold' => config('rekacy.delay.escalate_hours').' h',
                'Display timezone' => config('rekacy.timezone'),
                'Demo (simulated) tracking data' => config('rekacy.demo_data') ? 'Yes — labelled on every map' : 'No',
                'Geocoding provider' => parse_url(config('rekacy.geocoding.url'), PHP_URL_HOST),
                'Weather provider' => parse_url(config('rekacy.weather.url'), PHP_URL_HOST),
                'Email notifications' => config('rekacy.notifications.mail') ? 'Enabled' : 'Disabled',
            ],
        ]);
    }
}
