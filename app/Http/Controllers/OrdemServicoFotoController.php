<?php

namespace App\Http\Controllers;

use App\Models\OrdemServico;
use App\Models\OrdemServicoFoto;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos do checklist de entrada e do serviço.
 * Ficam fora da pasta pública: só abre quem é da mesma empresa (ou o cliente, pelo link do orçamento).
 */
class OrdemServicoFotoController extends Controller
{
    public const MAX_LADO = 1600;

    public function store(Request $request, OrdemServico $os)
    {
        $this->authorize('os.executar');
        abort_unless($os->status->noPatio(), 403);
        $request->validate([
            'fotos' => ['required', 'array', 'max:12'],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:15360'],
            'legenda' => ['nullable', 'string', 'max:160'],
        ], [
            'fotos.required' => 'Escolha pelo menos uma foto.',
            'fotos.*.image' => 'Envie apenas fotos.',
            'fotos.*.max' => 'Cada foto pode ter até 15 MB.',
        ]);

        foreach ($request->file('fotos') as $arquivo) {
            $os->fotos()->create([
                'user_id' => auth()->id(),
                'caminho' => $this->guardar($arquivo, $os),
                'legenda' => $request->input('legenda'),
            ]);
        }
        $n = count($request->file('fotos'));
        $os->registrar($n === 1 ? '1 foto anexada' : "{$n} fotos anexadas");

        return back()->with('ok', $n === 1 ? 'Foto anexada.' : "{$n} fotos anexadas.")->withFragment('fotos');
    }

    public function show(OrdemServico $os, OrdemServicoFoto $foto)
    {
        $this->authorize('os.ver');

        return $this->enviar($os, $foto);
    }

    public function destroy(OrdemServico $os, OrdemServicoFoto $foto)
    {
        $this->authorize('os.executar');
        abort_unless($foto->ordem_servico_id === $os->id, 404);
        Storage::disk('local')->delete($foto->caminho);
        $foto->delete();

        return back()->with('ok', 'Foto removida.')->withFragment('fotos');
    }

    /** Usado também pela página pública do orçamento. */
    public static function enviar(OrdemServico $os, OrdemServicoFoto $foto)
    {
        abort_unless($foto->ordem_servico_id === $os->id && Storage::disk('local')->exists($foto->caminho), 404);

        return Storage::disk('local')->response($foto->caminho, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Reduz para no máximo 1600px (foto de celular cai de ~4 MB para ~300 KB). */
    private function guardar(UploadedFile $arquivo, OrdemServico $os): string
    {
        $pasta = 'os/'.$os->empresa_id.'/'.$os->id;
        $nome = Str::uuid()->toString();
        $origem = $arquivo->getRealPath();
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($origem)) : false;

        if (! $img) {
            // Formato que o GD não abre (ex.: HEIC): guarda como veio
            return $arquivo->storeAs($pasta, $nome.'.'.($arquivo->extension() ?: 'jpg'), 'local');
        }

        $img = $this->corrigirRotacao($img, $origem);
        [$l, $a] = [imagesx($img), imagesy($img)];
        $escala = min(1, self::MAX_LADO / max($l, $a));
        if ($escala < 1) {
            $nova = imagescale($img, (int) round($l * $escala), (int) round($a * $escala));
            imagedestroy($img);
            $img = $nova;
        }
        ob_start();
        imagejpeg($img, null, 82);
        $jpg = ob_get_clean();
        imagedestroy($img);

        $caminho = $pasta.'/'.$nome.'.jpg';
        Storage::disk('local')->put($caminho, $jpg);

        return $caminho;
    }

    private function corrigirRotacao(\GdImage $img, string $arquivo): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($arquivo);
        $graus = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };

        return $graus ? imagerotate($img, $graus, 0) : $img;
    }
}
