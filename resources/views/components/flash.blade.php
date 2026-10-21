{{-- Messages flash de session (succès / erreur) --}}
@if (session('status'))
    <div role="status" class="mb-4 rounded-xl border-l-4 border-green-700 bg-green-50 px-4 py-3 text-sm font-medium text-green-900">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div role="alert" class="mb-4 rounded-xl border-l-4 border-red-700 bg-red-50 px-4 py-3 text-sm font-medium text-red-900">
        {{ session('error') }}
    </div>
@endif
