(() => {
    'use strict';
    const base=window.bankPrintApi, $=id=>document.getElementById(id);
    const numberLabel=value=>{
        const numeric=Number(value);
        if(!Number.isFinite(numeric))return String(value ?? '0');
        return numeric.toFixed(3).replace(/\.?0+$/,'');
    };
    const get=async url=>{
        const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}});
        const result=await response.json().catch(()=>null);
        if(!response.ok||result?.ok!==true)throw Error(result?.error?.message||'Gagal memuat Bank.');
        return result.data;
    };
    let items=[];
    const render=()=>{
        const target=$('printBankQuestions');target.replaceChildren();
        for(const [i,item] of items.entries()){
            const section=document.createElement('section');section.className='question';
            const title=document.createElement('h2');title.textContent=(i+1)+'. '+item.question_type+' · '+numberLabel(item.max_point)+' poin';
            section.append(title,window.CbtQuestionRenderer.render(item,{showAnswer:$('printBankAnswer').checked}));target.append(section);
        }
    };
    $('printBankAnswer').addEventListener('change',render);
    $('printBankButton').addEventListener('click',()=>window.print());
    (async()=>{
        try {
            let page=1,pages=1,list;
            do {list=await get(base+'?'+new URLSearchParams({type:'ALL',per_page:100,page}));
                pages=Number(list.pagination.pages);items.push(...list.items);page++;
            } while(page<=pages);
            $('printBankTitle').textContent=list.bank.nama_bank;
            $('printBankContext').textContent=list.bank.kegiatan_nama+' · '+list.bank.mapel_nama+' · Tingkat '+list.bank.tingkat;
            items=await Promise.all(items.map(async item=>(await get(base+'/'+item.id)).item));
            render();$('printBankButton').disabled=false;
        }catch(error){$('printBankTitle').textContent='Cetak gagal';$('printBankContext').textContent=error.message;}
    })();
})();
