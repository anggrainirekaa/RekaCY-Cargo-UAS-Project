@props(['name', 'class' => 'h-5 w-5'])
<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" {{ $attributes->merge(['class' => $class]) }} aria-hidden="true">
@switch($name)
    @case('tower')<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 0a2 2 0 100 4 2 2 0 000-4zm-5 14l3-9m7 9l-3-9m-6 5h6M5 21h14"/>@break
    @case('box')<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-4.5-9 4.5m18 0l-9 4.5m9-4.5v9l-9 4.5m0-9L3 7.5m9 4.5v9M3 7.5v9l9 4.5"/>@break
    @case('alert')<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.3 3.9L2.4 17.6A1.9 1.9 0 004 20.5h16a1.9 1.9 0 001.6-2.9L13.7 3.9a2 2 0 00-3.4 0z"/>@break
    @case('truck')<path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm11 0a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/>@break
    @case('pin')<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.6 7-11a7 7 0 10-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>@break
    @case('users')<path stroke-linecap="round" stroke-linejoin="round" d="M15 19v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1m18 0v-1a4 4 0 00-3-3.9M13 7a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zm4 .5a3 3 0 010 5.8"/>@break
    @case('shield')<path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6l8-3zm-3 9l2 2 4-4"/>@break
    @case('bell')<path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/>@break
    @case('search')<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/>@break
    @case('clock')<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>@break
    @case('check')<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>@break
    @case('menu')<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>@break
    @case('logout')<path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0l3-3m-3 3l3 3m6-9V5a2 2 0 012-2h5a2 2 0 012 2v14a2 2 0 01-2 2h-5a2 2 0 01-2-2v-2"/>@break
    @case('plus')<path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>@break
    @case('building')<path stroke-linecap="round" stroke-linejoin="round" d="M4 21V5l8-2v18M12 9l8 2v10M8 9h.01M8 13h.01M8 17h.01M16 15h.01M16 18h.01"/>@break
    @case('globe')<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9S9.5 5.5 12 3z"/>@break
    @case('radar')<path stroke-linecap="round" stroke-linejoin="round" d="M12 12l6-6M12 21a9 9 0 119-9m-4 0a5 5 0 10-5 5m0-8a3 3 0 103 3"/>@break
    @case('ops')<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h7m6-3l2 2 3-4"/>@break
    @case('chart')<path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>@break
    @case('cog')<path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 100-6 3 3 0 000 6zm7.4-3a7.4 7.4 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7.6 7.6 0 00-2-1.2L14.600 3h-4l-.4 2.600a7.600 7.600 0 00-2 1.200l-2.300-.9-2 3.400 2 1.500a7.400 7.400 0 000 2.400l-2 1.500 2 3.400 2.300-.9c.600.500 1.300.900 2 1.200l.4 2.600h4l.4-2.600c.700-.300 1.400-.700 2-1.200l2.300.9 2-3.400-2-1.500c.100-.400.100-.800.100-1.200z"/>@break
    @case('ticket')<path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h14a2 2 0 012 2v1.500a1.500 1.500 0 000 3V15a2 2 0 01-2 2H5a2 2 0 01-2-2v-1.500a1.500 1.500 0 000-3V9zm11-2v10"/>@break
    @case('file')<path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zm0 0v5h5M9 13h6M9 17h6"/>@break
    @case('route')<path stroke-linecap="round" stroke-linejoin="round" d="M6 19a2 2 0 100-4 2 2 0 000 4zm12-10a2 2 0 100-4 2 2 0 000 4zM8 17h6a3 3 0 000-6h-4a3 3 0 010-6h6"/>@break
    @case('collapse')<path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>@break
    @case('x')<path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>@break
    @case('up')<path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>@break
    @case('down')<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>@break
    @case('user')<path stroke-linecap="round" stroke-linejoin="round" d="M15.500 7.500a3.500 3.500 0 11-7 0 3.500 3.500 0 017 0zM5 20a7 7 0 0114 0"/>@break
    @case('list')<path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>@break
@endswitch
</svg>
