@if (session('status'))
    <div class="mb-4 flex items-start gap-3 rounded-xl bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300 ring-1 ring-emerald-400/30" role="status">
        <x-icon name="check" class="mt-0.5 h-4 w-4" /> <span>{{ session('status') }}</span>
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-xl bg-rose-500/10 px-4 py-3 text-sm text-rose-300 ring-1 ring-rose-400/30" role="alert">
        <p class="font-semibold">Please fix the following:</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
