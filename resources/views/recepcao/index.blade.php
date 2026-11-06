<x-app titulo="Recepção">
    <section class="card overflow-hidden">
        <div class="bg-asfalto px-6 py-8 text-white sm:px-10">
            <h1 class="text-white">Receber um carro</h1>
            <p class="mt-1 text-[#a9b6c1]">Digite a placa. Se o carro já veio antes, a ficha abre na hora.</p>

            <form data-recepcao action="{{ route('recepcao.buscar') }}" class="mt-6 flex flex-wrap items-end gap-4" autocomplete="off">
                <label class="block">
                    <span class="sr-only">Placa do veículo</span>
                    <span class="placa w-[300px] max-w-full shadow-[0_10px_30px_-12px_rgba(0,0,0,.6)]">
                        <span class="placa-faixa h-6 text-[11px]"><span>BR</span><span>BRASIL</span><span>MERCOSUL</span></span>
                        <input name="placa" value="{{ $placa }}" data-mascara="placa" maxlength="8" inputmode="text" autocapitalize="characters" spellcheck="false"
                               placeholder="ABC1D23" aria-describedby="status-placa"
                               class="placa-numero w-full bg-white py-2 text-[54px] uppercase outline-none placeholder:text-[#cfd5db] focus:bg-[#fffbea]">
                    </span>
                </label>
                <button class="btn-sinal px-6 py-3.5 text-base">Buscar placa</button>
            </form>
            <p id="status-placa" class="mt-3 min-h-6 text-[15px] text-[#d6dde3]" data-recepcao-status aria-live="polite"></p>
        </div>
    </section>

    <div class="mt-6" data-recepcao-saida></div>
</x-app>
