<x-app :titulo="$cliente->nome">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1>{{ $cliente->nome }}</h1>
            <p class="text-suave">{{ $cliente->tipo_pessoa === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física' }}{{ $cliente->cpf_cnpj ? ' · '.$cliente->documentoFormatado() : '' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($cliente->aceita_whatsapp && ($link = $cliente->linkWhatsapp()))
                <a href="{{ $link }}" target="_blank" rel="noopener" class="btn">WhatsApp</a>
            @endif
            @can('clientes.editar')<a href="{{ route('clientes.edit', $cliente) }}" class="btn">Editar</a>@endcan
            @can('veiculos.editar')<a href="{{ route('veiculos.create', ['cliente_id' => $cliente->id]) }}" class="btn-primario">Adicionar carro</a>@endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr] [&>*]:min-w-0">
        <section class="card card-corpo">
            <h2 class="mb-4">Contato e endereço</h2>
            <dl class="space-y-3 text-[15px]">
                <div><dt class="text-sm text-suave">WhatsApp</dt><dd>{{ $cliente->whatsapp ?: '—' }} @unless($cliente->aceita_whatsapp)<span class="text-sm text-suave">(não aceita avisos)</span>@endunless</dd></div>
                <div><dt class="text-sm text-suave">Outro telefone</dt><dd>{{ $cliente->telefone ?: '—' }}</dd></div>
                <div><dt class="text-sm text-suave">E-mail</dt><dd>{{ $cliente->email ?: '—' }}</dd></div>
                <div><dt class="text-sm text-suave">Endereço</dt><dd>{{ $cliente->enderecoCompleto() ?: '—' }}@if($cliente->cep)<br><span class="text-suave">CEP {{ substr($cliente->cep, 0, 5).'-'.substr($cliente->cep, 5) }}</span>@endif</dd></div>
                @if ($cliente->ie)<div><dt class="text-sm text-suave">Inscrição estadual</dt><dd>{{ $cliente->ie }}</dd></div>@endif
                @if ($cliente->observacoes)<div><dt class="text-sm text-suave">Observações</dt><dd class="whitespace-pre-line">{{ $cliente->observacoes }}</dd></div>@endif
            </dl>
        </section>

        <section class="card">
            <div class="border-b border-linha px-5 py-4"><h2>Carros ({{ $cliente->veiculos->count() }})</h2></div>
            @forelse ($cliente->veiculos as $v)
                <a href="{{ route('veiculos.show', $v) }}" class="flex items-center gap-4 border-b border-linha px-5 py-3 last:border-b-0 hover:bg-[#fafbfc]">
                    <x-placa :placa="$v->placa" />
                    <span class="min-w-0 flex-1 truncate font-medium">{{ $v->descricao() ?: 'Modelo não informado' }}</span>
                    <span class="text-sm text-suave">{{ $v->km_atual ? number_format($v->km_atual, 0, ',', '.').' km' : '' }}</span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-suave">Nenhum carro vinculado.</p>
            @endforelse
        </section>
    </div>

    @can('clientes.editar')
        <section class="card card-corpo mt-6">
            <h2 class="mb-1">Portal do cliente</h2>
            <p class="mb-3 text-sm text-suave">Link pessoal, sem senha, com os carros, serviços, garantias e próximas revisões. Só envie para o próprio cliente.</p>
            @if ($cliente->portal_token)
                @php $link = route('portal.ver', $cliente->portal_token); @endphp
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm" data-copiar="{{ $link }}">Copiar link</button>
                    @if ($cliente->aceita_whatsapp && ($w = $cliente->linkWhatsapp('Olá, '.explode(' ', $cliente->nome)[0].'! Aqui você acompanha os serviços e as garantias dos seus carros na '.auth()->user()->empresa->nome_fantasia.': '.$link)))<a href="{{ $w }}" target="_blank" rel="noopener" class="btn btn-sm">Enviar pelo WhatsApp</a>@endif
                    <form method="post" action="{{ route('clientes.portal', $cliente) }}" data-confirmar="Gerar outro link? O atual deixa de funcionar.">@csrf<button class="btn btn-sm">Gerar outro</button></form>
                    <form method="post" action="{{ route('clientes.portal.revogar', $cliente) }}">@csrf @method('delete')<button class="btn-perigo btn-sm">Desativar</button></form>
                </div>
            @else
                <form method="post" action="{{ route('clientes.portal', $cliente) }}">@csrf<button class="btn btn-sm">Criar link do portal</button></form>
            @endif
        </section>
    @endcan

    @can('os.ver')
        <section class="card mt-6">
            <div class="border-b border-linha px-5 py-4"><h2>Ordens de serviço</h2></div>
            @include('os._historico', ['ordens' => $cliente->ordens()->with(['itens', 'veiculo'])->limit(30)->get(), 'mostrarPlaca' => true])
        </section>
    @endcan

    @can('clientes.excluir')
        @if ($cliente->veiculos->isEmpty())
            <form method="post" action="{{ route('clientes.destroy', $cliente) }}" class="mt-8" data-confirmar="Excluir este cliente?">
                @csrf @method('delete')
                <button class="btn-perigo btn-sm">Excluir cliente</button>
            </form>
        @endif
    @endcan
</x-app>
