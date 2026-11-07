<x-app titulo="Avisos do gateway">
    <h1 class="mb-4">Avisos do gateway de cobrança</h1>
    @include('plataforma._menu')
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Recebido</th><th>Evento</th><th>Código</th><th>Resultado</th></tr></thead>
            <tbody>
            @forelse ($webhooks as $w)
                <tr><td class="whitespace-nowrap">{{ $w->created_at->format('d/m H:i') }}</td><td>{{ $w->evento }}</td><td class="text-sm text-suave">{{ $w->evento_id }}</td><td @class(['text-erro' => $w->erro])>{{ $w->erro ?? ($w->processado_em ? 'Aplicado' : 'Pendente') }}</td></tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-suave">Nenhum aviso recebido. Cadastre a URL /webhooks/asaas no painel do Asaas.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $webhooks->links() }}
    </div>
</x-app>
