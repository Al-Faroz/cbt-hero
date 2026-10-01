(() => {
    'use strict';
    const app=document.getElementById('liveScoringManager');
    if(!app)return;
    const csrf=()=>document.querySelector('meta[name="csrf-token"]')?.content||'';
    const els={
        jadwal:document.getElementById('liveJadwal'),start:document.getElementById('liveStart'),
        stop:document.getElementById('liveStop'),regenerate:document.getElementById('liveRegenerate'),
        copy:document.getElementById('liveCopy'),open:document.getElementById('liveOpen'),
        url:document.getElementById('liveUrl'),state:document.getElementById('liveState'),
        badge:document.getElementById('liveBadge'),feedback:document.getElementById('liveFeedback')
    };
    async function request(url,options={}){
        const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json','X-CSRF-TOKEN':csrf()}:{}),...(options.headers||{})},...options});
        const payload=await response.json().catch(()=>({}));
        if(!response.ok||payload.ok===false)throw new Error(payload.error?.message||'Operasi Live Scoring gagal.');
        return payload.data??payload;
    }
    async function options(){
        const rows=await request(app.dataset.optionsApi);
        els.jadwal.innerHTML='<option value="">Pilih Jadwal</option>'+(rows||[]).map(r=>
            '<option value="'+String(r.id)+'">'+[(r.kegiatan_nama||'Kegiatan'),(r.nama_mapel||r.nama_bank||'Ujian'),r.jenis_jadwal,(r.mulai_at||'')].join(' — ')+'</option>'
        ).join('');
    }
    function render(data){
        const enabled=Boolean(data.enabled);
        els.state.textContent=enabled?'PUBLIC DISPLAY AKTIF':'PUBLIC DISPLAY OFF';
        els.badge.textContent=enabled?'ON':'OFF';
        els.badge.className='badge '+(enabled?'text-bg-success':'text-bg-secondary');
        els.url.value=data.public_url||'';
        els.open.disabled=!data.public_url;
        els.copy.disabled=!data.public_url;
        els.stop.disabled=!enabled;
        if(data.jadwal_id)els.jadwal.value=String(data.jadwal_id);
    }
    async function state(){render(await request(app.dataset.api));}
    async function mutate(action,body=null){
        els.feedback.textContent='';
        try{
            const data=await request(app.dataset.api+'/'+action,{method:'POST',body:body===null?'{}':JSON.stringify(body)});
            render(data);
            els.feedback.textContent=action==='start'?'Live Scoring aktif.':action==='stop'?'Live Scoring dihentikan.':'URL publik berhasil diganti.';
            els.feedback.className='cbt-inline-feedback mt-3 text-success';
        }catch(error){els.feedback.textContent=error.message;els.feedback.className='cbt-inline-feedback mt-3 text-danger';}
    }
    els.start.addEventListener('click',()=>{if(!els.jadwal.value){els.feedback.textContent='Pilih Jadwal terlebih dahulu.';return;} mutate('start',{jadwal_id:Number(els.jadwal.value)});});
    els.stop.addEventListener('click',()=>mutate('stop'));
    els.regenerate.addEventListener('click',()=>{if(window.confirm('Ganti URL publik? URL lama langsung tidak berlaku.'))mutate('regenerate');});
    els.open.addEventListener('click',()=>{if(els.url.value)window.open(els.url.value,'_blank','noopener');});
    els.copy.addEventListener('click',async()=>{if(!els.url.value)return;try{await navigator.clipboard.writeText(els.url.value);els.feedback.textContent='URL disalin.';}catch(_){els.url.select();document.execCommand('copy');els.feedback.textContent='URL disalin.';}});
    (async()=> {
        try {
            await options();
            await state();
        } catch (error) {
            els.feedback.textContent=error.message;
            els.feedback.className='cbt-inline-feedback mt-3 text-danger';
        }
    })();
})();