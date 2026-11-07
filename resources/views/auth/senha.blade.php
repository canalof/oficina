<x-app titulo="Trocar senha">
    <h1 class="mb-6">Trocar senha</h1>
    <form method="post" action="{{ route('senha.salvar') }}" class="card card-corpo max-w-md space-y-4">
        @csrf @method('put')
        <label class="campo"><span>Senha atual</span><input type="password" name="senha_atual" class="input" autocomplete="current-password" required>@error('senha_atual')<p class="erro-msg">{{ $message }}</p>@enderror</label>
        <label class="campo"><span>Nova senha (mínimo 8 caracteres)</span><input type="password" name="password" class="input" autocomplete="new-password" required>@error('password')<p class="erro-msg">{{ $message }}</p>@enderror</label>
        <label class="campo"><span>Repita a nova senha</span><input type="password" name="password_confirmation" class="input" autocomplete="new-password" required></label>
        <button class="btn-primario">Salvar nova senha</button>
    </form>
</x-app>
