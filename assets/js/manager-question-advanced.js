(() => {
    'use strict';
    const app = document.getElementById('advancedApp'); if (!app) return;
    const $ = id => document.getElementById(id);
    const base = app.dataset.api;
    const labels = {PG_KOMPLEKS:'PG Kompleks', PG_BERTINGKAT:'PG Bertingkat', MATCHING:'Menjodohkan', ISIAN_SINGKAT:'Isian Singkat', URAIAN:'Uraian'};
    const types = Object.keys(labels);
    const state = {page:1, pages:1, seq:0, editable:false, configured:[], editing:null, revision:null, options:[], pairs:[], answers:[]};
    const feedback = (id, message, error = false) => {
        const el = $(id); el.textContent = message;
        el.className = 'cbt-inline-feedback' + (message ? error ? ' is-error' : ' is-info' : '');
    };
    const api = async (url, method='GET', payload=null) => {
        const headers = {Accept:'application/json'};
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
        if (payload !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch(url, {method, credentials:'same-origin', headers,
            ...(payload === null ? {} : {body:JSON.stringify(payload)})});
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.ok !== true) throw new Error(result?.error?.message ?? 'Permintaan gagal.');
        return result.data;
    };
    const el = (tag, text, className='') => {
        const item = document.createElement(tag); item.textContent = text; item.className = className; return item;
    };
    const field = (label, value, changed, {kind='text', width='col-md-8', min, max}={}) => {
        const wrapper = el('div','',width); const title = el('label',label,'cbt-form-label');
        const input = document.createElement(kind === 'textarea' ? 'textarea' : 'input');
        input.className = 'form-control form-control-sm';
        if (kind !== 'textarea') input.type = kind;
        if (kind === 'textarea') input.rows = 2;
        if (min !== undefined) input.min = String(min);
        if (max !== undefined) input.max = String(max);
        input.value = value ?? ''; input.disabled = !state.editable;
        input.addEventListener('input', () => {changed(input.value); preview();});
        title.append(input); wrapper.append(title); return wrapper;
    };
    const addButton = (text, action, disabled) => {
        const button = el('button',text,'btn btn-outline-primary btn-sm');
        button.type = 'button'; button.disabled = disabled || !state.editable;
        button.addEventListener('click', action); return button;
    };
    const rowRemove = (action, disabled) => {
        const button = addButton('Hapus',action,disabled); button.className = 'btn btn-outline-danger btn-sm align-self-end mb-1'; return button;
    };
    const current = () => {
        const type = $('advancedType').value;
        const data = {question_type:type, question_text:$('advancedQuestion').value,
            stimulus_text:$('advancedStimulus').value, max_point:$('advancedPoint').value,
            media:{...(state.media||{}),...(window.CbtMediaPreview||{})}};
        if (type === 'PG_KOMPLEKS') data.options = state.options.map(item => ({text:item.text, correct:item.correct}));
        if (type === 'PG_BERTINGKAT') data.options = state.options.map(item => ({text:item.text, point_value:item.point_value}));
        if (type === 'MATCHING') {data.pairs = state.pairs.map(item => ({left:item.left, right:item.right})); data.scoring_mode = $('advancedMode').value;}
        if (type === 'ISIAN_SINGKAT') {
            data.short_answer_mode = $('advancedMode').value;
            if (data.short_answer_mode === 'TEXT') data.accepted_values = [...state.answers];
            else {data.expected_numeric = $('advancedExpected').value; data.numeric_tolerance = $('advancedTolerance').value;}
        }
        if (type === 'URAIAN') data.rubric_text = $('advancedRubric').value;
        if (state.editing !== null) data.expected_revision = state.revision;
        return data;
    };
    const preview = () => {
        const data = current();
        data.options = (data.options || []).map((item,i) => ({option_key:String.fromCharCode(65+i), content_text:item.text,
            is_correct:item.correct ? 1 : 0, point_value:item.point_value}));
        data.pairs = (data.pairs || []).map((item,i) => ({left_key:String(i+1), left_text:item.left, right_text:item.right}));
        $('advancedPreview').replaceChildren(window.CbtQuestionRenderer.render(data,{showAnswer:true}));
    };
    const renderSpecific = () => {
        const type = $('advancedType').value; const target = $('advancedSpecific'); target.replaceChildren();
        if (type === 'PG_KOMPLEKS' || type === 'PG_BERTINGKAT') {
            target.append(el('p',type === 'PG_KOMPLEKS' ? 'Centang semua opsi yang benar.' : 'Tetapkan nilai setiap opsi (0 hingga poin maksimal).','text-secondary'));
            state.options.forEach((item,i) => {
                const row = el('div','','row g-2 align-items-end mb-2');
                row.append(field('Opsi ' + String.fromCharCode(65+i),item.text,value => item.text=value,{kind:'textarea'}));
                if (type === 'PG_KOMPLEKS') {
                    const label = el('label','Benar','col-md-2 form-check-label');
                    const check = document.createElement('input'); check.type='checkbox'; check.className='form-check-input ms-2';
                    check.checked = Boolean(item.correct); check.disabled = !state.editable;
                    check.addEventListener('change',()=>{item.correct=check.checked; preview();}); label.append(check); row.append(label);
                } else row.append(field('Poin',item.point_value ?? '0',value=>item.point_value=value,{kind:'number',width:'col-md-2',min:0,max:1000}));
                row.append(rowRemove(()=>{state.options.splice(i,1); renderSpecific();},state.options.length<=2)); target.append(row);
            });
            target.append(addButton('Tambah opsi',()=>{state.options.push({text:'',correct:false,point_value:'0'}); renderSpecific();},state.options.length>=8));
        } else if (type === 'MATCHING') {
            const label = el('label','Penilaian','cbt-form-label d-block'); const select = document.createElement('select');
            select.id='advancedMode'; select.className='form-select mb-3'; select.add(new Option('Per pasangan','PARTIAL'));
            select.add(new Option('Semua benar','ALL_OR_NOTHING')); select.value=state.mode||'PARTIAL'; select.disabled=!state.editable;
            select.disabled=true; label.append(select); target.append(label);
            state.pairs.forEach((item,i)=>{
                const row=el('div','','row g-2 align-items-end mb-2');
                row.append(field('Sisi kiri ' +(i+1),item.left,value=>item.left=value,{kind:'textarea',width:'col-md-5'}),
                    field('Pasangan kanan',item.right,value=>item.right=value,{kind:'textarea',width:'col-md-5'}),
                    rowRemove(()=>{state.pairs.splice(i,1); renderSpecific();},state.pairs.length<=2)); target.append(row);
            });
            target.append(addButton('Tambah pasangan',()=>{state.pairs.push({left:'',right:''}); renderSpecific();},state.pairs.length>=12));
        } else if (type === 'ISIAN_SINGKAT') {
            const label=el('label','Mode jawaban','cbt-form-label d-block'); const select=document.createElement('select');
            select.id='advancedMode'; select.className='form-select mb-3'; select.add(new Option('Teks','TEXT')); select.add(new Option('Angka','NUMERIC'));
            select.value=state.mode||'TEXT'; select.disabled=!state.editable;
            select.addEventListener('change',()=>{state.mode=select.value; renderSpecific();}); label.append(select); target.append(label);
            if (select.value==='TEXT') {
                state.answers.forEach((value,i)=>{
                    const row=el('div','','row g-2 align-items-end mb-2');
                    row.append(field('Jawaban diterima ' +(i+1),value,v=>state.answers[i]=v),
                        rowRemove(()=>{state.answers.splice(i,1); renderSpecific();},state.answers.length<=1)); target.append(row);
                });
                target.append(addButton('Tambah jawaban diterima',()=>{state.answers.push(''); renderSpecific();},state.answers.length>=20));
            } else {
                const row=el('div','','row g-2 mb-3');
                const expected=field('Angka harapan',state.expected||'',v=>state.expected=v,{width:'col-md-4'});
                const tolerance=field('Toleransi',state.tolerance||'0',v=>state.tolerance=v,{width:'col-md-4'});
                expected.querySelector('input').id='advancedExpected'; tolerance.querySelector('input').id='advancedTolerance';
                row.append(expected,tolerance); target.append(row);
            }
        } else if (type === 'URAIAN') {
            const rub=field('Rubrik penilaian untuk pemeriksa',state.rubric||'',v=>state.rubric=v,{kind:'textarea',width:'col-12'});
            rub.querySelector('textarea').id='advancedRubric'; target.append(rub);
        }
        preview();
    };
    const open = (item=null) => {
        state.editing=item ? Number(item.id) : null; state.revision=item ? Number(item.current_revision_no) : null;
        $('advancedType').replaceChildren(...state.configured.map(t=>new Option(labels[t],t)));
        $('advancedType').value=item?.question_type || ($('advancedFilter').value && state.configured.includes($('advancedFilter').value) ? $('advancedFilter').value : state.configured[0]);
        $('advancedType').disabled=Boolean(item)||!state.editable;
        $('advancedQuestion').value=item?.question_text||''; $('advancedStimulus').value=item?.stimulus_text||'';
        $('advancedPoint').value=item?.max_point||'1';
        state.options=(item?.options||[{},{},{},{}]).map(o=>({text:o.content_text||'',correct:Number(o.is_correct)===1,point_value:o.point_value||'0'}));
        state.pairs=(item?.pairs||[{},{}]).map(p=>({left:p.left_text||'',right:p.right_text||''}));
        state.answers=item?.accepted_values?.length ? [...item.accepted_values] : [''];
        state.mode=item?.short_answer_mode||item?.scoring_mode||($('advancedType').value==='MATCHING'
            ? state.configMap.get('MATCHING')?.scoring_mode||'PARTIAL':'TEXT');
        state.media=item?.media||window.CbtMediaPreview||{}; state.expected=item?.expected_numeric||'';
        state.tolerance=item?.numeric_tolerance||'0'; state.rubric=item?.rubric_text||'';
        for (const id of ['advancedQuestion','advancedStimulus','advancedPoint']) $(id).disabled=!state.editable;
        $('advancedSave').hidden=!state.editable;
        $('advancedTitle').textContent=item ? labels[item.question_type]+' · Revisi '+state.revision : 'Tambah Soal Akademik';
        feedback('advancedFormFeedback',''); $('advancedEditor').hidden=false; renderSpecific();
        $('advancedEditor').scrollIntoView({behavior:'smooth'});
    };
    const load=async()=>{
        const seq=++state.seq; feedback('advancedFeedback','Memuat soal...');
        try {
            const data=await api(base+'?'+new URLSearchParams({type:$('advancedFilter').value||'ADVANCED',page:state.page,per_page:$('advancedSize').value}));
            if (seq!==state.seq) return;
            state.editable=data.bank.status==='DRAFT'&&data.bank.kegiatan_status==='DRAFT';
            $('advancedContext').textContent=data.bank.nama_bank+' · '+data.bank.kegiatan_nama+' · '+data.bank.mapel_nama+' · Tingkat '+data.bank.tingkat+' · '+data.bank.status;
            $('advancedAdd').disabled=!state.editable||!state.configured.length;
            const body=$('advancedRows'); body.replaceChildren();
            for (const item of data.items) {
                const row=document.createElement('tr'), action=el('td','');
                const view=addButton(state.editable?'Edit / Pratinjau':'Lihat',async()=>{
                    try {const detail=await api(base+'/'+item.id); open(detail.item);}
                    catch (error) {feedback('advancedFeedback',error.message,true);}
                },false); view.disabled=false; action.append(view);
                if (state.editable) action.append(rowRemove(async()=>{
                    if (!window.confirm('Hapus soal ini beserta semua revisinya?')) return;
                    try {await api(base+'/'+item.id,'DELETE'); $('advancedEditor').hidden=true; await load();}
                    catch (error) {feedback('advancedFeedback',error.message,true);}
                },false));
                row.append(el('td',item.sort_order),el('td',labels[item.question_type]||item.question_type),
                    el('td',(item.question_text||'').slice(0,160)),el('td',item.current_revision_no),action); body.append(row);
            }
            if (!body.children.length) {const row=el('tr',''),cell=el('td','Belum ada soal tipe ini.','text-center text-secondary py-4'); cell.colSpan=5; row.append(cell); body.append(row);}
            state.page=Number(data.pagination.page); state.pages=Number(data.pagination.pages);
            $('advancedCount').textContent=data.pagination.total+' soal'; $('advancedPage').textContent=state.page+' / '+state.pages;
            $('advancedPrev').disabled=state.page<=1; $('advancedNext').disabled=state.page>=state.pages;
            feedback('advancedFeedback',state.configured.length?'':'Aktifkan tipe pada Komposisi Bank terlebih dahulu.',!state.configured.length);
        } catch(error) {if(seq===state.seq) feedback('advancedFeedback',error.message,true);}
    };
    $('advancedType').addEventListener('change',()=>{state.options=[{text:'',correct:false,point_value:'0'},{text:'',correct:false,point_value:'0'}];state.pairs=[{left:'',right:''},{left:'',right:''}];state.answers=[''];state.mode=$('advancedType').value==='MATCHING'?state.configMap.get('MATCHING')?.scoring_mode||'PARTIAL':'TEXT';renderSpecific();});
    for(const id of ['advancedQuestion','advancedStimulus','advancedPoint']) $(id).addEventListener('input',preview);
    $('advancedAdd').addEventListener('click',()=>open()); $('advancedCancel').addEventListener('click',()=>{$('advancedEditor').hidden=true;});
    $('advancedForm').addEventListener('submit',async event=>{
        event.preventDefault(); const payload=current(), button=$('advancedSave'); button.disabled=true;
        try {const data=await api(state.editing===null?base:base+'/'+state.editing,state.editing===null?'POST':'PUT',payload);
            $('advancedEditor').hidden=true; state.page=1; await load(); feedback('advancedFeedback','Soal tersimpan pada revisi '+data.item.current_revision_no+'.');}
        catch(error) {feedback('advancedFormFeedback',error.message,true);} finally {button.disabled=false;}
    });
    $('advancedFilter').addEventListener('change',()=>{state.page=1;load();});
    $('advancedSize').addEventListener('change',()=>{state.page=1;load();});
    $('advancedPrev').addEventListener('click',()=>{if(state.page>1){state.page--;load();}});
    $('advancedNext').addEventListener('click',()=>{if(state.page<state.pages){state.page++;load();}});
    api(app.dataset.config).then(data=>{
        state.configMap=new Map(data.items.map(i=>[i.question_type,i]));
        state.configured=data.items.map(i=>i.question_type).filter(t=>types.includes(t));
        $('advancedFilter').replaceChildren(new Option('Semua tipe lanjutan','ADVANCED'),
            ...types.map(t=>new Option(labels[t],t)));
        load();
    }).catch(error=>feedback('advancedFeedback',error.message,true));
})();
