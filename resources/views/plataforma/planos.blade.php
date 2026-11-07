@php use App\Support\Dinheiro; @endphp
<x-app titulo="Planos">
    <h1 class="mb-4">Planos</h1>
    @include('plataforma._menu')
    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($planos->push(new \App\Models\Plano(['ativo' => true])) as $p)
            <form method="post" action="{{ $p->exists ? route('plataforma.planos.update', $p) : route('plataforma.planos.store') }}" class="card card-corpo space-y-3">
                @csrf @if($p->exists) @method('put') @endif
                <h2>{{ $p->exists ? $p->nome : 'Novo plano' }}</h2>
                <div class="grid grid-cols-2 gap-3">
                    <x-campo nome="nome" rotulo="Nome" :valor="$p->nome" required />
                    <x-campo nome="slug" rotulo="Código" :valor="$p->slug" required />
                    <x-campo nome="preco" rotulo="Preço (R$/mês)" :valor="$p->exists ? Dinheiro::campo($p->preco_centavos) : ''" mascara="dinheiro" required />
                    <x-campo nome="ordem" rotulo="Ordem" tipo="number" :valor="$p->ordem" />
                    <x-campo nome="limite_usuarios" rotulo="Usuários" tipo="number" :valor="$p->limite_usuarios" ajuda="Vazio = sem limite" />
                    <x-campo nome="limite_lojas" rotulo="Lojas" tipo="number" :valor="$p->limite_lojas" />
                    <x-campo nome="limite_consultas_placa" rotulo="Placas/mês" tipo="number" :valor="$p->limite_consultas_placa" />
                </div>
                <label class="flex items-center gap-2 text-[15px]"><input type="checkbox" name="whatsapp_automatico" value="1" class="h-4 w-4 accent-asfalto" @checked($p->whatsapp_automatico)> WhatsApp automático</label>
                <label class="flex items-center gap-2 text-[15px]"><input type="hidden" name="ativo" value="0"><input type="checkbox" name="ativo" value="1" class="h-4 w-4 accent-asfalto" @checked($p->ativo)> Disponível para assinar</label>
                <button class="btn-primario btn-sm">Salvar plano</button>
            </form>
        @endforeach
    </div>
</x-app>
