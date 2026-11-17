import { BrowserQRCodeReader } from '@zxing/browser';
import { createWorker, PSM } from 'tesseract.js';
import { prepararImagem } from './placa-ocr';

export class LocalDocumentReader {
    async ler(arquivo) {
        const imagem = await prepararImagem(arquivo);
        let qrEncontrado = false;
        try {
            new BrowserQRCodeReader().decodeFromCanvas(imagem);
            qrEncontrado = true;
        } catch {
            // Um documento pode estar legível mesmo sem QR detectável.
        }
        const base = new URL('/build/ocr/', window.location.origin);
        const worker = await createWorker('eng', 1, {
            workerPath: new URL('worker.min.js', base).href,
            corePath: base.href,
            langPath: base.href,
            workerBlobURL: false,
            cacheMethod: 'none',
        });
        try {
            await worker.setParameters({ tessedit_pageseg_mode: PSM.AUTO });
            const { data } = await worker.recognize(imagem);
            return { ...extrairDados(data.text), qrEncontrado };
        } finally {
            await worker.terminate();
            imagem.width = 0;
            imagem.height = 0;
        }
    }
}

export function extrairDados(texto) {
    const normalizado = texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toUpperCase();
    const cpf = normalizado.match(/\b\d{3}[. ]\d{3}[. ]\d{3}[- ]\d{2}\b/)?.[0]?.replace(/\D/g, '');
    const validade = normalizado.match(/VALIDADE\s*[:\s]*([0-3]\d)\s*[/.-]\s*([01]\d)\s*[/.-]\s*(20\d{2})/);
    const categoria = normalizado.match(/(?:CATEGORIA|CAT\.?\s*HAB\.?)[\s:]+(ACC|AB|AC|AD|AE|A|B|C|D|E)\b/)?.[1];
    return { cpf, validade: validade ? [validade[3], validade[2], validade[1]].join('-') : null, categoria };
}
