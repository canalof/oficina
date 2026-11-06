<x-app titulo="Usuários">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1>Usuários</h1>
            <p class="text-suave">Cada pessoa tem o próprio login. O perfil define o que ela pode ver e alterar.</p>
        </div>
        <a href="{{ route('usuarios.create') }}" class="btn-primario">Novo usuário</a>
    </div>
    <div class="card overflow-x-auto">
        <table class="tabela">
            <thead><tr><th>Nome</th><th class="hidden sm:table-cell">E-mail</th><th>Perfil</th><th class="hidden md:table-cell">Último acesso</th><th></th></tr></thead>
            <tbody>
            @foreach ($usuarios as $u)
                <tr @class(['text-suave' => ! $u->ativo])>
                    <td class="font-semibold">{{ $u->name }} @unless($u->ativo)<span class="rotulo ml-1 border-linha text-suave">inativo</span>@endunless</td>
                    <td class="hidden sm:table-cell">{{ $u->email }}</td>
                    <td>{{ $u->perfil->rotulo() }}</td>
                    <td class="hidden md:table-cell">{{ $u->ultimo_acesso_em?->diffForHumans() ?? 'nunca entrou' }}</td>
                    <td class="text-right"><a href="{{ route('usuarios.edit', $u->id) }}" class="btn btn-sm">Editar</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <section class="card card-corpo mt-6">
        <h2 class="mb-3">O que cada perfil pode fazer</h2>
        <dl class="grid gap-3 text-[15px] sm:grid-cols-2">
            <div><dt class="font-semibold">Dono</dt><dd class="text-suave">Tudo, inclusive dados da empresa.</dd></div>
            <div><dt class="font-semibold">Gerente</dt><dd class="text-suave">Tudo, menos os dados da empresa: OS, cancelamentos, catálogo de serviços e usuários.</dd></div>
            <div><dt class="font-semibold">Consultor / recepção</dt><dd class="text-suave">Recebe carros, abre OS, monta e envia orçamentos, consulta placa.</dd></div>
            <div><dt class="font-semibold">Mecânico</dt><dd class="text-suave">Vê as OS, registra diagnóstico e fotos, inicia e conclui serviços.</dd></div>
            <div><dt class="font-semibold">Caixa</dt><dd class="text-suave">Consulta e atualiza clientes; vê OS (o caixa entra no módulo financeiro).</dd></div>
            <div><dt class="font-semibold">Estoquista</dt><dd class="text-suave">Vê veículos e OS (estoque entra no módulo de estoque).</dd></div>
        </dl>
    </section>
</x-app>
