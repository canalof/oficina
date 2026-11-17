<x-app titulo="Tutorial">
    @can('plataforma')
        @include('plataforma.ajuda')
    @else
    <h1>Tutorial do TORQUE</h1>
    <p class="mb-4 text-suave">Guia da equipe da oficina, do primeiro acesso ao fechamento do caixa.</p>
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Tópicos do tutorial">
        <a class="btn btn-sm" href="#primeiro-acesso">Primeiro acesso e equipe</a>
        <a class="btn btn-sm" href="#agenda">Agenda e lembretes</a>
        <a class="btn btn-sm" href="#atendimento">Atendimento</a>
        <a class="btn btn-sm" href="#caixa">Caixa</a>
        <a class="btn btn-sm" href="#produtos">Produtos e estoque</a>
        <a class="btn btn-sm" href="#bancos">Bancos</a>
        <a class="btn btn-sm" href="#pix">Pix manual</a>
        <a class="btn btn-sm" href="#conciliacao">Conciliação</a>
        <a class="btn btn-sm" href="#notas-fiscais">Notas fiscais</a>
        <a class="btn btn-sm" href="#instalar-app">App no celular</a>
        <a class="btn btn-sm" href="#assinatura-suporte">Assinatura e suporte</a>
    </nav>

    <div class="mb-5 space-y-4">
        <section id="primeiro-acesso" class="card card-corpo">
            <h2>Primeiro acesso e equipe</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Preencha o cadastro público da oficina e aguarde a liberação pelo administrador do TORQUE. Depois, entre com o e-mail e a senha escolhidos no cadastro.</li>
                <li>Na conta da oficina, abra <strong>Empresa</strong> para conferir identificação, endereço, contato e regras como capacidade da Agenda.</li>
                <li>Em <strong>Usuários → Novo usuário</strong>, informe nome, e-mail e senha inicial. Escolha o perfil adequado, como <strong>Consultor / recepção</strong>, <strong>Mecânico</strong>, <strong>Caixa</strong> ou <strong>Estoquista</strong>, e mantenha <strong>Acesso ativo</strong> marcado para quem precisa entrar.</li>
                <li>Use uma conta por pessoa. Para trocar o próprio acesso, use <strong>Senha</strong> no menu. Para alterar o perfil ou desativar alguém, use <strong>Usuários → Editar</strong>.</li>
            </ol>
            @can('usuarios.gerenciar')<a class="btn btn-sm mt-3" href="{{ route('usuarios.index') }}">Abrir Usuários</a>@endcan
        </section>
        <section id="agenda" class="card card-corpo">
            <h2>Agenda e lembretes</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Em <strong>Agenda</strong>, clique em <strong>+ Agendar</strong> no dia desejado. Selecione um cliente ou escreva o nome de quem ainda não tem cadastro. Informe serviço, data, hora e duração.</li>
                <li>Use <strong>Confirmou</strong> quando o cliente confirmar, <strong>Chegou</strong> ao receber o veículo e <strong>Não veio</strong> para registrar a falta. Se o veículo já estiver vinculado, use <strong>Chegou: abrir OS</strong> para abrir a ordem.</li>
                <li>Em <strong>Catálogo de serviços</strong>, informe o prazo de revisão nos serviços que precisam de retorno. Ao entregar o veículo, o TORQUE gera o lembrete para os serviços aplicáveis.</li>
                <li>Em <strong>Lembretes de revisão</strong>, use <strong>Agendar</strong> para marcar o retorno. Se usar WhatsApp manual, abra a mensagem, envie ao cliente que autorizou o contato e só então clique em <strong>Confirmar envio</strong>.</li>
            </ol>
            @can('agenda.ver')
                <div class="mt-3 flex flex-wrap gap-2">
                    <a class="btn btn-sm" href="{{ route('agenda.index') }}">Abrir Agenda</a>
                    <a class="btn btn-sm" href="{{ route('lembretes.index') }}">Ver lembretes</a>
                </div>
            @endcan
        </section>
    </div>
    <ol class="space-y-4">
        <li id="atendimento" class="card card-corpo">
            <h2>1. Receba o veículo</h2>
            <p class="mt-2">Na Recepção, toque em <strong>Fotografar placa</strong> ou digite a placa. Confira o texto e toque em <strong>Buscar placa</strong>. Se o veículo já estiver cadastrado, a ficha mostra o cliente vinculado, o último km e as visitas recentes.</p>
            <p class="mt-2">Na primeira visita, você pode começar em <strong>Recepção → Cadastrar novo cliente</strong>, sem informar placa. Clique em <strong>Salvar e cadastrar veículo</strong> para informar a placa e vincular o veículo ao cliente. Em seguida, atualize o km e abra a ordem de serviço. Para corrigir os dados depois, use <strong>Clientes → Editar</strong>.</p>
            <p class="mt-2">Para cadastrar um carro diretamente, abra <strong>Veículos → Novo veículo</strong>. Informe a placa, selecione o cliente e preencha marca, modelo, versão, motor, ano de fabricação e ano do modelo conforme o documento. As sugestões ajudam a preencher; você também pode digitar valores próprios.</p>
            @can('veiculos.ver')<a class="btn btn-sm mt-3" href="{{ route('recepcao') }}">Abrir Recepção</a>@endcan
        </li>
        <li class="card card-corpo">
            <h2>2. Monte e aprove o orçamento</h2>
            <p class="mt-2">Na OS, descreva o problema e adicione peças e serviços com quantidade e valor. Envie o link do orçamento ao cliente ou registre a aprovação no balcão. Confira quais itens foram aprovados antes de iniciar.</p>
            <p class="mt-2">Se o cliente lembrar de algo durante o atendimento, use <strong>Editar OS</strong> para atualizar o relato e as observações. Para cobrar uma peça ou um serviço extra, use <strong>Adicionar peça ou serviço</strong> na OS e registre a aprovação do novo item antes de concluir.</p>
            <p class="mt-2">Use <strong>Conversa interna</strong> na OS para combinar ações entre mecânica, recepção e caixa. Você pode mencionar um perfil da equipe. A lista de OS indica mensagens novas e a conversa tem o botão <strong>Atualizar conversa</strong>.</p>
            <p class="mt-2">Em <strong>Catálogo de serviços</strong>, use <strong>Categorias de serviços</strong> para organizar os serviços. Em <strong>Modelos de OS</strong>, salve combinações de peças e serviços. Na OS, clique em <strong>Aplicar modelo de OS</strong> e confira os itens, as quantidades e os valores antes da aprovação.</p>
            @can('os.ver')<a class="btn btn-sm mt-3" href="{{ route('os.index') }}">Ver ordens de serviço</a>@endcan
        </li>
        <li class="card card-corpo">
            <h2>3. Conclua, receba e entregue</h2>
            <p class="mt-2">Marque a OS como pronta quando terminar. Registre o pagamento ou a condição a prazo e confirme a entrega. Consulte o histórico na ficha do veículo quando ele voltar.</p>
            <p class="mt-2">Em <strong>Inspeção visual</strong> na OS, registre o componente, a situação e a explicação para o cliente. Você pode associar uma foto enviada na OS e um item do orçamento. Informe data ou quilometragem para trazer a recomendação ao histórico do veículo.</p>
            <p class="mt-2">Em <strong>Laudo de alinhamento e pneus</strong>, registre as medidas antes e depois, o sulco por roda e as recomendações. Marque <strong>Publicar no portal do cliente</strong> quando estiver pronto. Se adicionar itens a uma OS já aprovada, o link separa os itens novos dos já autorizados e mostra a previsão atual de entrega.</p>
            <p class="mt-2">Em um achado marcado para acompanhar ou com atenção imediata, informe a data de contato e clique em <strong>Adicionar à fila de revisões</strong>. Em <strong>Lembretes de revisão</strong>, a equipe pode agendar ou enviar uma mensagem ao cliente que autorizou avisos.</p>
            <p class="mt-2">Em uma OS entregue, use <strong>Garantias e devoluções</strong> para registrar peça ou serviço, fornecedor, prazo e resolução. Em um cliente pessoa jurídica, abra <strong>Extrato mensal da frota</strong> para conferir as OS entregues por placa e valor.</p>
            <p class="mt-2">Na OS, abra <strong>Compras e serviços terceirizados</strong>. Compare cotações de peças aprovadas e vincule o item da nota depois da entrada por XML. Para retífica e outros parceiros, informe custo, responsável, prazo e situação do retorno.</p>
            <p class="mt-2">Em <strong>Garantias e devoluções</strong>, crie a OS de retorno a partir da ocorrência. Registre cortesias com valor zero e envie itens cobrados para aprovação. Use a <strong>Ficha de troca de óleo</strong> para guardar especificação, volume, filtro e quilometragem no histórico do veículo.</p>
            <p class="mt-2">O cliente pode solicitar um horário no portal. A recepção responde em <strong>Agenda → Solicitações do portal</strong> e pode criar a OS vinculada ao agendamento na chegada.</p>
            <p class="mt-2">Na retirada, use o botão <strong>Retirada</strong> na OS para registrar o condutor e a autorização antes de entregar o veículo.</p>
            <p class="mt-2">Para compartilhar o acompanhamento, clique em <strong>Portal do cliente</strong> na OS ou na ficha em <strong>Clientes</strong>. Clique em <strong>Criar link do portal</strong> e copie ou envie o link pelo WhatsApp, quando disponível. O cliente abre o link sem senha para ver os veículos, o andamento, os orçamentos disponíveis, as garantias e as próximas revisões. Envie apenas ao titular e use <strong>Desativar</strong> se precisar revogar o acesso.</p>
            @can('caixa.operar')<a class="btn btn-sm mt-3" href="{{ route('caixa.index') }}">Abrir Caixa</a>@endcan
        </li>
        <li id="mobilidade" class="card card-corpo">
            <h2>Condutores, CNH e retirada</h2>
            <ol class="mt-2 list-decimal space-y-2 pl-5">
                <li>Em <strong>Condutores e retirada</strong>, cadastre a pessoa e abra <strong>Consulta e Verificação de CNH</strong>. Fotografe ou selecione a imagem. O navegador tenta ler os dados e detectar o QR. Compare com o documento e registre validade, categoria e resultado da conferência.</li>
                <li>A leitura local auxilia a comparação dos dados. Autenticidade e situação atual do direito de dirigir aparecem separadamente. Se a oficina exigir confirmação oficial, a liberação fica bloqueada enquanto a integração estiver indisponível.</li>
                <li>Para retirada, clique em <strong>Retirada</strong> na OS, selecione o condutor e registre o aceite. Quando for um terceiro, anote a autorização do responsável. Conclua a entrega pela OS.</li>
            </ol>
        </li>
        @can('locacao.ver')
        <li id="locacao" class="card card-corpo">
            <h2>Locação: carro reserva e aluguel</h2>
            <ol class="mt-2 list-decimal space-y-2 pl-5">
                <li>Ligue o módulo em <strong>Ajustes › Empresa e módulos › Módulos</strong>. Desligado, nada de Locação aparece no sistema.</li>
                <li>Em <strong>Locação → Reservas e frota</strong>, cadastre os carros da frota.</li>
                <li><strong>Carro reserva</strong>: para o cliente que deixou o carro na oficina. Escolha a OS (ou clique em <strong>Carro reserva</strong> dentro da OS). Pode ser carro da frota ou de locadora parceira, cobrado ou cortesia.</li>
                <li><strong>Aluguel</strong>: aluguel comum, sem OS. Escolha o cliente e um carro da frota.</li>
                <li>Registre quilometragem, combustível, vistoria e fotos na saída e na devolução. Na devolução, diárias e taxas viram um título em <strong>Contas a receber</strong>, na categoria Locação, separado da oficina no <strong>Resumo do mês</strong>. A caução é registrada à parte.</li>
                <li>Em <strong>Contratos e vistorias</strong>, o dono ou gerente edita os modelos. Gere o contrato dentro da reserva e use <strong>Imprimir / salvar PDF</strong>.</li>
            </ol>
        </li>
        @endcan

        <li id="produtos" class="card card-corpo">
            <h2>4. Cadastre produtos e controle o estoque</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Abra <strong>Estoque → Produtos → Novo produto</strong>. Informe um nome que identifique a peça, por exemplo, <strong>Filtro de óleo — modelo X</strong>. A oficina já recebe categorias como <strong>Filtros</strong> e <strong>Freios</strong>. Para criar outra, abra <strong>Produtos → Categorias</strong>, informe o nome e clique em <strong>Criar categoria</strong>. Nessa tela você também pode renomear ou desativar categorias. Para cadastrar uma marca, use <strong>Fabricantes</strong>.</li>
                <li>Escolha a <strong>Unidade</strong>, como unidade ou litro. Preencha <strong>Código interno</strong>, <strong>Código de barras</strong>, referência do fabricante, localização e NCM quando aplicável. Na emissão fiscal, confira o NCM com a contabilidade.</li>
                <li>Informe <strong>Custo médio</strong> pelo valor de compra e <strong>Preço de venda</strong> pelo que será cobrado. Em <strong>Quantidade em estoque agora</strong>, coloque o saldo físico inicial. Use <strong>Estoque mínimo</strong> para sinalizar a hora de repor.</li>
                <li>Clique em <strong>Cadastrar produto</strong>. Abra a ficha para conferir o saldo e as movimentações. Para entrada, perda, devolução ou inventário, use <strong>Mexer no estoque</strong> e informe o motivo. Em uma contagem, informe o total encontrado na prateleira.</li>
            </ol>
            <p class="mt-3 text-suave"><strong>Exemplo:</strong> um filtro custa R$ 25,00 e é vendido por R$ 45,00. Se há 10 filtros e você quer repor ao chegar em 3, cadastre estoque inicial 10 e mínimo 3.</p>
            <p class="mt-2 text-suave"><strong>Leitor de código de barras:</strong> toque em <strong>Escanear código</strong> na busca ou no cadastro de Produtos e fotografe a etiqueta inteira, sem reflexo. Confira o código preenchido e clique em <strong>Buscar</strong> ou salve o produto. Se usar um leitor USB ou Bluetooth que funciona como teclado, coloque o cursor no campo antes de escanear.</p>
            <p class="mt-2 text-suave">Para cadastrar vários produtos, use <strong>Importar</strong> e baixe o modelo CSV. Para dar entrada com uma nota de compra, use <strong>Entrada por XML</strong>, confira os itens e vincule os produtos existentes antes de lançar no estoque.</p>
            <p class="mt-2">Para listar peças equivalentes, coloque o mesmo <strong>Grupo de equivalência</strong> nos produtos compatíveis com a mesma unidade. A ficha do produto mostra as opções, o saldo e o preço. Para uma troca de óleo, use <strong>Modelos de OS</strong> para reunir óleo, filtro e mão de obra.</p>
            @can('estoque.ver')<a class="btn btn-sm mt-3" href="{{ route('produtos.index') }}">Abrir Produtos</a>@endcan
        </li>
        <li id="bancos" class="card card-corpo">
            <h2>5. Cadastre a conta bancária da oficina</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Abra <strong>Bancos</strong>. Em <strong>Cadastrar conta</strong>, informe o <strong>Apelido</strong>, como <strong>Conta principal</strong>, e o nome do <strong>Banco</strong>.</li>
                <li>Preencha <strong>Código do banco</strong>, <strong>Agência</strong> e <strong>Número da conta</strong> conforme os dados do banco, quando aplicáveis. Selecione <strong>Corrente</strong>, <strong>Poupança</strong> ou <strong>Pagamento</strong> em <strong>Tipo de conta</strong>.</li>
                <li>Clique em <strong>Cadastrar conta</strong> e confira a conta na lista. Para receber por Pix nessa conta, siga a configuração abaixo.</li>
            </ol>
            <p class="mt-3 text-suave">O cadastro identifica a conta no TORQUE. Ele serve de base para registrar movimentos, importar extratos e configurar a chave Pix.</p>
            @can('financeiro.ver')<a class="btn btn-sm mt-3" href="{{ route('bancos.index') }}">Abrir Bancos</a>@endcan
        </li>
        <li id="pix" class="card card-corpo">
            <h2>6. Configure, gere e confirme um Pix manual</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Em <strong>Bancos</strong>, abra a conta que vai receber e clique em <strong>Configurar Pix manual</strong>. Escolha o tipo de chave: CPF, CNPJ, celular, e-mail ou aleatória. Digite a chave já cadastrada no banco. Para celular, use o formato internacional, como <strong>+55</strong> seguido de DDD e número.</li>
                <li>Informe o <strong>Nome do recebedor</strong> e a <strong>Cidade</strong>. Clique em <strong>Salvar Pix manual</strong>. A conta vai aparecer como configurada.</li>
                <li>Confira se a cobrança está em <strong>Contas a receber → Em aberto</strong>. Abra <strong>Pix</strong>, selecione a <strong>Conta bancária</strong> e a <strong>Conta a receber</strong> e clique em <strong>Preparar cobrança</strong>.</li>
                <li>Na lista de cobranças, abra <strong>Ver QR Code e Pix Copia e Cola</strong>. Antes do pagamento, confira no aplicativo do banco o recebedor e o valor.</li>
                <li>Depois que o crédito aparecer no extrato da oficina, clique em <strong>Confirmar recebimento</strong>. O TORQUE baixa o título em <strong>Contas a receber</strong> e marca a cobrança como confirmada.</li>
            </ol>
            <p class="mt-3 text-suave">A baixa do Pix manual depende da sua conferência no banco. Se você cancelar a preparação no TORQUE, um QR Code já compartilhado ainda pode ser pago no banco.</p>
            @can('financeiro.ver')
                <div class="mt-3 flex flex-wrap gap-2">
                    <a class="btn btn-sm" href="{{ route('bancos.index') }}">Configurar chave</a>
                    <a class="btn btn-sm" href="{{ route('receber.index') }}">Ver contas a receber</a>
                    <a class="btn btn-sm" href="{{ route('pix.index') }}">Abrir Pix</a>
                </div>
            @endcan
        </li>
        <li id="conciliacao" class="card card-corpo">
            <h2>7. Importe e concilie o extrato</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Exporte o extrato no aplicativo do banco. Em <strong>Bancos</strong>, abra a conta correspondente, expanda <strong>Importar extrato</strong> e selecione o arquivo em <strong>Importar OFX</strong> ou <strong>Importar CSV</strong>.</li>
                <li>Se usar CSV, separe as colunas por ponto e vírgula e use o cabeçalho <code>data;descricao;valor_centavos;referencia</code>. Use data no formato <code>AAAA-MM-DD</code> e uma referência única por movimento. Por exemplo, R$ 150,00 de entrada é <code>15000</code> e R$ 25,00 de saída é <code>-2500</code>.</li>
                <li>Em <strong>Últimos movimentos</strong>, abra <strong>Conciliar</strong> no lançamento. Escolha uma sugestão ou digite o ID do título já recebido ou pago. Confira se o valor do título bate com o do extrato e clique em <strong>Conciliar</strong>.</li>
                <li>Para corrigir um vínculo, clique em <strong>Desfazer vínculo</strong>. A conciliação vincula o extrato ao título já liquidado. O pagamento ou recebimento é registrado no financeiro antes dessa etapa.</li>
            </ol>
            @can('financeiro.ver')<a class="btn btn-sm mt-3" href="{{ route('bancos.index') }}">Ver movimentos bancários</a>@endcan
        </li>
        <li id="notas-fiscais" class="card card-corpo">
            <h2>8. Configure e emita notas fiscais</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Em <strong>Empresa → Dados fiscais</strong>, confira com a contabilidade o CNPJ, a razão social, o endereço, a inscrição municipal, o código IBGE e o regime tributário. Para a primeira oficina, em <strong>Hortolândia/SP</strong>, o código IBGE é <strong>3519071</strong>. Use o código e as regras da cidade da própria oficina nos próximos cadastros.</li>
                <li>Confirme o emissor aplicável ao CNPJ. Em Hortolândia, o portal municipal é o <strong>Giss Online V2</strong> para quem emite pelo município. Para microempresas e empresas de pequeno porte do Simples Nacional, a NFS-e deve ser emitida pelo <strong>Emissor Nacional</strong> desde 1º de novembro de 2026. A integração atual do TORQUE usa o fluxo municipal da Focus NFe. Enquanto o fluxo aplicável à oficina não estiver homologado, emita a nota no portal correspondente.</li>
                <li>Quando o fluxo municipal for o correto, confirme o credenciamento, certificado digital e os códigos de serviço com a contabilidade e o provedor. Cadastre o token da Focus em <strong>Empresa → Dados fiscais</strong> e comece pelo ambiente <strong>Homologação</strong>. A demonstração e a homologação servem para teste.</li>
                <li>Na OS, confira o cadastro do cliente e os itens aprovados. Registre o recebimento e abra <strong>Notas fiscais</strong> na OS. Use <strong>Emitir NFS-e (serviços)</strong> para os serviços. Para peças, use NFC-e ou NF-e conforme a orientação fiscal da oficina e confira o NCM dos produtos.</li>
                <li>Confira o retorno em <strong>Notas fiscais</strong>. Se estiver processando, use <strong>Atualizar situação</strong>. Se houver rejeição, revise a mensagem antes de tentar novamente. Quando disponível, consulte o PDF ou XML da nota autorizada.</li>
            </ol>
            <div class="mt-3 flex flex-wrap gap-2">
                @can('empresa.configurar')<a class="btn btn-sm" href="{{ route('empresa.edit') }}">Abrir dados da empresa</a>@endcan
                @can('notas.emitir')<a class="btn btn-sm" href="{{ route('notas.index') }}">Abrir Notas fiscais</a>@endcan
                <a class="btn btn-sm" href="https://www.hortolandia.sp.gov.br/nfse/" target="_blank" rel="noopener">NFS-e de Hortolândia</a>
                <a class="btn btn-sm" href="https://www.gov.br/nfse/pt-br" target="_blank" rel="noopener">Portal Nacional da NFS-e</a>
            </div>
        </li>
        <li id="instalar-app" class="card card-corpo">
            <h2>9. Instale o TORQUE no celular</h2>
            <p class="mt-2">No Android, abra o TORQUE pelo Chrome e use <strong>Instalar app</strong> ou <strong>Adicionar à tela inicial</strong>. No iPhone, abra pelo Safari e use <strong>Compartilhar → Adicionar à Tela de Início</strong>.</p>
            <p class="mt-2">Depois, abra pelo ícone do TORQUE e entre com a conta da oficina. Mantenha a internet ativa para buscar dados e salvar o atendimento.</p>
            <a class="btn btn-sm mt-3" href="{{ route('instalar') }}">Abrir instruções de instalação</a>
        </li>
        <li id="caixa" class="card card-corpo">
            <h2>10. Abra, confira e feche o caixa</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5">
                <li>Em <strong>Caixa</strong>, informe o <strong>Troco inicial</strong> e clique em <strong>Abrir caixa</strong>. Para registrar um recebimento em dinheiro, o caixa precisa estar aberto.</li>
                <li>Durante o dia, confira <strong>Movimentações deste caixa</strong> e os totais por forma de pagamento. Em <strong>Reforço ou retirada</strong>, use <strong>Colocar troco</strong> ou <strong>Retirar dinheiro</strong>, com valor e motivo.</li>
                <li>No fim do turno, conte o dinheiro físico da gaveta. Informe o <strong>Dinheiro contado</strong>, registre uma observação se houver diferença e clique em <strong>Fechar caixa</strong>. O histórico mostra o esperado, o contado e a diferença.</li>
            </ol>
            @can('caixa.operar')<a class="btn btn-sm mt-3" href="{{ route('caixa.index') }}">Abrir Caixa</a>@endcan
        </li>
        <li id="assinatura-suporte" class="card card-corpo">
            <h2>11. Assinatura e ajuda com o acesso</h2>
            <p class="mt-2">Em <strong>Assinatura</strong>, o responsável consulta plano, situação, prazo de teste e faturas. Quando houver fatura aberta, use <strong>Pagar</strong> para abrir o link do banco ou provedor. Se o pagamento for confirmado manualmente, fale com o administrador do TORQUE para acompanhar a liberação.</p>
            <p class="mt-2">Se alguém da equipe esquecer a senha, o responsável pode definir uma nova em <strong>Usuários → Editar</strong>. Se quem perdeu o acesso foi o próprio responsável e o e-mail de recuperação não chegar, contate o administrador do TORQUE pelo canal combinado com a oficina.</p>
            @can('empresa.configurar')<a class="btn btn-sm mt-3" href="{{ route('assinatura') }}">Abrir Assinatura</a>@endcan
        </li>
    </ol>
    @endcan
</x-app>
