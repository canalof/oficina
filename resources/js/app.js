import QRCode from 'qrcode';
// Máscaras simples e utilidades das telas (sem framework)

const soDigitos = (v) => (v || '').replace(/\D/g, '');

function mascaraDocumento(v) {
    const d = (v || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (d.length <= 11 && /^\d*$/.test(d)) {
        return d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
    }
    return d.slice(0, 14).replace(/^(\w{2})(\w)/, '$1.$2').replace(/^(\w{2})\.(\w{3})(\w)/, '$1.$2.$3')
        .replace(/\.(\w{3})(\w)/, '.$1/$2').replace(/(\w{4})(\d{1,2})$/, '$1-$2');
}

function mascaraTelefone(v) {
    const d = soDigitos(v).slice(0, 11);
    if (d.length <= 10) return d.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
    return d.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
}

// "123456" -> "1.234,56"
function mascaraDinheiro(v) {
    const d = soDigitos(v).replace(/^0+(?=\d)/, '');
    if (!d) return '';
    const n = d.padStart(3, '0');
    return n.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + n.slice(-2);
}

const reais = (centavos) => (centavos / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

document.addEventListener('input', (e) => {
    const el = e.target;
    if (el.matches('[data-mascara=documento]')) el.value = mascaraDocumento(el.value);
    if (el.matches('[data-mascara=telefone]')) el.value = mascaraTelefone(el.value);
    if (el.matches('[data-mascara=cep]')) el.value = soDigitos(el.value).slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2');
    if (el.matches('[data-mascara=placa]')) el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
    if (el.matches('[data-mascara=dinheiro]')) el.value = mascaraDinheiro(el.value);
    if (el.matches('[data-mascara=km]')) el.value = soDigitos(el.value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
});

// Busca de endereço pelo CEP (ViaCEP)
document.addEventListener('focusout', async (e) => {
    const el = e.target;
    if (!el.matches('[data-mascara=cep]')) return;
    const cep = soDigitos(el.value);
    if (cep.length !== 8) return;
    try {
        const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const j = await r.json();
        if (j.erro) return;
        const form = el.form;
        const set = (n, v) => { const c = form.querySelector(`[name=${n}]`); if (c && !c.value) c.value = v || ''; };
        set('logradouro', j.logradouro); set('bairro', j.bairro); set('cidade', j.localidade); set('uf', j.uf);
    } catch (_) { /* sem internet: segue manual */ }
});

// Confirmação antes de ações destrutivas
document.addEventListener('submit', (e) => {
    const msg = e.submitter?.getAttribute('data-confirmar') || e.target.getAttribute('data-confirmar');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // Formulário que abre em outra aba (ex.: WhatsApp): recarrega esta para mostrar a nova situação
    if (e.target.target === '_blank' && e.target.querySelector('[data-recarregar]')) setTimeout(() => location.reload(), 1500);
});

// Menu no celular
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-menu-abrir]')) document.body.classList.toggle('menu-aberto');
});

// Recepção: busca de placa
const form = document.querySelector('[data-recepcao]');
if (form) {
    const input = form.querySelector('input[name=placa]');
    const saida = document.querySelector('[data-recepcao-saida]');
    const status = document.querySelector('[data-recepcao-status]');
    const urlBusca = form.getAttribute('action');

    async function buscar(placa) {
        status.textContent = 'Procurando a placa…';
        saida.setAttribute('aria-busy', 'true');
        try {
            const r = await fetch(`${urlBusca}?placa=${encodeURIComponent(placa)}`, { headers: { Accept: 'application/json' } });
            const j = await r.json();
            if (r.status === 422) { status.textContent = j.mensagem; saida.innerHTML = ''; return; }
            if (r.status === 429) { status.textContent = 'Muitas buscas seguidas. Aguarde um minuto.'; return; }
            status.textContent = j.status === 'cadastrado' ? 'Veículo encontrado.' : 'Placa nova. Confira os dados e cadastre.';
            saida.innerHTML = j.html;
            history.replaceState(null, '', `?placa=${placa}`);
        } catch (_) {
            status.textContent = 'Não foi possível buscar agora. Verifique a conexão e tente de novo.';
        } finally {
            saida.removeAttribute('aria-busy');
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const p = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (!/^[A-Z]{3}\d[A-Z0-9]\d{2}$/.test(p)) { status.textContent = 'Placa inválida. Use ABC1234 ou ABC1D23.'; input.focus(); return; }
        buscar(p);
    });
    if (input.value.length === 7) form.requestSubmit();
}

