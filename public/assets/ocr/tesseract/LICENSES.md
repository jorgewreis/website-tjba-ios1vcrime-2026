# Tesseract.js OCR assets

This directory contains pinned browser assets used only by the experimental PDF Intelligence Engine. PDF page images and OCR text remain in the browser; worker, WebAssembly, and Portuguese language files are served from this application.

| Asset | Version/source | Declared license | Note |
|---|---|---|---|
| `tesseract.min.js`, `worker.min.js` | `tesseract.js@7.0.0`, npm tarball SHA-1 `4106fb6245efab40c57b94bc1798368807526be8` | Apache-2.0 | License copy: `licenses/tesseract.js-7.0.0-LICENSE.md`. |
| `core/tesseract-core-lstm.wasm.js`, `.wasm` | `tesseract.js-core@7.0.0`, npm tarball SHA-1 `596aa1ab5c130adab12f21059e6aa1a1cecc0bab` | Apache-2.0 | License copy: `licenses/tesseract.js-core-7.0.0-LICENSE`. The WASM distribution includes permissively licensed upstream components; see the upstream [license clarification](https://github.com/naptha/tesseract.js-core/issues/44). Review notices before production redistribution. |
| `lang/4.0.0_best_int/por.traineddata.gz` | `@tesseract.js-data/por@1.0.0`, npm tarball SHA-1 `33bcb3972aa3f308f7dbdda232c7140d74e091db` | MIT in npm metadata; source tessdata repository is Apache-2.0 | The npm tarball contains no standalone license file. This discrepancy is recorded for legal review; do not treat this inventory as a legal opinion. |

The portable LSTM-only WASM build is selected explicitly so the basic OCR path does not depend on SIMD or a GPU. The Tesseract.js documentation discourages selecting one core build because it can reduce performance; throughput and browser compatibility therefore remain benchmark gates. The language model is the package's default integerized `tessdata_best` model for LSTM-only recognition.

Upstream sources: [Tesseract.js](https://github.com/naptha/tesseract.js), [Tesseract.js core](https://github.com/naptha/tesseract.js-core), [Tesseract.js language data](https://github.com/naptha/tessdata), [tesseract-ocr tessdata](https://github.com/tesseract-ocr/tessdata).
