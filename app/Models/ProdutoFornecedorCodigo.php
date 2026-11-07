<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use Illuminate\Database\Eloquent\Model;

class ProdutoFornecedorCodigo extends Model
{
    use PertenceAEmpresa;

    protected $fillable = ['fornecedor_id', 'produto_id', 'codigo'];
}
