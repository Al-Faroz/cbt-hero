// Renderer konten teks PG untuk preview Manager; dapat dipakai ulang oleh Exam Client.
window.CbtQuestionRenderer = Object.freeze({
    renderPg(question, {showAnswer = false} = {}) {
        const article = document.createElement('article');
        const stem = document.createElement('div');
        stem.className = 'fw-semibold mb-3'; stem.style.whiteSpace = 'pre-wrap';
        stem.textContent = question.question_text || '';
        article.append(stem);
        const list = document.createElement('div'); list.className = 'd-grid gap-2';
        for (const option of question.options || []) {
            const line = document.createElement('div');
            line.className = 'border rounded p-2' + (showAnswer && option.option_key === question.correct_key ? ' border-success' : '');
            const key = document.createElement('strong'); key.textContent = option.option_key + '. ';
            const content = document.createElement('span'); content.style.whiteSpace = 'pre-wrap';
            content.textContent = option.content_text || '';
            line.append(key, content);
            if (showAnswer && option.option_key === question.correct_key) {
                const badge = document.createElement('span'); badge.className = 'badge text-bg-success ms-2';
                badge.textContent = 'Kunci'; line.append(badge);
            }
            list.append(line);
        }
        article.append(list);
        return article;
    },
});
