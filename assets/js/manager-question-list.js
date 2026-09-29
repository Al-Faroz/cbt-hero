(() => {
    'use strict';
    const app = document.getElementById('questionListApp'); if (!app) return;
    const $ = id => document.getElementById(id);
    const base = app.dataset.api, configApi = app.dataset.config, preflightApi = app.dataset.preflight, mediaApi = app.dataset.mediaApi;
    const labels = {
        PG:'Pilihan Ganda', PG_KOMPLEKS:'PG Kompleks', PG_BERTINGKAT:'PG Bertingkat',
        MATCHING:'Menjodohkan', ISIAN_SINGKAT:'Isian Singkat', URAIAN:'Uraian',
    };
    const order = ['PG','PG_KOMPLEKS','PG_BERTINGKAT','MATCHING','ISIAN_SINGKAT','URAIAN'];
    const state = {
        configMap:new Map(), configured:[], activeType:null, bank:null, editable:false, liveEditing:false,
        editing:null, revision:null, options:[], pairs:[], answers:[], mode:'TEXT',
        expected:'', tolerance:'0', rubric:'', media:{}, selected:new Set(), deleteIds:[],
        page:1, pages:1, total:0, filtered:0, seq:0,
        richQuestion:null, richStimulus:null, dynamicEditors:[],
    };

    const info = {
        PG:{
            intro:'Gunakan Pilihan Ganda ketika peserta harus memilih satu jawaban yang paling tepat dari beberapa pilihan.',
            sections:[
                {title:'Cara mengisi',items:[
                    'Tulis pertanyaan dengan jelas. Jika perlu, tambahkan gambar, rumus, tabel, audio, atau video.',
                    'Isi semua pilihan jawaban yang tersedia. Jumlah pilihan mengikuti pengaturan Komposisi Bank.',
                    'Tandai tepat satu pilihan sebagai jawaban benar.'
                ]},
                {title:'Nilai',items:[
                    'Poin maksimum menentukan nilai penuh untuk soal ini.',
                    'Peserta mendapat nilai penuh jika memilih jawaban yang benar.'
                ]},
                {title:'Contoh',items:[
                    'Pertanyaan: Ibu kota Indonesia adalah ....',
                    'Pilihan: Jakarta, Bandung, Surabaya, Medan. Tandai Jakarta sebagai jawaban benar.'
                ]},
                {title:'Perhatikan',items:[
                    'Jangan memberi lebih dari satu jawaban benar.',
                    'Pastikan semua pilihan sudah terisi sebelum menyimpan.'
                ]}
            ]
        },
        PG_KOMPLEKS:{
            intro:'Gunakan PG Kompleks ketika satu pertanyaan dapat mempunyai lebih dari satu jawaban benar.',
            sections:[
                {title:'Cara mengisi',items:[
                    'Tulis pertanyaan dan semua pilihan jawaban. Jumlah pilihan mengikuti Komposisi Bank.',
                    'Centang setiap pilihan yang benar.',
                    'Harus ada sedikitnya satu pilihan benar dan sedikitnya satu pilihan salah.'
                ]},
                {title:'Nilai',items:[
                    'Poin maksimum menentukan nilai penuh untuk soal ini.',
                    'Jawaban peserta dinilai berdasarkan aturan PG Kompleks yang digunakan pada ujian.'
                ]},
                {title:'Contoh',items:[
                    'Pertanyaan: Manakah yang termasuk hewan mamalia?',
                    'Pilihan: Kucing, Ayam, Sapi, Ikan. Tandai Kucing dan Sapi sebagai jawaban benar.'
                ]},
                {title:'Perhatikan',items:[
                    'Jangan menandai semua pilihan sebagai benar.',
                    'Jangan sampai tidak ada pilihan yang benar.'
                ]}
            ]
        },
        PG_BERTINGKAT:{
            intro:'Gunakan PG Bertingkat ketika setiap pilihan dapat mempunyai nilai yang berbeda, misalnya jawaban paling tepat mendapat nilai tertinggi dan jawaban yang kurang tepat mendapat nilai lebih kecil.',
            sections:[
                {title:'Cara mengisi',items:[
                    'Tulis pertanyaan dan semua pilihan jawaban. Jumlah pilihan mengikuti Komposisi Bank.',
                    'Isi nilai untuk setiap pilihan, mulai dari 0 sampai Poin Maksimum.',
                    'Sedikitnya harus ada satu pilihan yang mempunyai nilai lebih dari 0.'
                ]},
                {title:'Nilai',items:[
                    'Nilai peserta mengikuti nilai pada pilihan yang dipilih.',
                    'Nilai setiap pilihan tidak boleh lebih besar dari Poin Maksimum soal.'
                ]},
                {title:'Contoh',items:[
                    'Poin Maksimum 4. Pilihan A bernilai 4, B bernilai 3, C bernilai 1, dan D bernilai 0.'
                ]},
                {title:'Perhatikan',items:[
                    'Tipe ini tidak memakai satu kunci jawaban benar seperti Pilihan Ganda biasa.',
                    'Periksa kembali nilai setiap pilihan sebelum menyimpan.'
                ]}
            ]
        },
        MATCHING:{
            intro:'Gunakan Menjodohkan ketika peserta harus memasangkan bagian di sebelah kiri dengan pasangan yang tepat di sebelah kanan.',
            sections:[
                {title:'Cara mengisi',items:[
                    'Isi setiap bagian kiri dan pasangan kanannya.',
                    'Jumlah pasangan mengikuti Komposisi Bank.',
                    'Isi pada sisi kiri tidak boleh sama satu sama lain. Isi pada sisi kanan juga tidak boleh sama satu sama lain.'
                ]},
                {title:'Nilai',items:[
                    'Cara penilaian mengikuti Komposisi Bank.',
                    'Jika menggunakan Per Pasangan, pasangan yang benar dapat memperoleh nilai walaupun pasangan lain salah.',
                    'Jika menggunakan Semua Benar, nilai penuh diberikan jika seluruh pasangan benar.'
                ]},
                {title:'Contoh',items:[
                    'Kiri: Indonesia, Jepang, Thailand. Kanan pasangannya: Jakarta, Tokyo, Bangkok.'
                ]},
                {title:'Perhatikan',items:[
                    'Pastikan setiap bagian kiri mempunyai tepat satu pasangan kanan.',
                    'Gambar juga dapat digunakan pada bagian kiri maupun kanan.'
                ]}
            ]
        },
        ISIAN_SINGKAT:{
            intro:'Gunakan Isian Singkat ketika peserta harus mengetik jawaban pendek, berupa kata/kalimat singkat atau angka.',
            sections:[
                {title:'Jika jawabannya berupa teks',items:[
                    'Pilih mode Teks.',
                    'Masukkan satu atau lebih jawaban yang boleh dianggap benar.',
                    'Contoh: untuk pertanyaan tentang ibu kota Indonesia, Anda dapat cukup memasukkan Jakarta sebagai jawaban yang diterima.'
                ]},
                {title:'Jika jawabannya berupa angka',items:[
                    'Pilih mode Angka.',
                    'Isi jawaban angka yang diharapkan.',
                    'Isi toleransi jika jawaban boleh sedikit berbeda. Toleransi 0 berarti jawaban harus tepat.'
                ]},
                {title:'Contoh angka',items:[
                    'Jawaban yang diharapkan 10 dengan toleransi 0,5 berarti jawaban dari 9,5 sampai 10,5 dapat diterima.'
                ]},
                {title:'Perhatikan',items:[
                    'Jangan memasukkan jawaban teks yang sama berulang kali.',
                    'Gunakan Uraian jika jawaban peserta membutuhkan penjelasan panjang.'
                ]}
            ]
        },
        URAIAN:{
            intro:'Gunakan Uraian ketika peserta perlu menjawab dengan penjelasan, langkah pengerjaan, alasan, atau jawaban panjang yang dinilai oleh pemeriksa.',
            sections:[
                {title:'Cara mengisi',items:[
                    'Tulis pertanyaan dengan jelas dan lengkap.',
                    'Isi Pedoman Penilaian agar pemeriksa mempunyai acuan saat memberi nilai.',
                    'Tentukan Poin Maksimum sesuai nilai tertinggi yang dapat diperoleh peserta.'
                ]},
                {title:'Pedoman Penilaian',items:[
                    'Tuliskan unsur jawaban yang diharapkan, langkah penting, atau pembagian nilai.',
                    'Pedoman dapat berisi teks, gambar, rumus, atau tabel jika diperlukan.'
                ]},
                {title:'Contoh',items:[
                    'Poin Maksimum 5: ketepatan jawaban 2 poin, langkah pengerjaan 2 poin, dan kesimpulan 1 poin.'
                ]},
                {title:'Perhatikan',items:[
                    'Soal Uraian tidak dinilai otomatis seperti Pilihan Ganda.',
                    'Buat pedoman yang cukup jelas agar penilaian antar pemeriksa tetap konsisten.'
                ]}
            ]
        }
    };

    const commonInfo = [
        {title:'Menulis isi soal',items:[
            'Gunakan tombol B untuk tulisan tebal dan I untuk tulisan miring.',
            'Gunakan tombol fx jika ingin menambahkan rumus. Pilih bentuk rumus lalu isi bagian yang diperlukan.',
            'Gunakan tombol Gambar untuk menambahkan gambar pada bagian yang sedang diisi.',
            'Gunakan tombol Tabel jika soal membutuhkan tabel sederhana.',
            'Huruf Arab, aksara Jawa, dan huruf lainnya dapat diketik atau ditempel seperti teks biasa.'
        ]},
        {title:'Audio dan video',items:[
            'Simpan file audio atau video di Google Drive dan atur agar siapa saja yang mempunyai link dapat melihat file tersebut.',
            'Ketik Audio: lalu tempel link Google Drive jika ingin menambahkan audio.',
            'Ketik Video: lalu tempel link Google Drive jika ingin menambahkan video.',
            'Sebelum digunakan dalam ujian, lihat Pratinjau dan pastikan audio atau video dapat diputar.'
        ]},
        {title:'Jika soal dibuat dari Microsoft Word',items:[
            'Teks dari Word dapat disalin lalu ditempel ke kotak soal.',
            'Untuk rumus di Word, gunakan menu Insert → Equation seperti biasa.',
            'Untuk banyak soal sekaligus, lebih nyaman menggunakan Template Word dari menu Impor Soal.'
        ]},
        {title:'Sebelum menyimpan',items:[
            'Lihat Pratinjau untuk memastikan tulisan, gambar, rumus, audio, video, dan jawaban sudah benar.',
            'Pastikan tidak ada bagian penting yang masih kosong.'
        ]}
    ];

    const feedback = (id,message,error=false) => {
        const el=$(id); el.textContent=message;
        el.className='cbt-inline-feedback'+(message?(error?' is-error':' is-info'):'');
    };
    const api = async (url,method='GET',payload=null) => {
        const headers={Accept:'application/json'};
        if(method!=='GET') headers['X-CSRF-TOKEN']=document.querySelector('meta[name="csrf-token"]').content;
        if(payload!==null) headers['Content-Type']='application/json';
        const response=await fetch(url,{method,credentials:'same-origin',headers,...(payload===null?{}:{body:JSON.stringify(payload)})});
        const result=await response.json().catch(()=>null);
        if(!response.ok||result?.ok!==true) throw new Error(result?.error?.message||'Permintaan gagal.');
        return result.data;
    };
    const esc = value => String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    const numberLabel = value => {
        const n=Number(value); return Number.isFinite(n)?n.toFixed(4).replace(/\.?0+$/,''):String(value??'');
    };
    const summaryText = value => String(value??'')
        .replace(/\[\[media:[^\]]+\]\]/g,'[Gambar]')
        .replace(/\$\$[^$]+\$\$/g,'[Rumus]')
        .replace(/\$[^$]+\$/g,'[Rumus]')
        .replace(/\*\*/g,'').replace(/(?<!\*)\*(?!\*)/g,'')
        .replace(/\bAudio\s*:\s*https?:\/\/\S+/gi,'[Audio]')
        .replace(/\bVideo\s*:\s*https?:\/\/\S+/gi,'[Video]')
        .replace(/\s+/g,' ').trim();

    const clearDynamicEditors = () => {
        for(const editor of state.dynamicEditors) editor.destroy?.();
        state.dynamicEditors=[];
    };

    const editorEditable = () => state.editable || state.liveEditing;

    const makeRichField = (label,value,onChange,{width='col-12',rows=3}={}) => {
        const col=document.createElement('div'); col.className=width;
        const lab=document.createElement('label'); lab.className='cbt-form-label'; lab.textContent=label;
        const area=document.createElement('textarea'); area.className='form-control'; area.rows=rows; area.value=value??'';
        col.append(lab,area);
        const editor=window.CbtRichEditor.mount(area,{api:mediaApi,media:state.media,onChange});
        editor.setDisabled(!editorEditable()); state.dynamicEditors.push(editor);
        return col;
    };

    const preview = () => {
        if(!$('questionEditor') || $('questionEditor').hidden) return;
        try {
            const type=state.activeType;
            const data={
                question_type:type, question_text:state.richQuestion?.getValue()||'',
                stimulus_text:type==='PG'?'':state.richStimulus?.getValue()||'',
                max_point:$('questionPoint').value,
                media:{...state.media,...(window.CbtMediaPreview||{})},
            };
            if(type==='PG') data.options=state.options.map((o,i)=>({option_key:String.fromCharCode(65+i),content_text:o.text,is_correct:o.correct?1:0}));
            if(type==='PG_KOMPLEKS') data.options=state.options.map((o,i)=>({option_key:String.fromCharCode(65+i),content_text:o.text,is_correct:o.correct?1:0}));
            if(type==='PG_BERTINGKAT') data.options=state.options.map((o,i)=>({option_key:String.fromCharCode(65+i),content_text:o.text,point_value:o.point_value}));
            if(type==='MATCHING') data.pairs=state.pairs.map((p,i)=>({left_key:String(i+1),left_text:p.left,right_text:p.right}));
            if(type==='ISIAN_SINGKAT') {
                data.short_answer_mode=state.mode; data.accepted_values=state.answers;
                data.expected_numeric=state.expected; data.numeric_tolerance=state.tolerance;
            }
            if(type==='URAIAN') data.rubric_text=state.rubric;
            $('questionPreview').replaceChildren(window.CbtQuestionRenderer.render(data,{showAnswer:true}));
        } catch(_) {}
    };

    const renderSpecific = () => {
        clearDynamicEditors();
        const type=state.activeType, target=$('questionSpecific'); target.replaceChildren();
        const config=state.configMap.get(type)||{}, count=Number(config.option_count)||0;

        if(['PG','PG_KOMPLEKS','PG_BERTINGKAT'].includes(type)){
            const note=document.createElement('p'); note.className='small text-secondary mb-2';
            note.textContent=type==='PG'?'Pilih tepat satu kunci jawaban.':
                type==='PG_KOMPLEKS'?'Centang semua pilihan yang benar.':'Isi poin untuk setiap pilihan.';
            target.append(note);
            const grid=document.createElement('div'); grid.className='d-grid gap-3';
            state.options.forEach((item,i)=>{
                const card=document.createElement('div'); card.className='border rounded p-3';
                const row=document.createElement('div'); row.className='row g-2 align-items-start';
                row.append(makeRichField('Pilihan '+String.fromCharCode(65+i),item.text,v=>{item.text=v;preview();},{width:'col-md-8'}));
                const side=document.createElement('div'); side.className='col-md-4';
                if(type==='PG'){
                    const lab=document.createElement('label'); lab.className='form-check mt-4';
                    const input=document.createElement('input'); input.type='radio'; input.name='pgCorrect'; input.className='form-check-input';
                    input.checked=Boolean(item.correct); input.disabled=!editorEditable();
                    input.addEventListener('change',()=>{state.options.forEach(o=>o.correct=false);item.correct=true;preview();});
                    const text=document.createElement('span'); text.className='form-check-label ms-1'; text.textContent='Kunci jawaban';
                    lab.append(input,text); side.append(lab);
                } else if(type==='PG_KOMPLEKS'){
                    const lab=document.createElement('label'); lab.className='form-check mt-4';
                    const input=document.createElement('input'); input.type='checkbox'; input.className='form-check-input';
                    input.checked=Boolean(item.correct); input.disabled=!editorEditable();
                    input.addEventListener('change',()=>{item.correct=input.checked;preview();});
                    const text=document.createElement('span'); text.className='form-check-label ms-1'; text.textContent='Jawaban benar';
                    lab.append(input,text); side.append(lab);
                } else {
                    const lab=document.createElement('label'); lab.className='cbt-form-label'; lab.textContent='Poin pilihan';
                    const input=document.createElement('input'); input.className='form-control'; input.type='number'; input.min='0'; input.max='1000'; input.step='0.0001';
                    input.value=item.point_value??'0'; input.disabled=!editorEditable();
                    input.addEventListener('input',()=>{item.point_value=input.value;preview();});
                    lab.append(input); side.append(lab);
                }
                row.append(side); card.append(row); grid.append(card);
            });
            target.append(grid);
        } else if(type==='MATCHING'){
            const note=document.createElement('div'); note.className='alert alert-light border small';
            note.textContent='Mode penilaian: '+(config.scoring_mode==='ALL_OR_NOTHING'?'Semua benar':'Per pasangan')+'. Jumlah pasangan: '+count+'.';
            target.append(note);
            const grid=document.createElement('div'); grid.className='d-grid gap-3';
            state.pairs.forEach((item,i)=>{
                const row=document.createElement('div'); row.className='row g-2 border rounded p-2';
                row.append(makeRichField('Sisi kiri '+(i+1),item.left,v=>{item.left=v;preview();},{width:'col-md-6'}),
                    makeRichField('Pasangan kanan '+(i+1),item.right,v=>{item.right=v;preview();},{width:'col-md-6'}));
                grid.append(row);
            });
            target.append(grid);
        } else if(type==='ISIAN_SINGKAT'){
            const modeWrap=document.createElement('div'); modeWrap.className='mb-3';
            const lab=document.createElement('label'); lab.className='cbt-form-label'; lab.textContent='Mode jawaban';
            const select=document.createElement('select'); select.className='form-select'; select.disabled=!editorEditable();
            select.add(new Option('Teks','TEXT')); select.add(new Option('Angka','NUMERIC')); select.value=state.mode||'TEXT';
            select.addEventListener('change',()=>{state.mode=select.value;renderSpecific();preview();});
            lab.append(select); modeWrap.append(lab); target.append(modeWrap);
            if(state.mode==='TEXT'){
                const grid=document.createElement('div'); grid.className='row g-2';
                state.answers.forEach((value,i)=>{
                    const col=document.createElement('div'); col.className='col-md-6';
                    const label=document.createElement('label'); label.className='cbt-form-label'; label.textContent='Jawaban diterima '+(i+1);
                    const input=document.createElement('input'); input.className='form-control'; input.value=value; input.disabled=!editorEditable();
                    input.addEventListener('input',()=>{state.answers[i]=input.value;preview();}); label.append(input); col.append(label); grid.append(col);
                });
                target.append(grid);
                if(editorEditable()){
                    const add=document.createElement('button'); add.type='button'; add.className='btn btn-outline-primary btn-sm mt-2'; add.textContent='Tambah jawaban diterima';
                    add.disabled=state.answers.length>=20; add.addEventListener('click',()=>{state.answers.push('');renderSpecific();});
                    target.append(add);
                }
            } else {
                const row=document.createElement('div'); row.className='row g-2';
                for(const spec of [['Angka harapan','expected'],['Toleransi absolut','tolerance']]){
                    const col=document.createElement('div'); col.className='col-md-4';
                    const label=document.createElement('label'); label.className='cbt-form-label'; label.textContent=spec[0];
                    const input=document.createElement('input'); input.className='form-control'; input.type='number'; input.step='0.00000001';
                    input.value=state[spec[1]]|| (spec[1]==='tolerance'?'0':''); input.disabled=!editorEditable();
                    input.addEventListener('input',()=>{state[spec[1]]=input.value;preview();}); label.append(input); col.append(label); row.append(col);
                }
                target.append(row);
            }
        } else if(type==='URAIAN'){
            target.append(makeRichField('Rubrik / Pedoman Penilaian',state.rubric,v=>{state.rubric=v;preview();},{rows:5}));
        }
        preview();
    };

    const openEditor = (item, live=false) => {
        const type=state.activeType, config=state.configMap.get(type)||{};
        state.liveEditing=Boolean(live && item && state.bank?.status==='READY');
        const editable=editorEditable();
        state.editing=item?Number(item.id):null; state.revision=item?Number(item.current_revision_no):null; state.media=item?.media||{};
        window.CbtMediaPreview={...(item?.media||{})};
        $('questionTypeLabel').value=labels[type]||type;
        $('questionStimulusGroup').hidden=type==='PG';
        $('questionPoint').disabled=!editable;
        $('questionPoint').value=item?.max_point??'1';
        if(['PG','PG_KOMPLEKS'].includes(type) && !item) $('questionPoint').value='1';

        state.richQuestion.setValue(item?.question_text||'',state.media);
        state.richQuestion.setDisabled(!editable);
        state.richStimulus.setValue(item?.stimulus_text||'',state.media);
        state.richStimulus.setDisabled(!editable);

        const count=Number(config.option_count)||4;
        state.options=(item?.options||Array.from({length:count},()=>({}))).map((o,i)=>({
            text:o.content_text||'', correct:type==='PG'?Number(o.is_correct)===1:Boolean(Number(o.is_correct)),
            point_value:o.point_value??'0'
        }));
        state.pairs=(item?.pairs||Array.from({length:count},()=>({}))).map(p=>({left:p.left_text||'',right:p.right_text||''}));
        state.answers=item?.accepted_values?.length?[...item.accepted_values]:[''];
        state.mode=item?.short_answer_mode||item?.scoring_mode||(type==='MATCHING'?config.scoring_mode||'PARTIAL':'TEXT');
        state.expected=item?.expected_numeric??''; state.tolerance=item?.numeric_tolerance??'0'; state.rubric=item?.rubric_text??'';

        $('questionEditorKicker').textContent=state.liveEditing?'Live Edit · '+(labels[type]||type):(labels[type]||type);
        $('questionEditorTitle').textContent=item
            ? (state.liveEditing?'Buat Revisi Baru · Revisi ':editable?'Edit / Tinjau Soal · Revisi ':'Tinjau Soal · Revisi ')+state.revision
            : 'Tambah Soal';
        $('questionLivePanel').hidden=!state.liveEditing;
        $('questionLiveKind').value='CONTENT';
        $('questionLiveNote').value='';
        $('questionLivePolicy').value='PRESERVE';
        $('questionLivePolicyWrap').hidden=true;
        $('questionSave').hidden=!editable;
        $('questionSave').textContent=state.liveEditing?'Simpan Revisi':'Simpan Soal';
        $('questionCancel').textContent=editable?'Batal':'Tutup';
        feedback('questionFormFeedback',''); renderSpecific(); $('questionEditor').hidden=false;
        $('questionEditor').scrollIntoView({behavior:'smooth',block:'start'});
    };

    const currentPayload = () => {
        const type=state.activeType;
        const data={question_text:state.richQuestion.getValue(),max_point:$('questionPoint').value};
        if(type!=='PG'||state.liveEditing){data.question_type=type;}
        if(type!=='PG'){data.stimulus_text=state.richStimulus.getValue();}
        if(type==='PG'){
            data.correct_key=String.fromCharCode(65+Math.max(0,state.options.findIndex(o=>o.correct)));
            data.options=state.options.map(o=>({text:o.text}));
        } else if(type==='PG_KOMPLEKS') data.options=state.options.map(o=>({text:o.text,correct:Boolean(o.correct)}));
        else if(type==='PG_BERTINGKAT') data.options=state.options.map(o=>({text:o.text,point_value:o.point_value}));
        else if(type==='MATCHING'){data.pairs=state.pairs.map(p=>({left:p.left,right:p.right}));data.scoring_mode=state.configMap.get(type)?.scoring_mode||'PARTIAL';}
        else if(type==='ISIAN_SINGKAT'){
            data.short_answer_mode=state.mode;
            if(state.mode==='TEXT') data.accepted_values=state.answers;
            else {data.expected_numeric=state.expected;data.numeric_tolerance=state.tolerance||'0';}
        } else if(type==='URAIAN') data.rubric_text=state.rubric;
        if(state.editing!==null) data.expected_revision=state.revision;
        return data;
    };

    const refreshCounts = async () => {
        try {
            const check=await api(preflightApi);
            state.bank={...(state.bank||{}),...(check.bank||{})};
            const counts=check.counts||{};
            for(const button of document.querySelectorAll('[data-question-type]')){
                const type=button.dataset.questionType, count=counts[type]||{};
                const badge=button.querySelector('.question-tab-count');
                if(badge) badge.textContent=String(Number(count.available||0))+'/'+String(Number(count.planned??state.configMap.get(type)?.question_count??0));
            }
        } catch(_) {}
    };

    const updateSelectionUi = () => {
        $('questionSelectedCount').textContent=state.selected.size+' soal dipilih';
        $('questionBulkDelete').disabled=!state.editable||state.selected.size===0;
        const boxes=[...document.querySelectorAll('.question-row-check')];
        $('questionCheckAll').checked=boxes.length>0&&boxes.every(box=>box.checked);
        $('questionCheckAll').indeterminate=boxes.some(box=>box.checked)&&!$('questionCheckAll').checked;
    };

    const clearSelection = () => {state.selected.clear(); $('questionCheckAll').checked=false; $('questionCheckAll').indeterminate=false; updateSelectionUi();};

    const openDelete = ids => {
        state.deleteIds=[...ids];
        $('questionDeleteTitle').textContent=ids.length>1?'Hapus Soal Terpilih':'Hapus Soal';
        $('questionDeleteMessage').textContent=ids.length>1
            ? 'Hapus permanen '+ids.length+' soal terpilih beserta seluruh revisinya?'
            : 'Hapus permanen soal ini beserta seluruh revisinya?';
        bootstrap.Modal.getOrCreateInstance($('questionDeleteModal')).show();
    };

    const appendInfoSection = (body, section, common=false) => {
        const card=document.createElement('section');
        card.className='border rounded p-3 '+(common?'bg-body-tertiary':'bg-body')+' mb-3';
        const heading=document.createElement('h3'); heading.className='fs-6 mb-2'; heading.textContent=section.title;
        const list=document.createElement('ul'); list.className='mb-0 ps-3';
        for(const line of section.items||[]){
            const item=document.createElement('li'); item.className='mb-1'; item.textContent=line; list.append(item);
        }
        card.append(heading,list); body.append(card);
    };

    const infoModal = () => {
        const type=state.activeType, guide=info[type], body=$('questionInfoBody'); body.replaceChildren();
        $('questionInfoTitle').textContent='Panduan — '+(labels[type]||type);

        if(guide?.intro){
            const lead=document.createElement('p'); lead.className='mb-3'; lead.textContent=guide.intro; body.append(lead);
        }
        for(const section of guide?.sections||[]) appendInfoSection(body,section);

        const divider=document.createElement('hr'); divider.className='my-4'; body.append(divider);
        const commonTitle=document.createElement('h3'); commonTitle.className='fs-6 mb-3'; commonTitle.textContent='Hal yang juga dapat digunakan pada soal';
        body.append(commonTitle);
        for(const section of commonInfo) appendInfoSection(body,section,true);

        bootstrap.Modal.getOrCreateInstance($('questionInfoModal')).show();
    };

    const rowCell = (text, className='') => {
        const cell=document.createElement('td'); cell.className=className; cell.textContent=String(text??''); return cell;
    };

    const renderTableRows = items => {
        const body=$('questionTable').querySelector('tbody'); body.replaceChildren();
        for(const item of items){
            const row=document.createElement('tr');

            const selectCell=document.createElement('td'); selectCell.className='text-center question-check-col';
            const check=document.createElement('input'); check.type='checkbox'; check.className='form-check-input question-row-check';
            check.dataset.id=String(Number(item.id)); check.checked=state.selected.has(Number(item.id));
            check.disabled=!state.editable; check.setAttribute('aria-label','Pilih soal');
            selectCell.append(check);

            const action=document.createElement('td'); action.className='text-nowrap';
            const edit=document.createElement('button'); edit.type='button'; edit.className='btn btn-outline-primary btn-sm me-1';
            edit.dataset.action='edit'; edit.dataset.id=String(Number(item.id)); edit.textContent=state.editable?'Edit / Tinjau':'Tinjau';
            action.append(edit);
            if(state.bank?.status==='READY'){
                const live=document.createElement('button'); live.type='button'; live.className='btn btn-outline-warning btn-sm me-1';
                live.dataset.action='live'; live.dataset.id=String(Number(item.id)); live.textContent='Live Edit';
                action.append(live);
            }
            const remove=document.createElement('button'); remove.type='button'; remove.className='btn btn-outline-danger btn-sm';
            remove.dataset.action='delete'; remove.dataset.id=String(Number(item.id)); remove.textContent='Hapus'; remove.disabled=!state.editable;
            action.append(remove);

            row.append(
                selectCell,
                rowCell(item.sort_order,'text-nowrap'),
                rowCell((summaryText(item.question_text)||'[Konten media]').slice(0,180)),
                rowCell(numberLabel(item.max_point),'text-nowrap'),
                rowCell(item.current_revision_no,'text-nowrap'),
                action
            );
            body.append(row);
        }
        if(!items.length){
            const row=document.createElement('tr'), cell=document.createElement('td');
            cell.colSpan=6; cell.className='text-center text-secondary py-4';
            cell.textContent=$('questionSearch').value.trim()?'Tidak ada soal yang cocok.':'Belum ada soal tipe ini.';
            row.append(cell); body.append(row);
        }
        $('questionCheckAll').disabled=!state.editable||!items.length;
        updateSelectionUi();
    };

    const loadTable = async ({keepPage=true}={}) => {
        const seq=++state.seq;
        if(!keepPage) state.page=1;
        feedback('questionFeedback','Memuat soal...');
        try{
            const params=new URLSearchParams({
                type:state.activeType,
                page:String(state.page),
                per_page:$('questionPageSize').value,
                q:$('questionSearch').value.trim()
            });
            const data=await api(base+'?'+params);
            if(seq!==state.seq)return;

            state.bank=data.bank;
            state.editable=data.bank.status==='DRAFT'&&data.bank.kegiatan_status==='DRAFT';
            state.page=Number(data.pagination.page||1);
            state.pages=Number(data.pagination.pages||1);
            state.total=Number(data.pagination.total||0);
            state.filtered=Number(data.pagination.filtered??state.total);

            $('questionContext').textContent=data.bank.nama_bank+' · '+data.bank.kegiatan_nama+' · '+data.bank.mapel_nama+' · Tingkat '+data.bank.tingkat+' · '+data.bank.status;
            $('questionAdd').disabled=!state.editable;
            $('questionImportLink').hidden=!state.editable;

            renderTableRows(data.items||[]);

            const perPage=Number($('questionPageSize').value)||25;
            const startRow=state.filtered?((state.page-1)*perPage)+1:0;
            const endRow=Math.min(state.page*perPage,state.filtered);
            $('questionTableInfo').textContent=state.filtered===state.total
                ? (state.filtered?startRow+'–'+endRow+' dari '+state.filtered+' soal':'Belum ada soal')
                : startRow+'–'+endRow+' dari '+state.filtered+' hasil · '+state.total+' total';
            $('questionPageInfo').textContent=state.page+' / '+state.pages;
            $('questionPrevious').disabled=state.page<=1;
            $('questionNext').disabled=state.page>=state.pages;
            feedback('questionFeedback','');
        }catch(error){
            if(seq!==state.seq)return;
            renderTableRows([]);
            $('questionTableInfo').textContent='Gagal memuat soal';
            feedback('questionFeedback',error.message,true);
        }
    };

    const initTable = () => {
        const table=$('questionTable');
        table.addEventListener('change',event=>{
            const box=event.target.closest('.question-row-check'); if(!box)return;
            const id=Number(box.dataset.id); if(box.checked)state.selected.add(id);else state.selected.delete(id);
            updateSelectionUi();
        });
        table.addEventListener('click',async event=>{
            const button=event.target.closest('[data-action]'); if(!button)return;
            const id=Number(button.dataset.id);
            if(button.dataset.action==='delete'){if(state.editable)openDelete([id]);return;}
            button.disabled=true;
            try{
                const data=await api(base+'/'+id);
                openEditor(data.item,button.dataset.action==='live');
            }
            catch(error){feedback('questionFeedback',error.message,true);}
            finally{button.disabled=false;}
        });

        let timer=null;
        $('questionSearch').addEventListener('input',()=>{
            clearTimeout(timer); clearSelection();
            timer=setTimeout(()=>{state.page=1;loadTable();},250);
        });
        $('questionPageSize').addEventListener('change',()=>{clearSelection();state.page=1;loadTable();});
        $('questionPrevious').addEventListener('click',()=>{if(state.page>1){state.page--;loadTable();}});
        $('questionNext').addEventListener('click',()=>{if(state.page<state.pages){state.page++;loadTable();}});
    };

    const activateType = type => {
        if(!state.configured.includes(type))return;
        state.activeType=type; state.page=1; clearSelection(); $('questionEditor').hidden=true;
        $('questionSearch').value='';
        for(const button of document.querySelectorAll('[data-question-type]')){
            const active=button.dataset.questionType===type; button.classList.toggle('active',active); button.setAttribute('aria-selected',active?'true':'false');
        }
        loadTable();
    };

    const buildTabs = () => {
        const tabs=$('questionTypeTabs'); tabs.replaceChildren();
        for(const type of state.configured){
            const li=document.createElement('li');li.className='nav-item';li.role='presentation';
            const button=document.createElement('button');button.type='button';button.className='nav-link';button.dataset.questionType=type;button.role='tab';
            const label=document.createElement('span');label.textContent=labels[type]||type;
            const badge=document.createElement('span');badge.className='badge rounded-pill text-bg-light question-tab-count ms-2';badge.textContent='0/'+Number(state.configMap.get(type)?.question_count||0);
            button.append(label,badge);button.addEventListener('click',()=>activateType(type));li.append(button);tabs.append(li);
        }
    };

    $('questionInfo').addEventListener('click',infoModal);
    $('questionAdd').addEventListener('click',()=>openEditor(null));
    $('questionPoint').addEventListener('input',preview);
    $('questionEditorClose').addEventListener('click',()=>{$('questionEditor').hidden=true;});
    $('questionCancel').addEventListener('click',()=>{$('questionEditor').hidden=true;});
    $('questionCheckAll').addEventListener('change',()=>{
        for(const box of document.querySelectorAll('.question-row-check')){
            box.checked=$('questionCheckAll').checked;const id=Number(box.dataset.id);
            if(box.checked)state.selected.add(id);else state.selected.delete(id);
        } updateSelectionUi();
    });
    $('questionBulkDelete').addEventListener('click',()=>{if(state.selected.size)openDelete([...state.selected]);});
    $('questionDeleteConfirm').addEventListener('click',async()=>{
        const ids=[...state.deleteIds]; if(!ids.length)return;
        const button=$('questionDeleteConfirm');button.disabled=true;
        try{
            if(ids.length===1) await api(base+'/'+ids[0],'DELETE');
            else await api(base+'/bulk-delete','POST',{ids});
            bootstrap.Modal.getOrCreateInstance($('questionDeleteModal')).hide();
            clearSelection();$('questionEditor').hidden=true;loadTable();await refreshCounts();
            feedback('questionFeedback',ids.length+' soal berhasil dihapus permanen.');
        }catch(error){feedback('questionFeedback',error.message,true);}
        finally{button.disabled=false;state.deleteIds=[];}
    });
    $('questionForm').addEventListener('submit',async event=>{
        event.preventDefault(); if(!editorEditable())return;
        if(state.activeType==='PG'&&!state.options.some(o=>o.correct)){feedback('questionFormFeedback','Pilih tepat satu kunci jawaban.',true);return;}
        const revision=currentPayload(), button=$('questionSave');button.disabled=true;
        try{
            if(state.liveEditing){
                const note=$('questionLiveNote').value.trim();
                if(!note){feedback('questionFormFeedback','Catatan perubahan Live Edit wajib diisi.',true);return;}
                const kind=$('questionLiveKind').value;
                const payload={
                    change_kind:kind,
                    change_note:note,
                    active_answer_policy:kind==='STRUCTURAL'?$('questionLivePolicy').value:'PRESERVE',
                    expected_revision:state.revision,
                    revision
                };
                const data=await api(base+'/'+state.editing+'/revision','POST',payload);
                state.liveEditing=false;
                $('questionEditor').hidden=true;loadTable();await refreshCounts();
                feedback('questionFeedback','Live Edit tersimpan sebagai revisi '+data.revision_no+'.');
            }else{
                const data=await api(state.editing===null?base:base+'/'+state.editing,state.editing===null?'POST':'PUT',revision);
                $('questionEditor').hidden=true;loadTable();await refreshCounts();
                feedback('questionFeedback','Soal tersimpan pada revisi '+data.item.current_revision_no+'.');
            }
        }catch(error){feedback('questionFormFeedback',error.message,true);}
        finally{button.disabled=false;}
    });

    $('questionLiveKind').addEventListener('change',()=>{
        $('questionLivePolicyWrap').hidden=$('questionLiveKind').value!=='STRUCTURAL';
    });
    $('questionVoid').addEventListener('click',async()=>{
        if(!state.liveEditing||!state.editing)return;
        const note=$('questionLiveNote').value.trim();
        if(!note){feedback('questionFormFeedback','Isi Catatan Perubahan sebelum membatalkan soal.',true);return;}
        const button=$('questionVoid');button.disabled=true;
        try{
            const data=await api(base+'/'+state.editing+'/void','POST',{
                expected_revision:state.revision,
                change_note:note
            });
            state.liveEditing=false;$('questionEditor').hidden=true;loadTable();await refreshCounts();
            feedback('questionFeedback','Soal dibatalkan (VOID) pada revisi '+data.revision_no+'. Jawaban historis tetap tersimpan.');
        }catch(error){feedback('questionFormFeedback',error.message,true);}
        finally{button.disabled=false;}
    });

    Promise.all([api(configApi),api(preflightApi)]).then(([config,check])=>{
        state.bank=config.bank;state.editable=Boolean(config.editable);
        $('questionImportLink').hidden=!state.editable;
        state.configMap=new Map(config.items.map(item=>[item.question_type,item]));
        state.configured=order.filter(type=>state.configMap.has(type));
        buildTabs();
        if(!state.configured.length){
            feedback('questionFeedback','Belum ada tipe soal aktif. Atur Komposisi Bank terlebih dahulu.',true);
            $('questionAdd').disabled=true;$('questionInfo').disabled=true;return;
        }
        state.activeType=state.configured[0];
        state.richStimulus=window.CbtRichEditor.mount($('questionStimulus'),{api:mediaApi,onChange:preview});
        state.richQuestion=window.CbtRichEditor.mount($('questionText'),{api:mediaApi,onChange:preview});
        initTable();activateType(state.activeType);refreshCounts();
    }).catch(error=>feedback('questionFeedback',error.message,true));
})();