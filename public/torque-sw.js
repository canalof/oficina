self.addEventListener('fetch', (evento) => {
    if (evento.request.method !== 'GET' || evento.request.mode !== 'navigate') return;

    evento.respondWith(fetch(evento.request).catch(() => new Response(
        '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TORQUE sem conexão</title><h1>TORQUE sem conexão</h1><p>Conecte-se à internet e recarregue para continuar.</p></html>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } },
    )));
});
