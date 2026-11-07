<x-pagina-simples titulo="Teste grátis">
    <h1 class="mb-1">Teste o Torque por 14 dias</h1>
    <p class="mb-6 text-suave">Sem cartão. Ao fim do teste, escolha o plano para continuar.</p>
    <form method="post" action="{{ route('cadastro.store') }}" class="space-y-4">
        @csrf
        <x-campo nome="oficina" rotulo="Nome da oficina" required autofocus />
        <x-campo nome="cnpj" rotulo="CNPJ (opcional)" mascara="documento" />
        <x-campo nome="nome" rotulo="Seu nome" required autocomplete="name" />
        <x-campo nome="telefone" rotulo="WhatsApp" mascara="telefone" required />
        <x-campo nome="email" rotulo="E-mail de acesso" tipo="email" required autocomplete="username" />
        <label class="campo"><span>Senha (mínimo 8)</span><input type="password" name="password" class="input @error('password') input-erro @enderror" required autocomplete="new-password"></label>
        @error('password')<p class="erro-msg">{{ $message }}</p>@enderror
        <label class="campo"><span>Repita a senha</span><input type="password" name="password_confirmation" class="input" required autocomplete="new-password"></label>
        <fieldset>
            <legend class="mb-1.5 text-sm font-medium text-suave">Plano depois do teste</legend>
            <div class="space-y-2">
                @foreach ($planos as $p)
                    <label class="flex cursor-pointer items-center gap-3 rounded-md border border-linha bg-white px-3 py-2.5 has-[:checked]:border-asfalto">
                        <input type="radio" name="plano" value="{{ $p->slug }}" class="h-4 w-4 accent-asfalto" @checked(old('plano', $escolhido) === $p->slug)>
                        <span class="flex-1"><span class="font-semibold">{{ $p->nome }}</span> <span class="text-sm text-suave">· {{ $p->limiteTexto($p->limite_usuarios, 'usuários') }} · {{ $p->limite_consultas_placa }} placas/mês{{ $p->whatsapp_automatico ? ' · WhatsApp automático' : '' }}</span></span>
                        <span class="font-semibold">{{ $p->precoFormatado() }}<span class="text-xs font-normal text-suave">/mês</span></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <label class="flex items-start gap-2 text-[15px]"><input type="checkbox" name="aceite" value="1" class="mt-1 h-4 w-4 accent-asfalto" @checked(old('aceite'))> <span>Li e aceito os termos de uso e a política de privacidade.</span></label>
        @error('aceite')<p class="erro-msg">{{ $message }}</p>@enderror
        <button class="btn-primario w-full py-3">Começar o teste grátis</button>
    </form>
    <a href="{{ route('login') }}" class="mt-5 text-[15px] font-semibold text-asfalto-3 hover:underline">Já tenho acesso</a>
</x-pagina-simples>
