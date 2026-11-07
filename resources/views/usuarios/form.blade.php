<x-app :titulo="$usuario->exists ? 'Editar usuário' : 'Novo usuário'">
    <h1 class="mb-5">{{ $usuario->exists ? 'Editar usuário' : 'Novo usuário' }}</h1>
    <form method="post" data-avisar-saida action="{{ $usuario->exists ? route('usuarios.update', $usuario->id) : route('usuarios.store') }}" class="card card-corpo max-w-2xl space-y-4">
        @csrf
        @if ($usuario->exists) @method('put') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-campo nome="name" rotulo="Nome" :valor="$usuario->name" required />
            <x-campo nome="email" rotulo="E-mail de acesso" tipo="email" :valor="$usuario->email" required />
            <x-campo nome="telefone" rotulo="Telefone" :valor="$usuario->telefone" mascara="telefone" />
            <label class="campo"><span>Perfil</span>
                <select name="perfil" class="input">
                    @foreach (\App\Support\Perfil::opcoes() as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('perfil', $usuario->perfil?->value) === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </label>
            <x-campo nome="comissao_percentual" rotulo="Comissão sobre serviços (%)" tipo="number" step="0.5" min="0" max="100" :valor="$usuario->comissao_percentual ? rtrim(rtrim((string) $usuario->comissao_percentual, '0'), '.') : ''" ajuda="Calculada na entrega do carro, sobre os serviços em que a pessoa é o mecânico." />
            @if ($lojas->count() > 1)
                <label class="campo"><span>Loja</span>
                    <select name="loja_id" class="input"><option value="">Todas</option>
                        @foreach ($lojas as $l)<option value="{{ $l->id }}" @selected((string) old('loja_id', $usuario->loja_id) === (string) $l->id)>{{ $l->nome }}</option>@endforeach
                    </select>
                </label>
            @endif
            <label class="campo"><span>{{ $usuario->exists ? 'Nova senha (deixe vazio para manter)' : 'Senha inicial (mínimo 8)' }}</span>
                <input type="password" name="password" class="input @error('password') input-erro @enderror" autocomplete="new-password" @required(! $usuario->exists)>
                @error('password')<p class="erro-msg">{{ $message }}</p>@enderror
            </label>
        </div>
        <label class="flex items-center gap-2 text-[15px]">
            <input type="hidden" name="ativo" value="0">
            <input type="checkbox" name="ativo" value="1" class="h-4 w-4 accent-asfalto" @checked(old('ativo', $usuario->ativo))> Acesso ativo
        </label>
        <div class="flex gap-3 pt-2">
            <button class="btn-primario">Salvar</button>
            <a href="{{ route('usuarios.index') }}" class="btn">Cancelar</a>
            @if ($usuario->exists && auth()->user()->can('auditoria.ver'))<a href="{{ route('historico', ['usuario', $usuario->id]) }}" class="btn">Ver histórico</a>@endif
        </div>
    </form>
</x-app>
