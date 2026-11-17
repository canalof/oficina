import { createWorker, PSM } from 'tesseract.js';

export async function prepararImagem(arquivo) {
    const url = URL.createObjectURL(arquivo);
    try {
        const imagem = new Image();
        imagem.src = url;
        await imagem.decode();
        const escala = Math.min(1, 1600 / Math.max(imagem.naturalWidth, imagem.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(imagem.naturalWidth * escala));
        canvas.height = Math.max(1, Math.round(imagem.naturalHeight * escala));
        canvas.getContext('2d').drawImage(imagem, 0, 0, canvas.width, canvas.height);
        return canvas;
    } finally {
        URL.revokeObjectURL(url);
    }
}

export function placasNoTexto(texto) {
    const placas = texto.toUpperCase().split(/\r?\n/).flatMap((linha) => {
        const compacta = linha.replace(/[^A-Z0-9]/g, '');
        return compacta.match(/[A-Z]{3}\d[A-Z0-9]\d{2}/g) ?? [];
    });
    return [...new Set(placas)];
}

export async function lerPlaca(arquivo) {
    const imagem = await prepararImagem(arquivo);
    const base = new URL('/build/ocr/', window.location.origin);
    const worker = await createWorker('eng', 1, {
        workerPath: new URL('worker.min.js', base).href,
        corePath: base.href,
        langPath: base.href,
        workerBlobURL: false,
    });
    try {
        await worker.setParameters({
            tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            tessedit_pageseg_mode: PSM.SINGLE_LINE,
        });
        const { data } = await worker.recognize(imagem);
        const placas = placasNoTexto(data.text);
        if (placas.length) return placas;

        await worker.setParameters({ tessedit_pageseg_mode: PSM.SPARSE_TEXT });
        const segundaLeitura = await worker.recognize(imagem);
        return placasNoTexto(segundaLeitura.data.text);
    } finally {
        await worker.terminate();
    }
}
