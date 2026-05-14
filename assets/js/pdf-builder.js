/**
 * ANOVEX Label System v2 — PDF Builder
 * FlateDecode, přeneseno ze stávajícího systému
 */

async function buildPDF(canvas, imgW, imgH) {
  const ctx       = canvas.getContext('2d');
  const imageData = ctx.getImageData(0, 0, imgW, imgH);
  const rgb       = new Uint8Array(imgW * imgH * 3);

  for (let i = 0; i < imgW * imgH; i++) {
    rgb[i*3]   = imageData.data[i*4];
    rgb[i*3+1] = imageData.data[i*4+1];
    rgb[i*3+2] = imageData.data[i*4+2];
  }

  const compressed = await deflateRaw(rgb);

  const enc  = new TextEncoder();
  const nl   = enc.encode('\n');
  const crlf = enc.encode('\r\n');

  function pdfStr(s) { return enc.encode(s); }

  const imgStream = compressed;
  const imgLen    = imgStream.length;

  const catalog   = pdfStr('1 0 obj\n<</Type /Catalog /Pages 2 0 R>>\nendobj\n\n');
  const pages     = pdfStr(`2 0 obj\n<</Type /Pages /Kids [3 0 R] /Count 1>>\nendobj\n\n`);
  const pageW     = (imgW  * 72 / 300).toFixed(2);
  const pageH     = (imgH  * 72 / 300).toFixed(2);
  const page      = pdfStr(`3 0 obj\n<</Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageW} ${pageH}] /Contents 4 0 R /Resources <</XObject <</Im1 5 0 R>>>>>>\nendobj\n\n`);
  const contentS  = `q ${pageW} 0 0 ${pageH} 0 0 cm /Im1 Do Q`;
  const content   = pdfStr(`4 0 obj\n<</Length ${contentS.length}>>\nstream\n${contentS}\nendstream\nendobj\n\n`);
  const imgObj    = pdfStr(`5 0 obj\n<</Type /XObject /Subtype /Image /Width ${imgW} /Height ${imgH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length ${imgLen}>>\nstream\n`);
  const imgObjEnd = pdfStr('\nendstream\nendobj\n\n');

  const parts = [catalog, pages, page, content, imgObj, imgStream, imgObjEnd];
  const header = pdfStr('%PDF-1.4\n');
  const offsets = [];

  let pos = header.length;
  for (const p of parts) {
    offsets.push(pos);
    pos += p.length;
  }

  const xrefPos  = pos;
  const objCount = 6;
  let xref = `xref\n0 ${objCount}\n0000000000 65535 f \n`;
  for (let i = 1; i < objCount; i++) {
    xref += String(offsets[i-1]).padStart(10, '0') + ' 00000 n \n';
  }
  xref += `trailer\n<</Size ${objCount} /Root 1 0 R>>\nstartxref\n${xrefPos}\n%%EOF`;

  const xrefBytes = pdfStr(xref);
  const total     = header.length + parts.reduce((a,b) => a + b.length, 0) + xrefBytes.length;
  const out       = new Uint8Array(total);
  let off = 0;

  function write(arr) { out.set(arr, off); off += arr.length; }

  write(header);
  for (const p of parts) write(p);
  write(xrefBytes);

  return new Blob([out], {type: 'application/pdf'});
}

async function deflateRaw(data) {
  const cs = new CompressionStream('deflate');  // zlib formát (s hlavičkou) — vyžaduje PDF FlateDecode
  const wr = cs.writable.getWriter();
  wr.write(data);
  wr.close();
  const chunks = [];
  const reader = cs.readable.getReader();
  while (true) {
    const {done, value} = await reader.read();
    if (done) break;
    chunks.push(value);
  }
  const total = chunks.reduce((a, b) => a + b.length, 0);
  const out   = new Uint8Array(total);
  let offset  = 0;
  for (const c of chunks) { out.set(c, offset); offset += c.length; }
  return out;
}
