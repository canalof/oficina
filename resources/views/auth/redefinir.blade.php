<x-pagina-simples titulo="Criar senha nova">
    <h1 class="mb-6">Criar senha nova</h1>
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-campo nome="email" rotulo="E-mail" tipo="email" :valor="$email" required />
        <label class="campo"><span>Senha nova (mínimo 8)</span><input type="password" name="password" class="input @error('password') input-erro @enderror" required autocomplete="new-password"></label>
        @error('password')<p class="erro-msg">{{ $message }}</p>@enderror
        <label class="campo"><span>Repita a senha nova</span><input type="password" name="password_confirmation" class="input" required autocomplete="new-password"></label>
        <button class="btn-primario w-full py-3">Salvar senha</button>
    </form>
</x-pagina-simples>
