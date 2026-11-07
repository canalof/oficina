@if (session('duplicados'))
    <div class="rounded-md border border-[#f0d58a] bg-[#fff7df] px-4 py-3 text-[15px]" role="alert">
        <p class="font-semibold">Parece que já existe um cadastro assim:</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach (session('duplicados') as $d)
                <li><a href="{{ $d['url'] }}" class="font-semibold underline" target="_blank">{{ $d['nome'] }}</a>{{ $d['info'] ? ' · '.$d['info'] : '' }}</li>
            @endforeach
        </ul>
        <p class="mt-2">Se for o mesmo, use o cadastro que já existe (pode editar). Se for outro, marque abaixo e salve de novo.</p>
        <label class="mt-2 flex items-center gap-2 font-semibold"><input type="checkbox" name="confirmar_duplicado" value="1" class="h-4 w-4 accent-asfalto"> É outro cadastro, salvar mesmo assim</label>
    </div>
@endif