// Copiar link (orçamento para o cliente)
document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-copiar]');
    if (!b) return;
    const texto = b.getAttribute('data-copiar');
    try { await navigator.clipboard.writeText(texto); } catch (_) { window.prompt('Copie o link:', texto); return; }
    const antes = b.textContent;
    b.textContent = 'Link copiado';
    setTimeout(() => { b.textContent = antes; }, 2000);
});

// OS: ao escolher um serviço do catálogo, preenche descrição e valor; peça esconde o catálogo
const itemForm = document.querySelector('[data-item-form]');
if (itemForm) {
    const catalogo = itemForm.querySelector('[data-catalogo]');
    const soServico = itemForm.querySelector('[data-so-servico]');
    catalogo?.addEventListener('change', () => {
        const op = catalogo.selectedOptions[0];
        if (!op?.value) return;
        itemForm.querySelector('[name=descricao]').value = op.dataset.nome;
        itemForm.querySelector('[name=valor]').value = op.dataset.preco;
    });
    const estoque = itemForm.querySelector('[data-estoque]');
    const soPeca = itemForm.querySelector('[data-so-peca]');
    estoque?.addEventListener('change', () => {
        const op = estoque.selectedOptions[0];
        if (!op?.value) return;
        itemForm.querySelector('[name=descricao]').value = op.dataset.nome;
        itemForm.querySelector('[name=valor]').value = op.dataset.preco;
    });
    const atualizarTipo = () => {
        const peca = itemForm.querySelector('[name=tipo]:checked')?.value === 'peca';
        if (soServico) soServico.hidden = peca;
        if (soPeca) soPeca.hidden = !peca;
        if (peca && catalogo) catalogo.value = '';
        if (!peca && estoque) estoque.value = '';
    };
    itemForm.addEventListener('change', (e) => { if (e.target.name === 'tipo') atualizarTipo(); });
    atualizarTipo();
}

// Página do orçamento do cliente: total muda conforme marca/desmarca
const orc = document.querySelector('[data-orcamento]');
if (orc) {
    const total = orc.querySelector('[data-orcamento-total]');
    const somar = () => {
        let t = parseInt(total.dataset.base, 10) || 0;
        orc.querySelectorAll('input[name="itens[]"]:checked').forEach((c) => { t += parseInt(c.dataset.valor, 10) || 0; });
        total.textContent = reais(Math.max(0, t));
    };
    orc.addEventListener('change', somar);
}

// OS: receber pagamento (divide em formas, mostra quanto falta)
const pag = document.querySelector('[data-pagamento]');
if (pag) {
    const total = parseInt(pag.dataset.pagamento, 10);
    const linhas = [...pag.querySelectorAll('[data-linha]')];
    const centavos = (v) => parseInt(soDigitos(v) || '0', 10);
    const atualizar = () => {
        let soma = 0;
        linhas.forEach((l) => {
            if (!l.hidden) soma += centavos(l.querySelector('[data-valor]').value);
            const f = l.querySelector('[data-forma]').value;
            l.querySelector('[data-parcelas]').hidden = !['credito', 'boleto', 'prazo'].includes(f);
            l.querySelector('[data-vencimento]').hidden = !['boleto', 'prazo'].includes(f);
        });
        const r = pag.querySelector('[data-restante]');
        const falta = total - soma;
        r.textContent = falta === 0 ? 'Valores conferem' : (falta > 0 ? 'Falta ' + reais(falta) : 'Passou ' + reais(-falta));
        r.className = 'text-sm font-semibold ' + (falta === 0 ? 'text-ok' : 'text-erro');
    };
    pag.addEventListener('input', atualizar);
    pag.addEventListener('change', atualizar);
    pag.querySelector('[data-mais-forma]')?.addEventListener('click', () => {
        const prox = linhas.find((l) => l.hidden);
        if (!prox) return;
        prox.hidden = false;
        let soma = 0;
        linhas.forEach((l) => { if (!l.hidden && l !== prox) soma += centavos(l.querySelector('[data-valor]').value); });
        if (total - soma > 0) prox.querySelector('[data-valor]').value = mascaraDinheiro(String(total - soma));
        atualizar();
    });
    atualizar();
}

// Agenda no celular: mostra o dia de hoje
document.querySelector('[data-hoje]')?.scrollIntoView({ inline: 'center', block: 'nearest' });

// QR code da verificação em duas etapas (gerado no navegador, a chave não sai daqui)
document.querySelectorAll('canvas[data-qrcode]').forEach((c) => {
    QRCode.toCanvas(c, c.dataset.qrcode, { width: 180, margin: 1 });
});
