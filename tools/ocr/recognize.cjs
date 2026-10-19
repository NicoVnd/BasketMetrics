const { createWorker } = require('tesseract.js');
const sharp = require('sharp');

async function cleanGrid(file) {
    const {data, info} = await sharp(file).flatten({background:'#fff'}).greyscale().raw().toBuffer({resolveWithObject:true});
    const {width:w, height:h} = info;
    const erase = new Uint8Array(w*h);
    // Supprimer seulement les longs segments droits, jamais les petits traits des caractères.
    for(let y=0;y<h;y++) {
        let start=-1;
        for(let x=0;x<=w;x++) {
            const dark=x<w && data[y*w+x]<150;
            if(dark && start<0) start=x;
            if(!dark && start>=0) {
                if(x-start>w*.1) for(let xx=start;xx<x;xx++) for(let dy=-1;dy<=1;dy++) if(y+dy>=0&&y+dy<h) erase[(y+dy)*w+xx]=1;
                start=-1;
            }
        }
    }
    for(let x=0;x<w;x++) {
        let start=-1;
        for(let y=0;y<=h;y++) {
            const dark=y<h && data[y*w+x]<150;
            if(dark && start<0) start=y;
            if(!dark && start>=0) {
                if(y-start>h*.07) for(let yy=start;yy<y;yy++) for(let dx=-1;dx<=1;dx++) if(x+dx>=0&&x+dx<w) erase[yy*w+x+dx]=1;
                start=-1;
            }
        }
    }
    for(let i=0;i<data.length;i++) if(erase[i]) data[i]=255;
    return {image: await sharp(data,{raw:{width:w,height:h,channels:1}}).png().toBuffer(), width:w,height:h};
}

async function main() {
    const data = require('@tesseract.js-data/fra');
    const worker = await createWorker('fra', 1, { langPath: data.langPath, cacheMethod: 'none', gzip: true });
    try {
        const cleaned = await cleanGrid(process.argv[2]);
        await worker.setParameters({ tessedit_pageseg_mode: '6', user_defined_dpi:'150' });
        const { data: result } = await worker.recognize(cleaned.image, {}, { text: true, blocks: true });
        const toWords = result => (result.blocks ?? []).flatMap(b => b.paragraphs.flatMap(p => p.lines.flatMap(l => l.words.map(w => ({
            text: w.text, x: w.bbox.x0, y: w.bbox.y0, width: w.bbox.x1 - w.bbox.x0, height: w.bbox.y1 - w.bbox.y0, confidence: w.confidence,
        })))));
        const words = toWords(result);
        await worker.setParameters({tessedit_char_whitelist:'0123456789:', tessedit_pageseg_mode:'6'});
        const numeric = await worker.recognize(cleaned.image, {}, {text:true,blocks:true});
        const cells = [];
        const w=cleaned.width, h=cleaned.height;
        const rectangles=[];
        const mode=process.argv[3];
        if(mode==='summary') {
            const ys=words.filter(v=>v.text.includes(',') && v.x/w>.078 && v.x/w<.41 && v.y/h>.18 && v.y/h<.8).map(v=>(v.y+v.height/2)/h);
            for(const y of ys) for(const [left,right] of [[.016,.078],[.538,.602],[.602,.666],[.666,.73],[.73,.793],[.793,.857],[.857,.921],[.921,.986]]) rectangles.push({left,right,top:y-.007,bottom:y+.007});
        }
        if(mode==='sheet') {
            for(const y of [.8664,.8824]) for(const x of [.128,.221,.357,.448]) rectangles.push({left:x-.018,right:x+.018,top:y-.0048,bottom:y+.0048});
            for(const x of [.66,.888]) rectangles.push({left:x-.02,right:x+.02,top:.8616,bottom:.8712});
        }
        await worker.setParameters({tessedit_pageseg_mode:'7',tessedit_char_whitelist:'0123456789'});
        for(const r of rectangles) {
            const box={left:Math.round(r.left*w),top:Math.round(r.top*h),width:Math.round((r.right-r.left)*w),height:Math.round((r.bottom-r.top)*h)};
            const image=await sharp(mode==='sheet'?process.argv[2]:cleaned.image).extract(box).resize({height:90}).extend({top:15,bottom:15,left:15,right:15,background:'#fff'}).png().toBuffer();
            const recognized=await worker.recognize(image,{}, {text:true});
            cells.push({...r,text:recognized.data.text.trim(),confidence:recognized.data.confidence});
        }
        process.stdout.write(JSON.stringify({ text: result.text, words, numericWords:toWords(numeric.data), cells, width:w, height:h }));
    } finally { await worker.terminate(); }
}
main().catch(() => { console.error('La lecture OCR locale a échoué.'); process.exit(1); });
