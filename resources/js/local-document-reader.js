import { BrowserQRCodeReader } from '@zxing/browser';
import { createWorker, PSM } from 'tesseract.js';
import { prepararImagem } from './placa-ocr';

/** PDF (CNH digital ou foto salva em PDF): desenha a 1ª página num canvas, tudo no aparelho. */
async function prepararPdf(arquivo, ladoMaior = 2000) {
    const pdfjs = await import('pdfjs-dist');
    const { default: workerUrl } = await import('pdfjs-dist/build/pdf.worker.min.mjs?url');
    pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
    const pdf = await pdfjs.getDocument({ data: await arquivo.arrayBuffer(), isEvalSupported: false }).promise;
    try {
        const pagina = await pdf.getPage(1);
        const base = pagina.getViewport({ scale: 1 });
        const viewport = pagina.getViewport({ scale: ladoMaior / Math.max(base.width, base.height) });
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(viewport.width);
        canvas.height = Math.round(viewport.height);
        await pagina.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        return canvas;
    } finally {
        await pdf.destroy();
    }
}

export function ehPdf(arquivo) {
    return arquivo.type === 'application/pdf' || /\.pdf$/i.test(arquivo.name || '');
}

export class LocalDocumentReader {
    async ler(arquivo) {
        const imagem = ehPdf(arquivo) ? await prepararPdf(arquivo) : await prepararImagem(arquivo);
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
    // Na CNH, "emissão" e "validade" ficam lado a lado e o OCR junta as duas datas.
    // A validade é sempre a data mais recente do documento (nascimento, 1ª habilitação
    // e emissão são anteriores; as validades por categoria no verso são iguais a ela).
    const datas = [...normalizado.matchAll(/\b([0-3]\d)\s*[/.-]\s*([01]\d)\s*[/.-]\s*((?:19|20)\d{2})\b/g)]
        .map(([, dia, mes, ano]) => `${ano}-${mes}-${dia}`)
        .filter((data) => !Number.isNaN(Date.parse(data)))
        .sort();
    const validade = /VALIDADE/.test(normalizado) && datas.length ? datas[datas.length - 1] : null;
    const categoria = normalizado.match(/(?:CATEGORIA|CAT\.?\s*HA[B8S]\.?)[\s:]+(ACC|AB|AC|AD|AE|A|B|C|D|E)\b/)?.[1];
    return { cpf, validade, categoria };
}
