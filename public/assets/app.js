(() => {
    'use strict';

    const formatBytes = (bytes) => {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    const bindFilePreview = (root = document) => {
        const input = root.querySelector('#print-document');
        const preview = root.querySelector('#file-preview');

        if (!input || !preview || input.dataset.previewBound === 'true') return;
        input.dataset.previewBound = 'true';

        let objectUrl = null;

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }

            preview.replaceChildren();

            if (!file) {
                const empty = document.createElement('div');
                empty.className = 'file-preview-empty';
                const icon = document.createElement('span');
                icon.className = 'preview-icon';
                icon.setAttribute('aria-hidden', 'true');
                icon.textContent = '□';
                const message = document.createElement('span');
                message.textContent = preview.dataset.emptyLabel || '';
                empty.append(icon, message);
                preview.append(empty);
                return;
            }

            const card = document.createElement('div');
            card.className = 'file-preview-card';

            if (file.type === 'image/png' || file.type === 'image/jpeg') {
                objectUrl = URL.createObjectURL(file);
                const image = document.createElement('img');
                image.className = 'file-preview-image';
                image.src = objectUrl;
                image.alt = `${input.dataset.previewImageLabel || 'Preview'}: ${file.name}`;
                card.append(image);
            } else {
                const icon = document.createElement('span');
                icon.className = 'file-preview-pdf';
                icon.setAttribute('aria-hidden', 'true');
                icon.textContent = 'PDF';
                card.append(icon);
            }

            const details = document.createElement('div');
            details.className = 'file-preview-details';
            const name = document.createElement('strong');
            name.textContent = file.name;
            const size = document.createElement('small');
            size.textContent = formatBytes(file.size);
            details.append(name, size);
            card.append(details);
            preview.append(card);
        });
    };

    document.addEventListener('DOMContentLoaded', () => bindFilePreview());
    document.addEventListener('htmx:afterSwap', (event) => bindFilePreview(event.target));
})();
