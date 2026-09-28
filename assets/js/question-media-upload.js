(() => {
    'use strict';
    window.CbtMediaPreview = window.CbtMediaPreview || {};
    for (const wrapper of document.querySelectorAll('[data-media-insert]')) {
        const field = document.getElementById(wrapper.dataset.mediaInsert);
        const input = wrapper.querySelector('input[type="file"]');
        const status = wrapper.querySelector('small');
        const upload = wrapper.querySelector('button');
        if (!field || !input || !upload) continue;

        const insert = (item) => {
            const token = '[[media:' + item.id + ']]';
            const start = field.selectionStart ?? field.value.length;
            const end = field.selectionEnd ?? start;
            window.CbtMediaPreview[item.id] = {kind: item.kind, url: wrapper.dataset.api + '/' + item.id};
            field.value = field.value.slice(0, start) + token + field.value.slice(end);
            field.dispatchEvent(new Event('input', {bubbles: true}));
            field.focus();
            field.setSelectionRange(start + token.length, start + token.length);
            status.textContent = 'Gambar tersisip. Kode internal dapat dipindahkan ke bagian soal lain.';
        };

        upload.addEventListener('click', async () => {
            if (!input.files.length) {status.textContent = 'Pilih gambar dahulu.'; return;}
            upload.disabled = true; status.textContent = 'Mengunggah gambar...';
            try {
                const data = new FormData(); data.append('file', input.files[0]);
                const response = await fetch(wrapper.dataset.api, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                    body: data,
                });
                const result = await response.json().catch(() => null);
                if (!response.ok || result?.ok !== true) throw Error(result?.error?.message || 'Unggah gambar gagal.');
                insert(result.data); input.value = '';
            } catch (error) {status.textContent = error.message;}
            finally {upload.disabled = false;}
        });
    }
})();