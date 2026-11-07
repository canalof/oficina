# Matriz de permissões

Gerada a partir de `App\Support\Perfil::permissoes()`. Regra: negado por padrão; cada tela confere a permissão no servidor.

| Permissão | Dono | Gerente | Consultor / recepção | Mecânico | Caixa | Estoquista |
|---|---|---|---|---|---|---|
| Ver clientes (`clientes.ver`) | ✓ | ✓ | ✓ | ✓ | ✓ |  |
| Cadastrar/editar clientes (`clientes.editar`) | ✓ | ✓ | ✓ |  | ✓ |  |
| Excluir clientes (`clientes.excluir`) | ✓ | ✓ |  |  |  |  |
| Ver veículos (`veiculos.ver`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Cadastrar/editar veículos (`veiculos.editar`) | ✓ | ✓ | ✓ |  |  |  |
| Excluir veículos (`veiculos.excluir`) | ✓ | ✓ |  |  |  |  |
| Consultar placa (custo) (`placa.consultar`) | ✓ | ✓ | ✓ |  |  |  |
| Usuários (`usuarios.gerenciar`) | ✓ | ✓ |  |  |  |  |
| Dados da empresa, fiscais e assinatura (`empresa.configurar`) | ✓ |  |  |  |  |  |
| Auditoria (`auditoria.ver`) | ✓ | ✓ |  |  |  |  |
| Ver OS (`os.ver`) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Abrir OS, orçamento, aprovação no balcão, entregar (`os.editar`) | ✓ | ✓ | ✓ |  |  |  |
| Diagnóstico, fotos, iniciar e concluir (`os.executar`) | ✓ | ✓ | ✓ | ✓ |  |  |
| Cancelar/reabrir OS, remover item aprovado (`os.cancelar`) | ✓ | ✓ |  |  |  |  |
| Catálogo de serviços (`servicos.gerenciar`) | ✓ | ✓ |  |  |  |  |
| Ver estoque (`estoque.ver`) | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| Produtos e ajustes de estoque (`estoque.editar`) | ✓ | ✓ |  |  |  | ✓ |
| Importar notas de compra e fornecedores (`compras.lancar`) | ✓ | ✓ |  |  |  | ✓ |
| Caixa e recebimentos (`caixa.operar`) | ✓ | ✓ | ✓ |  | ✓ |  |
| Ver contas, resumo e exportar (`financeiro.ver`) | ✓ | ✓ |  |  | ✓ |  |
| Lançar/pagar contas, estornar (`financeiro.editar`) | ✓ | ✓ |  |  | ✓ |  |
| Ver comissões (`comissoes.ver`) | ✓ | ✓ |  |  |  |  |
| Emitir/cancelar notas fiscais (`notas.emitir`) | ✓ | ✓ | ✓ |  | ✓ |  |
| Ver agenda e lembretes (`agenda.ver`) | ✓ | ✓ | ✓ | ✓ | ✓ |  |
| Agendar e enviar lembretes (`agenda.editar`) | ✓ | ✓ | ✓ |  | ✓ |  |

**Administrador da plataforma** (`users.admin_plataforma`): acessa só o painel /plataforma (oficinas, planos, avisos do gateway), com verificação em duas etapas obrigatória. Não vê clientes, OS nem financeiro das oficinas.
