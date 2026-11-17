<section id="conversa-interna" class="card card-corpo mt-6 scroll-mt-16">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2>Conversa interna</h2>
            <p class="text-sm text-suave">Visível apenas para a equipe da oficina. Use a menção para indicar quem precisa agir.</p>
        </div>
        <a href="{{ route('os.show', $os) }}#conversa-interna" class="btn btn-sm">Atualizar conversa</a>
    </div>
    @if ($mensagens->count() === 100)
        <p class="mb-3 text-sm text-suave">Exibindo as 100 mensagens mais recentes.</p>
    @endif
    <ol class="space-y-3">
        @forelse ($mensagens as $mensagem)
            <li class="rounded-md border border-linha p-3">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-semibold">{{ $mensagem->autor?->name ?? 'Usuário removido' }}</span>
                    <time class="text-suave" datetime="{{ $mensagem->created_at->toIso8601String() }}">{{ $mensagem->created_at->format('d/m H:i') }}</time>
                    @if ($perfil = $mensagem->perfil_mencionado)
                        <span @class(['rotulo', 'border-sinal' => $perfil === auth()->user()->perfil, 'border-linha' => $perfil !== auth()->user()->perfil])>{{ '@'.$perfil->rotulo() }}</span>
                    @endif
                </div>
                <p class="mt-1 whitespace-pre-wrap break-words">{{ $mensagem->mensagem }}</p>
            </li>
        @empty
            <li class="text-sm text-suave">Ainda não há mensagens nesta OS.</li>
        @endforelse
    </ol>
    <form method="post" action="{{ route('os.mensagens.store', $os) }}" class="mt-4 space-y-3">
        @csrf
        <label class="campo"><span>Mensagem para a equipe</span>
            <textarea class="input" name="mensagem" rows="3" maxlength="2000" required>{{ old('mensagem') }}</textarea>
        </label>
        @error('mensagem')<p class="erro-msg">{{ $message }}</p>@enderror
        <label class="campo"><span>Mencionar equipe (opcional)</span>
            <select name="perfil_mencionado" class="input max-w-xs">
                <option value="">Sem menção</option>
                @foreach (\App\Support\Perfil::cases() as $perfil)
                    <option value="{{ $perfil->value }}" @selected(old('perfil_mencionado') === $perfil->value)>{{ $perfil->rotulo() }}</option>
                @endforeach
            </select>
        </label>
        @error('perfil_mencionado')<p class="erro-msg">{{ $message }}</p>@enderror
        <button class="btn-primario">Enviar mensagem</button>
    </form>
</section>
