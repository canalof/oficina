import QRCode from 'qrcode';

const documentoCnh = document.querySelector('[data-documento-cnh]');
if (documentoCnh) {
    const fotoCnh = documentoCnh.querySelector('input[type=file]');
    const saidaCnh = documentoCnh.querySelector('[data-documento-resultado]');
    fotoCnh.addEventListener('change', async () => {
        const arquivo = fotoCnh.files[0];
        if (!arquivo) return;
        fotoCnh.value = '';
        if (arquivo.size > 15 * 1024 * 1024) {
            saidaCnh.textContent = 'Use uma foto ou PDF de até 15 MB.';
            return;
        }
        fotoCnh.disabled = true;
        saidaCnh.textContent = 'Lendo o documento neste aparelho…';
        try {
            const { LocalDocumentReader } = await import('./local-document-reader');
            const extraido = await new LocalDocumentReader().ler(arquivo);
            const formulario = document.querySelector('[data-cadastro-condutor]');
            const linhas = ['Leitura local. Autenticidade e situação atual não verificadas.'];
            for (const [campo, rotulo] of [['cpf', 'CPF'], ['categoria', 'Categoria'], ['validade', 'Validade']]) {
                const valor = extraido[campo];
                const cadastro = formulario?.elements.namedItem(campo)?.value;
                const exibido = campo === 'cpf' && valor ? '•••.•••.•••-' + valor.slice(-2) : valor;
                const comparacao = valor && cadastro ? (valor === cadastro ? ' · coincide com o campo' : ' · diverge do campo') : '';
                linhas.push(rotulo + ': ' + (exibido ?? 'não identificado') + comparacao);
            }
            linhas.push(extraido.qrEncontrado ? 'QR Code detectado. Conteúdo não enviado.' : 'QR Code não detectado.');
            saidaCnh.textContent = linhas.join('\n');
        } catch {
            saidaCnh.textContent = 'Não foi possível ler o arquivo. Confira manualmente os dados do documento.';
        } finally {
            fotoCnh.disabled = false;
        }
    });
}

const listaModelos = document.querySelector('[data-modelos-veiculo]');
if (listaModelos) {
    const formulario = listaModelos.closest('form');
    const marcaVeiculo = formulario.elements.namedItem('marca');
    const modeloVeiculo = formulario.elements.namedItem('modelo');
    const selecao = formulario.querySelector('[data-selecao-veiculo]');
    const marcaSugerida = selecao.querySelector('[data-marca-sugerida]');
    const modeloSugerido = selecao.querySelector('[data-modelo-sugerido]');
    const catalogo = JSON.parse(listaModelos.dataset.modelosVeiculo);
    const todos = [...new Set(Object.values(catalogo).flat())].sort();
    const sugerirModelos = () => {
        const marca = Object.keys(catalogo).find((nome) => nome.toLowerCase() === marcaVeiculo.value.trim().toLowerCase());
        const modelos = marca ? catalogo[marca] : [];
        listaModelos.replaceChildren(...(marca ? modelos : todos).map((modelo) => new Option(modelo, modelo)));
        marcaSugerida.value = marca ?? '';
        modeloSugerido.replaceChildren(
            new Option(marca ? 'Escolha um modelo' : 'Escolha a marca primeiro', ''),
            ...modelos.map((modelo) => new Option(modelo, modelo)),
        );
        modeloSugerido.disabled = !marca;
        modeloSugerido.value = modelos.includes(modeloVeiculo.value) ? modeloVeiculo.value : '';
    };
    marcaVeiculo.addEventListener('input', sugerirModelos);
    marcaSugerida.addEventListener('change', () => {
        if (!marcaSugerida.value) return;
        marcaVeiculo.value = marcaSugerida.value;
        sugerirModelos();
    });
    modeloSugerido.addEventListener('change', () => {
        if (modeloSugerido.value) modeloVeiculo.value = modeloSugerido.value;
    });
    modeloVeiculo.addEventListener('input', () => {
        modeloSugerido.value = modeloVeiculo.value;
    });
    sugerirModelos();
    selecao.hidden = false;
}

