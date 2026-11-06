@props(['nome', 'rotulo', 'valor' => null, 'tipo' => 'text', 'mascara' => null, 'ajuda' => null])
<label {{ $attributes->only('class')->merge(['class' => 'campo']) }}>
    <span>{{ $rotulo }}</span>
    <input type="{{ $tipo }}" name="{{ $nome }}" value="{{ old($nome, $valor) }}"
        @if($mascara) data-mascara="{{ $mascara }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($nome) ? ' input-erro' : '')]) }}
        @error($nome) aria-invalid="true" aria-describedby="erro-{{ $nome }}" @enderror>
    @if ($ajuda)<small class="mt-1 block text-xs text-suave">{{ $ajuda }}</small>@endif
    @error($nome)<p class="erro-msg" id="erro-{{ $nome }}">{{ $message }}</p>@enderror
</label>
