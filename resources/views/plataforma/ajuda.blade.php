<h1>Tutorial do administrador</h1>
<p class="mb-4 text-suave">Use esta conta para aprovar oficinas, gerenciar planos e acompanhar as cobranças do TORQUE. Para operar Veículos, Clientes e OS, entre com o usuário da própria oficina.</p>
@include('plataforma._menu')

<div class="space-y-4">
    <section class="card card-corpo">
        <h2>1. Receba e aprove um cadastro</h2>
        <ol class="mt-3 list-decimal space-y-2 pl-5">
            <li>Envie à oficina o <strong>link público de cadastro</strong>. Ela informa os dados, cria a própria senha e aguarda a aprovação.</li>
            <li>Abra <strong>Plataforma → Pendentes</strong> ou clique no cartão <strong>Cadastros aguardando liberação</strong> na tela de Oficinas. O número no cartão indica quantas solicitações aguardam análise.</li>
            <li>Clique em <strong>Revisar cadastro</strong>, confira o nome e os contatos e clique em <strong>Liberar oficina</strong>. Os {{ \App\Models\Empresa::DIAS_TRIAL }} dias de teste começam na liberação.</li>
            <li>Avise a oficina que ela pode entrar com o e-mail e a senha do cadastro. Enquanto o envio de e-mail não estiver configurado, combine esse aviso por outro canal.</li>
        </ol>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm" data-copiar="{{ route('cadastro') }}">Copiar link de cadastro</button>
            <a class="btn btn-sm" href="{{ route('plataforma.index', ['status' => \App\Models\Empresa::CADASTRO_PENDENTE]) }}">Abrir Pendentes</a>
        </div>
    </section>
    <section class="card card-corpo">
        <h2>2. Edite a oficina e acompanhe a assinatura</h2>
        <p class="mt-2">Em <strong>Plataforma → Oficinas → Editar oficina</strong>, confira os dados de contato e ajuste o plano, a situação ou o prazo de teste. O e-mail de contato da oficina é separado do login do responsável.</p>
        <a class="btn btn-sm mt-3" href="{{ route('plataforma.index') }}">Abrir Oficinas</a>
    </section>
    <section class="card card-corpo">
        <h2>3. Compartilhe a cobrança do TORQUE</h2>
        <p class="mt-2">Crie a cobrança por Pix ou boleto no banco ou provedor. Em <strong>Editar oficina → Faturas</strong>, informe valor, vencimento e link. Use <strong>Copiar link</strong> ou <strong>Abrir WhatsApp</strong> para compartilhar. A oficina também encontra a fatura em <strong>Assinatura</strong>.</p>
        <p class="mt-2">Depois de conferir o crédito, use <strong>Registrar pagamento</strong>. Se for preciso liberar ou renovar o acesso, atualize a seção <strong>Assinatura</strong> na ficha da oficina.</p>
    </section>
</div>