if ('serviceWorker' in navigator && document.querySelector('link[rel="manifest"]')) {
    navigator.serviceWorker.register('/torque-sw.js').catch(() => {
        // O acesso pelo navegador continua disponível se a instalação falhar.
    });
}

const instalarTorque = document.querySelector('[data-instalar-torque]');
if (instalarTorque) {
    window.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        instalarTorque.hidden = false;
        instalarTorque.addEventListener('click', async () => {
            instalarTorque.hidden = true;
            await evento.prompt();
        }, { once: true });
    });
}

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
    const foto = form.querySelector('[data-foto-placa]');

    foto?.addEventListener('change', async () => {
        const arquivo = foto.files[0];
        if (!arquivo) return;
        foto.value = '';
        if (arquivo.size > 20 * 1024 * 1024) {
            status.textContent = 'A foto deve ter até 20 MB. Tire outra foto ou digite a placa.';
            return;
        }
        foto.disabled = true;
        status.textContent = 'Lendo a foto neste aparelho…';
        try {
            const { lerPlaca } = await import('./placa-ocr');
            const placas = await lerPlaca(arquivo);
            if (placas.length === 1) {
                input.value = placas[0];
                status.textContent = 'Placa lida. Confira o texto e toque em Buscar placa.';
                input.focus();
            } else {
                status.textContent = 'Não foi possível identificar uma única placa. Tire outra foto mais de perto ou digite a placa.';
            }
        } catch {
            status.textContent = 'Não foi possível ler a foto. Tente novamente ou digite a placa.';
        } finally {
            foto.disabled = false;
        }
    });

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

document.querySelectorAll('[data-leitor-barras]').forEach((area) => {
    const foto = area.querySelector('[data-foto-barras]');
    const status = area.querySelector('[data-status-barras]');
    const campo = area.closest('form').elements.namedItem(area.dataset.leitorBarras);

    foto.addEventListener('change', async () => {
        const arquivo = foto.files[0];
        if (!arquivo) return;
        foto.value = '';
        if (arquivo.size > 20 * 1024 * 1024) {
            status.textContent = 'A foto deve ter até 20 MB.';
            return;
        }
        foto.disabled = true;
        status.textContent = 'Lendo o código neste aparelho…';
        try {
            const { lerCodigoBarras } = await import('./codigo-barras');
            const codigo = await lerCodigoBarras(arquivo);
            if (!/^\d{8,14}$/.test(codigo)) {
                status.textContent = 'Use um código de produto com 8 a 14 dígitos.';
                return;
            }
            campo.value = codigo;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
            status.textContent = 'Código lido. Confira o campo para continuar.';
            campo.focus();
        } catch {
            status.textContent = 'Não consegui ler o código. Tire outra foto de perto, sem reflexo, ou digite os números.';
        } finally {
            foto.disabled = false;
        }
    });
});

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
    const adicionais = orc.querySelector('[data-orcamento-adicionais]');
    const somar = () => {
        const base = parseInt(total.dataset.base, 10) || 0;
        let selecionados = 0;
        orc.querySelectorAll('input[name="itens[]"]:checked').forEach((c) => { selecionados += parseInt(c.dataset.valor, 10) || 0; });
        total.textContent = reais(Math.max(0, base + selecionados));
        if (adicionais) adicionais.textContent = reais(selecionados);
    };
    orc.addEventListener('change', somar);
    somar();
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

// Aviso ao sair da tela com alterações não salvas
document.querySelectorAll('form[data-avisar-saida]').forEach((f) => {
    let mudou = false;
    f.addEventListener('input', () => { mudou = true; });
    f.addEventListener('change', () => { mudou = true; });
    f.addEventListener('submit', () => { mudou = false; });
    window.addEventListener('beforeunload', (e) => {
        if (!mudou) return;
        e.preventDefault();
        e.returnValue = '';
    });
});

