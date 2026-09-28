(() => {
    'use strict';

    const escapeText = value => String(value ?? '');
    const editorRegistry = new Set();
    let activeEditor = null;
    let mathModal = null;
    let mathState = null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const renderMathNode = (latex, block = false) => {
        const node = document.createElement(block ? 'div' : 'span');
        node.className = 'cbt-rich-math' + (block ? ' is-block' : '');
        node.dataset.cbtMath = latex;
        node.dataset.cbtMathBlock = block ? '1' : '0';
        node.contentEditable = 'false';
        if (window.katex) {
            try { window.katex.render(latex, node, {throwOnError: true, trust: false, strict: 'warn', displayMode: block}); }
            catch (_) { node.textContent = block ? '$$' + latex + '$$' : '$' + latex + '$'; }
        } else node.textContent = block ? '$$' + latex + '$$' : '$' + latex + '$';
        return node;
    };

    const mediaNode = (id, media) => {
        const asset = media?.[String(id)];
        if (asset?.kind === 'IMAGE' && asset.url) {
            const image = document.createElement('img');
            image.src = asset.url;
            image.alt = 'Gambar soal';
            image.dataset.cbtMedia = String(id);
            image.contentEditable = 'false';
            image.className = 'cbt-rich-image';
            return image;
        }
        const chip = document.createElement('span');
        chip.dataset.cbtMedia = String(id);
        chip.contentEditable = 'false';
        chip.className = 'cbt-rich-media-chip';
        chip.textContent = 'Gambar #' + id;
        return chip;
    };

    const appendInline = (target, source, media) => {
        const tokens = /(\[\[media:([1-9][0-9]*)\]\]|\$\$([^$\n]{1,2000})\$\$|\$([^$\n]{1,1000})\$|\*\*([^*\n]+)\*\*|(?<!\*)\*([^*\n]+)\*(?!\*))/g;
        let cursor = 0, match;
        while ((match = tokens.exec(source)) !== null) {
            if (match.index > cursor) target.append(document.createTextNode(source.slice(cursor, match.index)));
            if (match[2]) target.append(mediaNode(match[2], media));
            else if (match[3]) target.append(renderMathNode(match[3], true));
            else if (match[4]) target.append(renderMathNode(match[4], false));
            else if (match[5]) {
                const strong = document.createElement('strong'); strong.textContent = match[5]; target.append(strong);
            } else if (match[6]) {
                const em = document.createElement('em'); em.textContent = match[6]; target.append(em);
            }
            cursor = tokens.lastIndex;
        }
        if (cursor < source.length) target.append(document.createTextNode(source.slice(cursor)));
    };

    const loadValue = (surface, value, media = {}) => {
        surface.replaceChildren();
        const lines = escapeText(value).replace(/\r\n?/g, '\n').split('\n');
        for (let i = 0; i < lines.length;) {
            if (/^\s*\|.*\|\s*$/.test(lines[i])) {
                const table = document.createElement('table');
                table.className = 'table table-bordered table-sm cbt-rich-table';
                let rowNo = 0;
                while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) {
                    const cells = lines[i].trim().replace(/^\|/, '').replace(/\|$/, '').split('|');
                    const separator = cells.every(cell => /^\s*:?-{3,}:?\s*$/.test(cell));
                    if (!separator) {
                        const tr = document.createElement('tr');
                        for (const cellText of cells) {
                            const cell = document.createElement(rowNo === 0 ? 'th' : 'td');
                            appendInline(cell, cellText.trim(), media);
                            tr.append(cell);
                        }
                        table.append(tr); rowNo++;
                    }
                    i++;
                }
                surface.append(table);
                continue;
            }
            const line = document.createElement('div');
            if (lines[i] === '') line.append(document.createElement('br'));
            else appendInline(line, lines[i], media);
            surface.append(line); i++;
        }
        if (!surface.childNodes.length) surface.append(document.createElement('div'));
    };

    const serializeInline = node => {
        if (node.nodeType === Node.TEXT_NODE) return node.nodeValue || '';
        if (!(node instanceof HTMLElement)) return '';
        if (node.dataset.cbtMedia) return '[[media:' + node.dataset.cbtMedia + ']]';
        if (node.dataset.cbtMath !== undefined) {
            const body = node.dataset.cbtMath || '';
            return node.dataset.cbtMathBlock === '1' ? '$$' + body + '$$' : '$' + body + '$';
        }
        const tag = node.tagName;
        if (tag === 'BR') return '\n';
        const inner = [...node.childNodes].map(serializeInline).join('');
        if (tag === 'STRONG' || tag === 'B') return inner ? '**' + inner + '**' : '';
        if (tag === 'EM' || tag === 'I') return inner ? '*' + inner + '*' : '';
        return inner;
    };

    const serializeTable = table => {
        const rows = [...table.querySelectorAll(':scope > tbody > tr, :scope > tr')];
        if (!rows.length) return '';
        const result = rows.map(row => '| ' + [...row.children]
            .map(cell => serializeInline(cell).replace(/\|/g, ' ').replace(/\n+/g, ' ').trim())
            .join(' | ') + ' |');
        const width = rows[0].children.length;
        result.splice(1, 0, '| ' + Array.from({length: width}, () => '---').join(' | ') + ' |');
        return result.join('\n');
    };

    const serialize = surface => {
        const chunks = [];
        for (const child of [...surface.childNodes]) {
            if (child.nodeType === Node.TEXT_NODE) {
                chunks.push(child.nodeValue || '');
                continue;
            }
            if (!(child instanceof HTMLElement)) continue;
            if (child.tagName === 'TABLE') chunks.push(serializeTable(child));
            else chunks.push(serializeInline(child));
        }
        return chunks.join('\n').replace(/\n{3,}/g, '\n\n').trim();
    };

    const restoreRange = editor => {
        editor.surface.focus();
        const selection = window.getSelection();
        selection.removeAllRanges();
        if (editor.range) selection.addRange(editor.range);
        else {
            const range = document.createRange();
            range.selectNodeContents(editor.surface);
            range.collapse(false);
            selection.addRange(range);
        }
    };

    const rememberRange = editor => {
        const selection = window.getSelection();
        if (!selection?.rangeCount) return;
        const range = selection.getRangeAt(0);
        if (editor.surface.contains(range.commonAncestorContainer)) editor.range = range.cloneRange();
    };

    const insertNode = (editor, node) => {
        restoreRange(editor);
        const selection = window.getSelection();
        const range = selection.getRangeAt(0);
        range.deleteContents();
        range.insertNode(node);
        range.setStartAfter(node); range.collapse(true);
        selection.removeAllRanges(); selection.addRange(range);
        editor.range = range.cloneRange();
        editor.sync();
    };

    const uploadImage = async (editor, file) => {
        if (!file) return;
        const status = editor.status;
        status.textContent = 'Mengunggah gambar...';
        const data = new FormData(); data.append('file', file);
        const response = await fetch(editor.api, {
            method: 'POST', credentials: 'same-origin',
            headers: {Accept: 'application/json', 'X-CSRF-TOKEN': csrf()}, body: data,
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) throw new Error(result?.error?.message || 'Unggah gambar gagal.');
        const item = result.data;
        window.CbtMediaPreview = window.CbtMediaPreview || {};
        window.CbtMediaPreview[item.id] = {kind: item.kind, url: editor.api + '/' + item.id};
        editor.media[String(item.id)] = window.CbtMediaPreview[item.id];
        insertNode(editor, mediaNode(item.id, editor.media));
        status.textContent = 'Gambar tersisip pada bagian ini.';
    };

    const convertMathMl = node => {
        if (!node) return '';
        if (node.nodeType === Node.TEXT_NODE) return node.nodeValue || '';
        if (!(node instanceof Element)) return '';
        const name = node.localName?.toLowerCase() || '';
        const children = () => [...node.childNodes].map(convertMathMl).join('');
        if (['annotation','annotation-xml'].includes(name)) return '';
        if (['math','mrow','semantics'].includes(name)) return children();
        if (['mi','mn','mo','mtext'].includes(name)) return node.textContent || '';
        if (name === 'msup') {
            const c = [...node.children]; return '{' + convertMathMl(c[0]) + '}^{' + convertMathMl(c[1]) + '}';
        }
        if (name === 'msub') {
            const c = [...node.children]; return '{' + convertMathMl(c[0]) + '}_{' + convertMathMl(c[1]) + '}';
        }
        if (name === 'msubsup') {
            const c = [...node.children]; return '{' + convertMathMl(c[0]) + '}_{' + convertMathMl(c[1]) + '}^{' + convertMathMl(c[2]) + '}';
        }
        if (name === 'mfrac') {
            const c = [...node.children]; return '\\frac{' + convertMathMl(c[0]) + '}{' + convertMathMl(c[1]) + '}';
        }
        if (name === 'msqrt') return '\\sqrt{' + children() + '}';
        if (name === 'mroot') {
            const c = [...node.children]; return '\\sqrt[' + convertMathMl(c[1]) + ']{' + convertMathMl(c[0]) + '}';
        }
        if (name === 'mfenced') return '\\left(' + children() + '\\right)';
        if (name === 'mtable') {
            const rows = [...node.children].map(row => [...row.children].map(convertMathMl).join(' & '));
            return '\\begin{matrix}' + rows.join(' \\\\ ') + '\\end{matrix}';
        }
        return children();
    };

    const pasteHtml = (editor, html, plain) => {
        const parsed = new DOMParser().parseFromString(html, 'text/html');
        const fragment = document.createDocumentFragment();
        const walk = source => {
            if (source.nodeType === Node.TEXT_NODE) return document.createTextNode(source.nodeValue || '');
            if (!(source instanceof Element)) return document.createDocumentFragment();
            const name = source.localName?.toLowerCase() || '';
            if (name === 'math') return renderMathNode(convertMathMl(source), false);
            if (['script','style','iframe','object','embed','svg'].includes(name)) return document.createDocumentFragment();
            if (name === 'br') return document.createElement('br');
            let target;
            if (['b','strong'].includes(name)) target = document.createElement('strong');
            else if (['i','em'].includes(name)) target = document.createElement('em');
            else if (name === 'table') {target = document.createElement('table'); target.className='table table-bordered table-sm cbt-rich-table';}
            else if (['tr','td','th'].includes(name)) target = document.createElement(name);
            else if (['p','div'].includes(name)) target = document.createElement('div');
            else target = document.createElement('span');
            for (const child of [...source.childNodes]) target.append(walk(child));
            return target;
        };
        for (const child of [...parsed.body.childNodes]) fragment.append(walk(child));
        if (!fragment.childNodes.length) fragment.append(document.createTextNode(plain || ''));
        restoreRange(editor);
        const selection = window.getSelection(), range = selection.getRangeAt(0);
        range.deleteContents(); range.insertNode(fragment); range.collapse(false);
        selection.removeAllRanges(); selection.addRange(range);
        editor.sync();
    };

    const ensureMathModal = () => {
        if (mathModal) return;
        const wrap = document.createElement('div');
        wrap.innerHTML = `
        <div class="modal fade" id="cbtRichMathModal" tabindex="-1" aria-labelledby="cbtRichMathTitle" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <div><div class="manager-page-kicker mb-1">Rich Content</div><h2 class="modal-title fs-5" id="cbtRichMathTitle">Sisipkan Rumus</h2></div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
              </div>
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="cbt-form-label" for="cbtMathType">Bentuk Rumus</label>
                    <select class="form-select" id="cbtMathType">
                      <option value="SIMPLE">Ekspresi sederhana</option>
                      <option value="FRACTION">Pecahan</option>
                      <option value="POWER">Pangkat</option>
                      <option value="ROOT">Akar</option>
                      <option value="SIGMA">Sigma</option>
                      <option value="INTEGRAL">Integral</option>
                      <option value="MATRIX2">Matriks 2 × 2</option>
                    </select>
                  </div>
                  <div class="col-md-8" id="cbtMathFields"></div>
                </div>
                <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="cbtMathBlock"><label class="form-check-label" for="cbtMathBlock">Tampilkan sebagai rumus blok/besar</label></div>
                <div class="border rounded p-3 mt-3 cbt-rich-math-preview" id="cbtMathPreview">Pratinjau rumus</div>
                <p class="small text-secondary mt-2 mb-0">Untuk kebutuhan umum, pilih bentuk rumus dan isi kotaknya. Ekspresi sederhana menerima penulisan matematika linear seperti x^2 + 3x + 2.</p>
              </div>
              <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-cbt-primary" type="button" id="cbtMathInsert">Sisipkan Rumus</button></div>
            </div>
          </div>
        </div>`;
        document.body.append(wrap.firstElementChild);
        const modalEl = document.getElementById('cbtRichMathModal');
        mathModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const type = document.getElementById('cbtMathType');
        type.addEventListener('change', renderMathFields);
        document.getElementById('cbtMathBlock').addEventListener('change', updateMathPreview);
        document.getElementById('cbtMathInsert').addEventListener('click', () => {
            const latex = mathLatex();
            if (!latex || !mathState?.editor) return;
            insertNode(mathState.editor, renderMathNode(latex, document.getElementById('cbtMathBlock').checked));
            mathModal.hide();
        });
        renderMathFields();
    };

    const inputField = (id, label, placeholder = '') => {
        const wrap = document.createElement('div'); wrap.className='mb-2';
        const l = document.createElement('label'); l.className='cbt-form-label'; l.htmlFor=id; l.textContent=label;
        const input=document.createElement('input'); input.className='form-control'; input.id=id; input.placeholder=placeholder;
        input.addEventListener('input', updateMathPreview); wrap.append(l,input); return wrap;
    };

    function renderMathFields() {
        const type = document.getElementById('cbtMathType')?.value || 'SIMPLE';
        const target = document.getElementById('cbtMathFields'); if (!target) return;
        target.replaceChildren();
        const fields = {
            SIMPLE:[['cbtMathA','Ekspresi','Contoh: x^2 + 3x + 2']],
            FRACTION:[['cbtMathA','Pembilang','Contoh: x + 1'],['cbtMathB','Penyebut','Contoh: x - 1']],
            POWER:[['cbtMathA','Bilangan/variabel','Contoh: x'],['cbtMathB','Pangkat','Contoh: 2']],
            ROOT:[['cbtMathA','Isi akar','Contoh: x + 1']],
            SIGMA:[['cbtMathA','Batas bawah','Contoh: i=1'],['cbtMathB','Batas atas','Contoh: 10'],['cbtMathC','Ekspresi','Contoh: i']],
            INTEGRAL:[['cbtMathA','Batas bawah','Contoh: 0'],['cbtMathB','Batas atas','Contoh: 1'],['cbtMathC','Fungsi','Contoh: x^2'],['cbtMathD','Variabel','Contoh: x']],
            MATRIX2:[['cbtMathA','Baris 1 kolom 1','a'],['cbtMathB','Baris 1 kolom 2','b'],['cbtMathC','Baris 2 kolom 1','c'],['cbtMathD','Baris 2 kolom 2','d']],
        }[type] || [];
        for (const [id,label,placeholder] of fields) target.append(inputField(id,label,placeholder));
        updateMathPreview();
    }

    function mathLatex() {
        const type=document.getElementById('cbtMathType')?.value || 'SIMPLE';
        const v=id=>document.getElementById(id)?.value.trim() || '';
        if (type==='SIMPLE') return v('cbtMathA');
        if (type==='FRACTION') return v('cbtMathA') && v('cbtMathB') ? '\\frac{' + v('cbtMathA') + '}{' + v('cbtMathB') + '}' : '';
        if (type==='POWER') return v('cbtMathA') && v('cbtMathB') ? '{' + v('cbtMathA') + '}^{' + v('cbtMathB') + '}' : '';
        if (type==='ROOT') return v('cbtMathA') ? '\\sqrt{' + v('cbtMathA') + '}' : '';
        if (type==='SIGMA') return v('cbtMathC') ? '\\sum_{' + v('cbtMathA') + '}^{' + v('cbtMathB') + '} ' + v('cbtMathC') : '';
        if (type==='INTEGRAL') return v('cbtMathC') ? '\\int_{' + v('cbtMathA') + '}^{' + v('cbtMathB') + '} ' + v('cbtMathC') + '\\, d' + (v('cbtMathD') || 'x') : '';
        if (type==='MATRIX2') return '\\begin{bmatrix}' + v('cbtMathA') + ' & ' + v('cbtMathB') + ' \\\\ ' + v('cbtMathC') + ' & ' + v('cbtMathD') + '\\end{bmatrix}';
        return '';
    }

    function updateMathPreview() {
        const preview=document.getElementById('cbtMathPreview'); if (!preview) return;
        const latex=mathLatex(); preview.replaceChildren();
        if (!latex) {preview.textContent='Isi komponen rumus untuk melihat pratinjau.'; return;}
        preview.append(renderMathNode(latex, document.getElementById('cbtMathBlock')?.checked));
    }

    const openMath = editor => {
        ensureMathModal();
        mathState = {editor};
        document.getElementById('cbtMathType').value='SIMPLE';
        document.getElementById('cbtMathBlock').checked=false;
        renderMathFields();
        mathModal.show();
        setTimeout(()=>document.getElementById('cbtMathA')?.focus(),150);
    };

    const mount = (textarea, options = {}) => {
        if (!(textarea instanceof HTMLTextAreaElement)) throw new Error('Rich editor membutuhkan textarea.');
        const wrapper=document.createElement('div'); wrapper.className='cbt-rich-editor';
        const toolbar=document.createElement('div'); toolbar.className='cbt-rich-toolbar';
        const surface=document.createElement('div'); surface.className='cbt-rich-surface'; surface.contentEditable='true';
        surface.setAttribute('role','textbox'); surface.setAttribute('aria-multiline','true');
        const status=document.createElement('div'); status.className='small text-secondary mt-1';
        const file=document.createElement('input'); file.type='file'; file.accept='image/jpeg,image/png,image/webp'; file.hidden=true;

        const editor={
            textarea, wrapper, toolbar, surface, status, file, api:options.api || '', media:{...(options.media||{})}, range:null,
            sync() {
                textarea.value=serialize(surface);
                textarea.dispatchEvent(new Event('input',{bubbles:true}));
                if (typeof options.onChange==='function') options.onChange(textarea.value);
            },
            setValue(value, media={}) {this.media={...media}; loadValue(surface,value,this.media); textarea.value=escapeText(value);},
            getValue(){this.sync(); return textarea.value;},
            setDisabled(disabled){surface.contentEditable=disabled?'false':'true'; toolbar.querySelectorAll('button').forEach(b=>b.disabled=disabled);},
            destroy(){wrapper.remove(); textarea.hidden=false; editorRegistry.delete(editor);},
        };

        const button=(label,title,action)=>{
            const b=document.createElement('button'); b.type='button'; b.className='btn btn-outline-secondary btn-sm';
            b.textContent=label; b.title=title;
            b.addEventListener('mousedown',()=>rememberRange(editor));
            b.addEventListener('click',action); toolbar.append(b); return b;
        };
        button('B','Tebal',()=>{restoreRange(editor); document.execCommand('bold'); editor.sync();});
        button('I','Miring',()=>{restoreRange(editor); document.execCommand('italic'); editor.sync();});
        button('fx','Rumus visual',()=>openMath(editor));
        button('🖼','Sisipkan gambar',()=>{rememberRange(editor); file.click();});
        button('Tabel','Sisipkan tabel 2 × 2',()=>{
            const table=document.createElement('table'); table.className='table table-bordered table-sm cbt-rich-table';
            for(let r=0;r<2;r++){const tr=document.createElement('tr');for(let c=0;c<2;c++){const cell=document.createElement(r===0?'th':'td');cell.textContent=r===0?'Judul '+(c+1):'Isi';tr.append(cell);}table.append(tr);}
            insertNode(editor,table);
        });

        file.addEventListener('change',async()=>{
            try{await uploadImage(editor,file.files?.[0]);}
            catch(error){status.textContent=error.message;}
            finally{file.value='';}
        });
        surface.addEventListener('input',()=>editor.sync());
        surface.addEventListener('keyup',()=>rememberRange(editor));
        surface.addEventListener('mouseup',()=>rememberRange(editor));
        surface.addEventListener('focus',()=>{activeEditor=editor;});
        surface.addEventListener('paste',async event=>{
            const files=[...(event.clipboardData?.files||[])];
            const image=files.find(f=>f.type.startsWith('image/'));
            if(image){
                event.preventDefault(); rememberRange(editor);
                try{await uploadImage(editor,image);}catch(error){status.textContent=error.message;}
                return;
            }
            const html=event.clipboardData?.getData('text/html')||'';
            if(html){
                event.preventDefault(); rememberRange(editor);
                pasteHtml(editor,html,event.clipboardData?.getData('text/plain')||'');
            }
        });

        textarea.hidden=true;
        textarea.parentNode.insertBefore(wrapper,textarea.nextSibling);
        wrapper.append(toolbar,surface,status,file);
        editor.setValue(textarea.value,editor.media);
        editorRegistry.add(editor);
        return editor;
    };

    window.CbtRichEditor=Object.freeze({mount, get active(){return activeEditor;}});
})();