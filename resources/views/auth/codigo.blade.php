<x-pagina-simples titulo="Código de verificação">
    <h1 class="mb-1">Código de verificação</h1>
    <p class="mb-6 text-suave">Abra o aplicativo autenticador no celular e digite o código de 6 números. Sem o celular, use um dos códigos de recuperação.</p>
    <form method="post" action="{{ route('login.codigo.verificar') }}" class="space-y-4">
        @csrf
        <label class="campo"><span>Código</span>
            <input name="codigo" class="input text-center text-2xl tracking-[0.3em] @error('codigo') input-erro @enderror" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required autofocus>
        </label>
        @error('codigo')<p class="erro-msg">{{ $message }}</p>@enderror
        <button class="btn-primario w-full py-3">Confirmar</button>
    </form>
</x-pagina-simples>
