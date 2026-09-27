(() => {
    'use strict';
    const app=document.getElementById('questionImportApp'); if(!app) return;
    const $=id=>document.getElementById(id), base=app.dataset.api;
    let current=null;
    const message=(text,error=false)=>{const node=$('questionImportFeedback');node.textContent=text;
        node.className='cbt-inline-feedback'+(text?error?' is-error':' is-info':'');};
    const request=async(url,method='GET',body=null)=>{
        const headers={Accept:'application/json'};
        if(method!=='GET') headers['X-CSRF-TOKEN']=document.querySelector('meta[name="csrf-token"]').content;
        if(url.endsWith('/commit')) headers['Idempotency-Key']=crypto.randomUUID().replaceAll('-','');
        if(body!==null && !(body instanceof FormData)) headers['Content-Type']='application/json';
        const response=await fetch(url,{method,credentials:'same-origin',headers,
            ...(body===null?{}:{body:body instanceof FormData?body:JSON.stringify(body)})});
        const json=await response.json().catch(()=>null);
        if(!response.ok||json?.ok!==true) throw new Error(json?.error?.message||'Permintaan gagal.');
        return json.data;
    };
    const button=(title,handler)=>{const b=document.createElement('button');b.type='button';b.className='btn btn-outline-primary btn-sm me-1';
        b.textContent=title;b.addEventListener('click',handler);return b;};
    const cell=(text)=>{const c=document.createElement('td');c.textContent=text==null?'':String(text);return c;};
    const previewQuestion=item=>{
        const p=item.payload, q={...p,media:item.media||{}};
        q.options=(Array.isArray(p.options)?p.options:[]).map((o,i)=>({option_key:String.fromCharCode(65+i),content_text:o?.text,
            is_correct:p.question_type==='PG'?Number(p.correct_key===String.fromCharCode(65+i)):Number(o?.correct===true),point_value:o?.point_value}));
        q.pairs=(Array.isArray(p.pairs)?p.pairs:[]).map((pair,i)=>({left_key:String(i+1),left_text:pair?.left,right_text:pair?.right}));
        q.accepted_values=Array.isArray(p.accepted_values)?p.accepted_values:[];
        return window.CbtQuestionRenderer.render(q,{showAnswer:true});
    };
    const history=async()=>{
        const data=await request(base);
        $('questionImportHistory').replaceChildren(new Option('Pilih job terdahulu',''),
            ...data.items.map(item=>new Option('#'+item.id+' · '+item.original_filename+' · '+item.status,item.id)));
        if(current) $('questionImportHistory').value=String(current.job.id);
    };
    const render=data=>{
        current=data;const job=data.job; $('questionImportStaging').hidden=false;
        $('questionImportTitle').textContent='Staging #'+job.id+' · '+job.original_filename;
        $('questionImportSummary').textContent='Status '+job.status+' · Valid '+job.valid_items+' · Invalid '+job.invalid_items+' · Total '+job.total_items;
        $('questionImportCommit').disabled=job.status!=='VALIDATED'||Number(job.invalid_items)>0||Number(job.valid_items)<1;
        $('questionImportValidate').disabled=job.status==='COMMITTED';
        const body=$('questionImportRows');body.replaceChildren();
        for(const item of data.items){const row=document.createElement('tr'), actions=document.createElement('td');
            const detail=document.createElement('tr'), detailCell=document.createElement('td');
            detailCell.colSpan=5;detailCell.className='p-3';detailCell.append(previewQuestion(item));
            detail.append(detailCell);detail.hidden=true;
            actions.append(button('Pratinjau',()=>{detail.hidden=!detail.hidden;}));
            if(job.status!=='COMMITTED'){
                actions.append(button('Perbaiki JSON',async()=>{
                    const input=window.prompt('Ubah objek JSON untuk satu soal:',JSON.stringify(item.payload,null,2));
                    if(input===null)return;
                    try {const payload=JSON.parse(input); if(!payload||Array.isArray(payload)||typeof payload!=='object')throw Error('Objek JSON diperlukan.');
                        render(await request(base+'/'+job.id+'/items/'+item.id+'/FIX','PUT',payload));message('Baris diperbaiki; validasi ulang sebelum commit.');}
                    catch(error){message(error.message,true);}
                }));
                actions.append(button(item.validation_status==='EXCLUDED'?'Sertakan':'Keluarkan',async()=>{
                    try {render(await request(base+'/'+job.id+'/items/'+item.id+'/'+(item.validation_status==='EXCLUDED'?'INCLUDE':'EXCLUDE'),'PUT',{}));}
                    catch(error){message(error.message,true);}
                }));
            }
            row.append(cell(item.item_no),cell(item.payload.question_type),cell((item.payload.question_text||'').slice(0,160)),
                cell(item.validation_status+(item.errors.length?' · '+item.errors.join('; '):'')),actions);body.append(row,detail);
        }
    };
    $('questionImportForm').addEventListener('submit',async event=>{
        event.preventDefault();const b=$('questionImportUpload');b.disabled=true;message('Memproses file...');
        try {const form=new FormData();form.append('file',$('questionImportFile').files[0]);
            render(await request(base,'POST',form));await history();message('Staging siap. Periksa seluruh baris sebelum commit.');}
        catch(error){message(error.message,true);} finally {b.disabled=false;}
    });
    $('questionImportValidate').addEventListener('click',async()=>{
        try {render(await request(base+'/'+current.job.id+'/validate','POST',{}));message('Validasi selesai.');}
        catch(error){message(error.message,true);}
    });
    $('questionImportCommit').addEventListener('click',async()=>{
        if(!window.confirm('Commit '+current.job.valid_items+' soal ke Bank?')) return;
        const b=$('questionImportCommit');b.disabled=true;
        try {render(await request(base+'/'+current.job.id+'/commit','POST',{}));await history();message('Seluruh soal telah masuk Bank.');}
        catch(error){message(error.message,true);b.disabled=false;}
    });
    $('questionImportHistory').addEventListener('change',async()=>{
        const id=$('questionImportHistory').value;
        if(!id){$('questionImportStaging').hidden=true;current=null;return;}
        try {render(await request(base+'/'+id));message('Job #'+id+' dimuat.');}
        catch(error){message(error.message,true);}
    });
    history().catch(error=>message(error.message,true));
})();
