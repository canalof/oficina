@if (session('ok'))
    <div class="mb-5 flex items-start gap-3 rounded-md border border-[#b7dcc6] bg-[#eef8f2] px-4 py-3 text-[15px] text-[#185c35]" role="status">
        <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12l5 5L19 7"/></svg>
        <span>{{ session('ok') }}</span>
    </div>
@endif
@if (session('erro'))
    <div class="mb-5 rounded-md border border-[#ecc3bf] bg-[#fcf0ef] px-4 py-3 text-[15px] text-[#8c241d]" role="alert">{{ session('erro') }}</div>
@endif
