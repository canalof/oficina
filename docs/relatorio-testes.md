# Relatório de testes automatizados

Executado em 06/11/2026 23:30 com PHP 8.3.6, Laravel 13.40.0, banco SQLite em memória.

Comando: `vendor/bin/phpunit --testdox`

**Resultado: 104 testes, 616 verificações, todos aprovados.**

## O que estes testes NÃO comprovam

- Integrações reais: Focus NFe, Asaas, Evolution API e consulta de placa foram testadas com respostas simuladas (Http::fake). O formato segue a documentação pública de cada fornecedor e precisa ser validado em homologação com credenciais reais.
- MySQL: os testes rodam em SQLite. Rode `php artisan migrate` num MySQL de homologação antes da produção.
- Carga, concorrência real entre servidores e restauração de backup.

## Lista

```
Acesso (Tests\Feature\Acesso)
 ✔ Visitante vai para o login
 ✔ Login com senha certa e errada
 ✔ Usuario inativo nao entra
 ✔ Empresa desativada derruba a sessao
 ✔ Mecanico nao gerencia usuarios nem empresa
 ✔ Dono nao pode tirar o proprio perfil de dono
 ✔ Dono nao edita usuario de outra empresa
 ✔ Paginas principais abrem

Cadastros Estoque (Tests\Feature\CadastrosEstoque)
 ✔ Renavam confere digito
 ✔ Veiculo guarda motor e recusa renavam invalido
 ✔ Corrigir valor de item aprovado volta para aprovacao
 ✔ Nao corrige item de outra empresa
 ✔ Historico mostra antes e depois e respeita permissao e empresa
 ✔ Categorias e fabricantes por empresa
 ✔ Ajuste exige motivo e guarda saldo anterior
 ✔ Custo medio ponderado e historico de preco
 ✔ Avisa cliente duplicado e permite confirmar
 ✔ Importa clientes atualiza pelo documento e aponta erros
 ✔ Importa produtos com estoque inicial e cria fabricante
 ✔ Exporta so da empresa e neutraliza formulas

Cliente Veiculo (Tests\Feature\ClienteVeiculo)
 ✔ Cadastra cliente com cpf valido e guarda so numeros
 ✔ Rejeita cpf invalido e duplicado na mesma empresa
 ✔ Placa ja cadastrada no formato antigo bloqueia a versao mercosul
 ✔ Cadastra veiculo normalizando a placa
 ✔ Nao aceita cliente de outra empresa como dono
 ✔ Transferir veiculo fecha o dono anterior
 ✔ Alteracoes ficam na auditoria com o usuario
 ✔ Busca de clientes por nome documento e placa

Consulta Placa (Tests\Feature\ConsultaPlaca)
 ✔ Placa ja cadastrada abre a ficha sem gastar consulta
 ✔ Placa invalida
 ✔ Placa nova consulta a api com os cabecalhos certos e reaproveita depois
 ✔ Reaproveitamento respeita o prazo e a empresa
 ✔ Dados mapeados do provedor
 ✔ Placa nao encontrada e saldo acabado
 ✔ Alerta de roubo ou furto
 ✔ Quem nao tem permissao nao gasta consulta
 ✔ Falha de conexao vira aviso amigavel

Documento (Tests\Unit\Documento)
 ✔ Cpf
 ✔ Cnpj numerico
 ✔ Cnpj alfanumerico de 2026
 ✔ Formatar

Empresa Isolamento (Tests\Feature\EmpresaIsolamento)
 ✔ Consultas so enxergam a propria empresa
 ✔ Sem empresa definida nao retorna nada e nao grava
 ✔ Url de cliente e veiculo de outra empresa da 404
 ✔ Mesma placa pode existir em empresas diferentes

Estoque Financeiro (Tests\Feature\EstoqueFinanceiro)
 ✔ Importa xml reconhece e lanca estoque e contas a pagar
 ✔ Aprende o codigo do fornecedor para a proxima nota
 ✔ Xml invalido mostra erro amigavel
 ✔ Contagem de inventario ajusta o saldo
 ✔ Entrega baixa estoque gera comissao e lembrete
 ✔ Pagamento dividido com parcelas e exigencia do caixa para dinheiro
 ✔ Caixa sangria pagamento e fechamento com diferenca
 ✔ Conta fixa repetida por meses
 ✔ Permissoes dos novos modulos
 ✔ Dados de outra empresa nao aparecem
 ✔ Telas abrem

Notas Agenda (Tests\Feature\NotasAgenda)
 ✔ Sem token emite em modo demonstracao
 ✔ Nao emite antes do pagamento nem peca sem ncm
 ✔ Nfse pela focus envia dados certos e atualiza situacao
 ✔ Nfce envia itens e forma de pagamento e erro vira mensagem
 ✔ Cancelamento exige justificativa
 ✔ Token fiscal fica criptografado e nao aparece na tela
 ✔ Agendamento liga placa ao veiculo e muda status
 ✔ Lembrete sem whatsapp automatico abre o whatsapp
 ✔ Comando envia lembretes pela evolution api

Ordem Servico (Tests\Feature\OrdemServico)
 ✔ Dinheiro converte formatos brasileiros
 ✔ Abre os com numero por empresa checklist limpo e km atualizado
 ✔ Itens somam total com quantidade e desconto
 ✔ Item do catalogo usa nome e preco padrao
 ✔ Fluxo completo com aprovacao do cliente pelo link
 ✔ Cliente recusando tudo volta para orcamento
 ✔ Item adicional durante servico precisa de nova aprovacao
 ✔ Nao pula etapas
 ✔ Permissoes por perfil
 ✔ Os de outra empresa nao aparece e nao aceita itens
 ✔ Link do orcamento mostra so a os certa e token invalido da 404
 ✔ Fotos sao reduzidas e so abrem para a mesma empresa ou pelo link
 ✔ Telas de os abrem
 ✔ Catalogo de servicos

Placa (Tests\Unit\Placa)
 ✔ Normaliza removendo traco espaco e minusculas
 ✔ Valida formatos with antiga
 ✔ Valida formatos with mercosul
 ✔ Valida formatos with com·traço
 ✔ Valida formatos with curta
 ✔ Valida formatos with letra·no·fim
 ✔ Valida formatos with número·no·início
 ✔ Valida formatos with vazia
 ✔ Identifica mercosul e formata
 ✔ Equivalentes converte entre antiga e mercosul

Plataforma Seguranca (Tests\Feature\PlataformaSeguranca)
 ✔ Link expira e pode ser trocado
 ✔ Aceite guarda versao exata e recusa orcamento alterado depois
 ✔ Os cancelada desativa o link
 ✔ Reserva e bloqueio de estoque negativo
 ✔ Estorno de recebimento em dinheiro sai do caixa
 ✔ Exportacao csv
 ✔ Agenda respeita capacidade
 ✔ Recuperar senha
 ✔ Totp e login em duas etapas
 ✔ Cadastro cria oficina em teste e bloqueia quando vence
 ✔ Limite de usuarios do plano
 ✔ Webhook asaas autentica e e idempotente
 ✔ Assinatura pelo asaas envia dados certos
 ✔ Painel da plataforma exige admin com duas etapas
 ✔ Portal do cliente
```
