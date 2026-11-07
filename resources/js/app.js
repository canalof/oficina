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

document.addEventListener('input', (e) => {
    const el = e.target;
    if (el.matches('[data-mascara=documento]')) el.value = mascaraDocumento(el.value);
    if (el.matches('[data-mascara=telefone]')) el.value = mascaraTelefone(el.value);
    if (el.matches('[data-mascara=cep]')) el.value = soDigitos(el.value).slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2');
    if (el.matches('[data-mascara=placa]')) el.value = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
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
    const msg = e.target.getAttribute('data-confirmar');
    if (msg && !window.confirm(msg)) e.preventDefault();
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
