import { copyFile, mkdir, readdir } from 'node:fs/promises';

const destino = new URL('../public/build/ocr/', import.meta.url);
const core = new URL('../node_modules/tesseract.js-core/', import.meta.url);
await mkdir(destino, { recursive: true });
await copyFile(new URL('../node_modules/tesseract.js/dist/worker.min.js', import.meta.url), new URL('worker.min.js', destino));
await copyFile(new URL('../node_modules/@tesseract.js-data/eng/4.0.0_best_int/eng.traineddata.gz', import.meta.url), new URL('eng.traineddata.gz', destino));

for (const arquivo of await readdir(core)) {
    if (/^tesseract-core.*-lstm\.wasm(?:\.js)?$/.test(arquivo)) {
        await copyFile(new URL(arquivo, core), new URL(arquivo, destino));
    }
}
