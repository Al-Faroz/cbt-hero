(() => {
    'use strict';
    window.CbtMediaPreview = {};
    for (const wrapper of document.querySelectorAll('[data-media-insert]')) {
        const field=document.getElementById(wrapper.dataset.mediaInsert), input=wrapper.querySelector('input[type="file"]');
        const status=wrapper.querySelector('small'), upload=wrapper.querySelector('button:not([data-video])'), video=wrapper.querySelector('[data-video]');
        const insert=item=>{
            const token='[[media:'+item.id+']]';const start=field.selectionStart??field.value.length,end=field.selectionEnd??start;
            window.CbtMediaPreview[item.id]={kind:item.kind,url:item.kind==='VIDEO'?item.url:
                wrapper.dataset.api+'/'+item.id};
            field.value=field.value.slice(0,start)+token+field.value.slice(end);field.dispatchEvent(new Event('input',{bubbles:true}));
            field.focus();field.setSelectionRange(start+token.length,start+token.length);
            status.textContent='Tersisip '+token+'; kode ini dapat dipindahkan ke bagian soal lain.';
        };
        const send=async(body,isVideo)=>{
            const headers={Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content};
            if(isVideo)headers['Content-Type']='application/json';
            const response=await fetch(wrapper.dataset.api+(isVideo?'/video':''),
                {method:'POST',credentials:'same-origin',headers,body:isVideo?JSON.stringify(body):body});
            const result=await response.json().catch(()=>null);
            if(!response.ok||result?.ok!==true)throw Error(result?.error?.message||'Unggah gagal.');
            return result.data;
        };
        upload.addEventListener('click',async()=>{
            if(!input.files.length){status.textContent='Pilih file dahulu.';return;}
            upload.disabled=true;status.textContent='Mengunggah...';
            try {const data=new FormData();data.append('file',input.files[0]);insert(await send(data,false));input.value='';}
            catch(error){status.textContent=error.message;}finally{upload.disabled=false;}
        });
        video.addEventListener('click',async()=>{
            const url=window.prompt('Tautan HTTPS YouTube atau Vimeo:');if(!url)return;
            video.disabled=true;
            try {const item=await send({url},true);item.url=url;insert(item);}
            catch(error){status.textContent=error.message;}finally{video.disabled=false;}
        });
    }
})();