// Movimentar estoque: custo só aparece na entrada
const tipoMov = document.querySelector('[data-tipo-movimento]');
if (tipoMov) {
    const custo = document.querySelector('[data-so-entrada]')?.closest('label');
    const atualizar = () => { if (custo) custo.hidden = tipoMov.value !== 'entrada'; };
    tipoMov.addEventListener('change', atualizar);
    atualizar();
}

// Balanço de estoque: diferença ao vivo, resumo e leitor de código de barras (leitor USB digita e dá Enter)
document.querySelectorAll('[data-balanco]').forEach((form) => {
    const linhas = [...form.querySelectorAll('[data-linha]')];
    const resumo = form.querySelector('[data-resumo]');
    const bipar = form.querySelector('[data-bipar]');
    const somar = form.querySelector('[data-somar]');
    const status = form.querySelector('[data-bipar-status]');
    const num = (v) => {
        const t = String(v).trim();
        if (t === '') return null;
        const n = Number(t.replace(',', '.'));
        return Number.isFinite(n) && n >= 0 ? n : NaN;
    };
    const fmt = (n) => n.toLocaleString('pt-BR', { maximumFractionDigits: 3 });

    const atualizar = (tr) => {
        const campo = tr.querySelector('[data-contado]');
        const saida = tr.querySelector('[data-diferenca]');
        const q = num(campo.value);
        saida.classList.remove('text-erro', 'text-ok', 'text-suave');
        if (q === null) { saida.textContent = ''; return; }
        if (Number.isNaN(q)) { saida.textContent = 'Número inválido'; saida.classList.add('text-erro'); return; }
        const dif = Math.round((q - Number(tr.dataset.sistema)) * 1000) / 1000;
        saida.textContent = dif === 0 ? 'Bateu' : (dif > 0 ? '+' : '−') + fmt(Math.abs(dif));
        saida.classList.add(dif === 0 ? 'text-suave' : dif > 0 ? 'text-ok' : 'text-erro');
    };
    const resumir = () => {
        let contados = 0, diferentes = 0;
        linhas.forEach((tr) => {
            const q = num(tr.querySelector('[data-contado]').value);
            if (q === null || Number.isNaN(q)) return;
            contados++;
            if (Math.round((q - Number(tr.dataset.sistema)) * 1000) !== 0) diferentes++;
        });
        resumo.textContent = contados
            ? `${contados} ${contados === 1 ? 'produto contado' : 'produtos contados'}, ${diferentes} com diferença.`
            : 'Nenhum produto contado ainda.';
    };
    linhas.forEach(atualizar);
    resumir();
    form.addEventListener('input', (e) => {
        const tr = e.target.closest('[data-linha]');
        if (tr && e.target.matches('[data-contado]')) { atualizar(tr); resumir(); }
    });

    bipar?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault(); // Enter do leitor não envia o balanço
        const codigo = bipar.value.trim();
        bipar.value = '';
        if (!codigo) return;
        const tr = linhas.find((l) => l.dataset.codigos.split('|').includes(codigo));
        if (!tr) { status.textContent = `Código ${codigo} não está nesta lista.`; status.classList.replace('text-suave', 'text-erro'); return; }
        status.classList.replace('text-erro', 'text-suave');
        const campo = tr.querySelector('[data-contado]');
        const nome = tr.querySelector('.font-semibold')?.textContent ?? '';
        if (somar.checked) {
            const atual = num(campo.value);
            campo.value = fmt((Number.isNaN(atual) || atual === null ? 0 : atual) + 1).replace(/\./g, '');
            status.textContent = `${nome}: ${campo.value}`;
            atualizar(tr); resumir();
            bipar.focus();
        } else {
            status.textContent = `${nome}: digite a quantidade.`;
            campo.focus();
            campo.select();
        }
        tr.scrollIntoView({ block: 'center', behavior: 'smooth' });
        tr.classList.add('bg-[#fff7df]');
        setTimeout(() => tr.classList.remove('bg-[#fff7df]'), 1200);
    });
});
