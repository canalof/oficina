<?php

return [
    'accepted' => 'O campo :attribute deve ser aceito.',
    'after' => 'O campo :attribute deve ser uma data depois de :date.',
    'before' => 'O campo :attribute deve ser uma data antes de :date.',
    'between' => [
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'current_password' => 'A senha atual está incorreta.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'email' => 'Informe um e-mail válido.',
    'enum' => 'O valor de :attribute é inválido.',
    'exists' => 'O :attribute selecionado é inválido.',
    'in' => 'O valor de :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'string' => 'O campo :attribute deve ter no mínimo :min caracteres.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'password' => [
        'letters' => 'A :attribute deve ter pelo menos uma letra.',
        'mixed' => 'A :attribute deve ter letras maiúsculas e minúsculas.',
        'numbers' => 'A :attribute deve ter pelo menos um número.',
        'symbols' => 'A :attribute deve ter pelo menos um símbolo.',
        'uncompromised' => 'Esta :attribute apareceu em vazamentos de dados. Escolha outra.',
    ],
    'required' => 'Preencha o campo :attribute.',
    'size' => [
        'numeric' => 'O campo :attribute deve ser :size.',
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',

    'attributes' => [
        'nome' => 'nome',
        'email' => 'e-mail',
        'telefone' => 'telefone',
        'whatsapp' => 'WhatsApp',
        'cep' => 'CEP',
        'uf' => 'UF',
        'nascimento' => 'nascimento',
        'observacoes' => 'observações',
        'placa' => 'placa',
        'modelo' => 'modelo',
        'chassi' => 'chassi',
        'perfil' => 'perfil',
    ],
];
