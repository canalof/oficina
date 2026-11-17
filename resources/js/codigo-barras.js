import { BrowserMultiFormatOneDReader } from '@zxing/browser';

export async function lerCodigoBarras(arquivo) {
    const url = URL.createObjectURL(arquivo);
    try {
        const leitor = new BrowserMultiFormatOneDReader();
        const resultado = await leitor.decodeFromImageUrl(url);
        return resultado.getText();
    } finally {
        URL.revokeObjectURL(url);
    }
}
