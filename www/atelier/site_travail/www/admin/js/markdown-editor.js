// markdown-editor.js - Éditeur Markdown simple avec aperçu
document.addEventListener('DOMContentLoaded', function() {
    // Fonction de conversion Markdown → HTML (basique)
    function markdownToHtml(md) {
        if (!md) return '';
        let html = md
            .replace(/&/g, '&amp;')
            .replace(/</g, '<')
            .replace(/>/g, '>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // **gras**
            .replace(/\*(.*?)\*/g, '<em>$1</em>')             // *italique*
            .replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>') // [texte](url)
            .replace(/\n{2,}/g, '</p><p>')                    // Paragraphe
            .replace(/\n/g, '<br>');                          // Saut de ligne
        return `<p>${html}</p>`;
    }

    // Appliquer à tous les textarea avec la classe "markdown-editor"
    document.querySelectorAll('textarea.markdown-editor').forEach(textarea => {
        const container = document.createElement('div');
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';

        const editorContainer = document.createElement('div');
        editorContainer.style.display = 'flex';
        editorContainer.style.gap = '10px';

        const editor = document.createElement('div');
        editor.contentEditable = true;
        editor.style.border = '1px solid #ccc';
        editor.style.borderRadius = '4px';
        editor.style.padding = '10px';
        editor.style.minHeight = '200px';
        editor.style.flex = '1';
        editor.style.fontFamily = 'monospace';
        editor.style.fontSize = '14px';
        editor.textContent = textarea.value;

        const preview = document.createElement('div');
        preview.style.border = '1px solid #eee';
        preview.style.borderRadius = '4px';
        preview.style.padding = '10px';
        preview.style.minHeight = '200px';
        preview.style.flex = '1';
        preview.style.backgroundColor = '#fafafa';
        preview.innerHTML = markdownToHtml(textarea.value);

        editor.addEventListener('input', () => {
            textarea.value = editor.textContent;
            preview.innerHTML = markdownToHtml(editor.textContent);
        });

        editorContainer.appendChild(editor);
        editorContainer.appendChild(preview);
        container.appendChild(editorContainer);

        // Instructions
        const help = document.createElement('small');
        help.innerHTML = '📝 Syntaxe : **gras**, *italique*, [texte](https://url.com)';
        help.style.color = '#666';
        container.appendChild(help);

        textarea.parentNode.replaceChild(container, textarea);
    });
});