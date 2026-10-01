(() => {
    'use strict';
    const app = document.getElementById('analisisSoalApp');
    if (!app) return;
    const els = {
        jadwal:document.getElementById('analysisJadwal'), type:document.getElementById('analysisType'),
        load:document.getElementById('analysisLoad'), excel:document.getElementById('analysisExcel'),
        pdf:document.getElementById('analysisPdf'), feedback:document.getElementById('analysisFeedback'),
        title:document.getElementById('analysisTitle'), summary:document.getElementById('analysisSummary'),
        rows:document.getElementById('analysisRows'), detailTitle:document.getElementById('analysisDetailTitle'),
        detailBody:document.getElementById('analysisDetailBody')
    };
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('analysisDetailModal'));
    const esc = value => String(value ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;')
        .replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const plain = value => {
        const node = document.createElement('div');
        node.innerHTML = String(value || '');
        return (node.textContent || '').replace(/\s+/g, ' ').trim();
    };
    const fmt = value => value === null || value === '' || value === undefined ? '—'
        : Number(value).toLocaleString('id-ID',{maximumFractionDigits:2});
    const pct = value => value === null || value === '' || value === undefined ? '—' : fmt(value) + '%';
    const params = () => {
        const p=new URLSearchParams();
        if(els.jadwal.value)p.set('jadwal_id',els.jadwal.value);
        if(els.type.value)p.set('question_type',els.type.value);
        return p;
    };
    async function getJson(url){
        const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
        const payload=await response.json().catch(()=>({}));
        if(!response.ok||payload.ok===false)throw new Error(payload.error?.message||'Data belum dapat dimuat.');
        return payload.data??payload;
    }
    async function options(){
        const data=await getJson(app.dataset.optionsApi);
        els.jadwal.innerHTML='<option value="">Pilih Jadwal</option>'+(data.jadwal||[]).map(r =>
            '<option value="'+esc(r.id)+'">'+esc((r.kegiatan_nama||'Kegiatan')+' — '+(r.nama_mapel||r.nama_bank||'Ujian')+' — '+(r.mulai_at||''))+'</option>'
        ).join('');
        els.type.innerHTML='<option value="">Semua Tipe</option>'+(data.types||[]).map(r =>
            '<option value="'+esc(r.value)+'">'+esc(r.label)+'</option>').join('');
    }
    async function load(){
        if(!els.jadwal.value){
            els.feedback.textContent='Pilih Jadwal terlebih dahulu.';
            els.feedback.className='cbt-inline-feedback mt-2 text-warning'; return;
        }
        els.feedback.textContent='';
        els.rows.innerHTML='<tr><td colspan="10" class="text-center text-secondary py-4">Menghitung analisis...</td></tr>';
        try{
            const data=await getJson(app.dataset.api+'?'+params());
            const context=data.context||{}, items=data.items||[];
            els.title.textContent=[context.kegiatan_nama,context.nama_mapel||context.nama_bank].filter(Boolean).join(' · ');
            els.summary.textContent=(data.summary?.question_count||0)+' soal · '+(data.summary?.participant_count||0)+' peserta';
            els.rows.innerHTML=items.length?items.map(item=>'<tr>'
                +'<td>'+esc(item.stable_key||('#'+item.question_id))+'</td><td>'+esc(item.question_type)+'</td>'
                +'<td class="text-end">'+esc(item.participant_count)+'</td><td class="text-end">'+esc(item.pending_count||0)+'</td>'
                +'<td class="text-end">'+pct(item.average_percent)+'</td>'
                +'<td class="text-end">'+pct(item.difficulty_index)+'</td><td class="text-end">'+pct(item.full_score_rate)+'</td>'
                +'<td class="text-end">'+pct(item.zero_score_rate)+'</td><td class="text-end">'+esc(item.voided_count)+'</td>'
                +'<td><button class="btn btn-outline-primary btn-sm" data-analysis-detail="'+esc(item.question_id)+'">Detail</button></td></tr>').join('')
                :'<tr><td colspan="10" class="text-center text-secondary py-4">Belum ada item hasil resmi.</td></tr>';
            els.excel.disabled=false; els.pdf.disabled=false;
        }catch(error){
            els.feedback.textContent=error.message; els.feedback.className='cbt-inline-feedback mt-2 text-danger';
            els.rows.innerHTML='<tr><td colspan="10" class="text-center text-danger py-4">Analisis belum dapat dimuat.</td></tr>';
        }
    }
    async function detail(id){
        els.detailTitle.textContent='Analisis Soal';
        els.detailBody.innerHTML='<div class="text-center text-secondary py-4">Memuat detail...</div>'; modal.show();
        try{
            const data=await getJson(app.dataset.api+'/'+encodeURIComponent(id)+'?jadwal_id='+encodeURIComponent(els.jadwal.value));
            const q=data.question||{}, rows=data.responses||[];
            els.detailTitle.textContent=(q.stable_key||'Soal')+' · '+(q.question_type||'');
            els.detailBody.innerHTML='<div class="border rounded-3 p-3 mb-3 text-break">'+esc(plain(q.question_html||''))+'</div>'
                +'<div class="table-responsive"><table class="table manager-table align-middle mb-0">'
                +'<thead><tr><th>No Peserta</th><th>Nama</th><th>Rombel</th><th>Dijawab</th><th>Skor</th><th>Maks.</th><th>Status</th></tr></thead><tbody>'
                +(rows.length?rows.map(r=>{
                    const pending=['PENDING','PENDING_MANUAL','NEEDS_REVIEW'].includes(String(r.scoring_state||''));
                    const status=r.voided?'VOID':(pending?'BELUM DINILAI':esc(r.scoring_state||'—'));
                    return '<tr><td>'+esc(r.nomor_peserta_snapshot||'—')+'</td><td>'+esc(r.nama_snapshot)+'</td>'
                        +'<td>'+esc(r.rombel_snapshot)+'</td><td>'+(r.answered?'Ya':'Tidak')+'</td><td>'+(pending?'—':fmt(r.raw_score))+'</td>'
                        +'<td>'+fmt(r.max_point)+'</td><td>'+status+'</td></tr>';
                }).join('')
                    :'<tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada response resmi.</td></tr>')
                +'</tbody></table></div>';
        }catch(error){els.detailBody.innerHTML='<div class="alert alert-danger mb-0">'+esc(error.message)+'</div>';}
    }
    els.load.addEventListener('click',load);
    els.excel.addEventListener('click',()=>window.location.assign(app.dataset.exportXlsx+'?'+params()));
    els.pdf.addEventListener('click',()=>window.open(app.dataset.exportPdf+'?'+params(),'_blank','noopener'));
    els.rows.addEventListener('click',e=>{const b=e.target.closest('[data-analysis-detail]');if(b)detail(b.dataset.analysisDetail);});
    options().catch(error=>{els.feedback.textContent=error.message;els.feedback.className='cbt-inline-feedback mt-2 text-danger';});
})();