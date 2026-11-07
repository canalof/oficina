<x-app titulo="Segurança da conta">
    <h1 class="mb-1">Segurança da conta</h1>
    <p class="mb-5 text-suave">A verificação em duas etapas pede, além da senha, um código do aplicativo no seu celular. Recomendado para quem é dono ou gerente.</p>

    @if ($codigos)
        <section class="card card-corpo mb-6 max-w-2xl border-2 border-sinal">
            <h2 class="mb-1">Guarde estes códigos de recuperação</h2>
            <p class="mb-3 text-sm text-suave">Cada um funciona uma vez, se você perder o celular. Eles não aparecem de novo.</p>
            <ul class="grid grid-cols-2 gap-2 font-mono text-[15px]">@foreach ($codigos as $c)<li class="rounded bg-fundo px-3 py-1.5">{{ chunk_split($c, 5, ' ') }}</li>@endforeach</ul>
        </section>
    @endif

    <section class="card card-corpo max-w-2xl">
        @if ($user->doisFatoresAtivo())
            <p class="text-[15px]"><span class="rotulo border-[#b7dcc6] bg-[#eef8f2] text-ok">Ativa</span> desde {{ $user->dois_fatores_ativo_em->format('d/m/Y') }}. Restam {{ count($user->dois_fatores_recuperacao ?? []) }} códigos de recuperação.</p>
            @unless ($user->exigeDoisFatores())
                <form method="post" action="{{ route('seguranca.desativar') }}" class="mt-5 flex flex-wrap items-end gap-3" data-confirmar="Desativar a verificação em duas etapas?">
                    @csrf
                    <label class="campo"><span>Sua senha, para confirmar</span><input type="password" name="senha" class="input" required></label>
                    <button class="btn-perigo">Desativar</button>
                </form>
                @error('senha')<p class="erro-msg">{{ $message }}</p>@enderror
            @endunless
        @else
            <ol class="space-y-4 text-[15px]">
                <li><strong>1.</strong> Instale um aplicativo autenticador no celular (Google Authenticator, Microsoft Authenticator ou Authy).</li>
                <li><strong>2.</strong> No aplicativo, escaneie este QR code:
                    <div class="mt-3 flex flex-wrap items-center gap-5">
                        <canvas data-qrcode="{{ $url }}" class="rounded border border-linha bg-white p-2" width="180" height="180" aria-label="QR code para o aplicativo autenticador"></canvas>
                        <div class="text-sm text-suave">Ou digite a chave:<br><span class="select-all break-all font-mono text-base text-texto">{{ chunk_split($segredo, 4, ' ') }}</span></div>
                    </div>
                </li>
                <li><strong>3.</strong> Digite o código que aparece no aplicativo:
                    <form method="post" action="{{ route('seguranca.ativar') }}" class="mt-2 flex flex-wrap items-end gap-3">
                        @csrf
                        <input name="codigo" class="input w-40 text-center text-xl tracking-[0.25em]" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required aria-label="Código de 6 números">
                        <button class="btn-primario">Ativar</button>
                    </form>
                    @error('codigo')<p class="erro-msg">{{ $message }}</p>@enderror
                </li>
            </ol>
        @endif
    </section>
</x-app>
