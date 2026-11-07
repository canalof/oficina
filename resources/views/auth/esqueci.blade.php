<x-pagina-simples titulo="Esqueci a senha">
    <h1 class="mb-1">Esqueci a senha</h1>
    <p class="mb-6 text-suave">Digite o e-mail de acesso. Mandamos um link para você criar uma senha nova.</p>
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-campo nome="email" rotulo="E-mail" tipo="email" required autofocus />
        <button class="btn-primario w-full py-3">Enviar link</button>
    </form>
    <a href="{{ route('login') }}" class="mt-5 text-[15px] font-semibold text-asfalto-3 hover:underline">Voltar para entrar</a>
</x-pagina-simples>
