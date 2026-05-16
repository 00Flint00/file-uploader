(() => {
    'use strict';

    // Translations
    const i18n = {
        en: {
            passwordTitle: 'Access Required',
            passwordSubtitle: 'Please enter the upload password',
            passwordPlaceholder: 'Enter password',
            passwordBtn: 'Request Access',
            togglePasswordLabel: 'Show password',
            passwordEmpty: 'Please enter a password',
            passwordWrong: 'Wrong password or expired',
            passwordConnError: 'Connection error. Please try again.',
            passwordChecking: 'Checking...',
            uploadTitle: 'File Upload',
            uploadSubtitle: 'Drag files here or click to browse',
            dropText: 'Drag files here or <span>browse</span>',
            dropHint: 'All file types allowed \u2014 No compression',
            uploadBtn: 'Upload',
            clearBtn: 'Clear all',
            uploading: 'Uploading...',
            uploadSuccess: 'All files uploaded successfully!',
            uploadFailed: 'Upload failed',
            sessionExpired: 'Session expired. Please reload the page.',
            networkError: 'Network error',
            invalidResponse: 'Invalid server response',
            removeTitle: 'Remove',
        },
        de: {
            passwordTitle: 'Zugang erforderlich',
            passwordSubtitle: 'Bitte gib das Upload-Passwort ein',
            passwordPlaceholder: 'Passwort eingeben',
            passwordBtn: 'Zugang anfordern',
            togglePasswordLabel: 'Passwort anzeigen',
            passwordEmpty: 'Bitte Passwort eingeben',
            passwordWrong: 'Falsches Passwort oder abgelaufen',
            passwordConnError: 'Verbindungsfehler. Bitte erneut versuchen.',
            passwordChecking: 'Pr\u00fcfe...',
            uploadTitle: 'Datei-Upload',
            uploadSubtitle: 'Ziehe Dateien hierher oder klicke zum Ausw\u00e4hlen',
            dropText: 'Dateien hierher ziehen oder <span>durchsuchen</span>',
            dropHint: 'Alle Dateitypen erlaubt \u2014 Keine Komprimierung',
            uploadBtn: 'Hochladen',
            clearBtn: 'Alle entfernen',
            uploading: 'Hochladen...',
            uploadSuccess: 'Alle Dateien erfolgreich hochgeladen!',
            uploadFailed: 'Upload fehlgeschlagen',
            sessionExpired: 'Sitzung abgelaufen. Bitte Seite neu laden.',
            networkError: 'Netzwerkfehler',
            invalidResponse: 'Ung\u00fcltige Server-Antwort',
            removeTitle: 'Entfernen',
        },
    };

    // State
    let uploadToken = '';
    let selectedFiles = [];
    let currentLang = 'en';

    // DOM Elements
    const languageOverlay = document.getElementById('languageOverlay');
    const overlay = document.getElementById('passwordOverlay');
    const passwordInput = document.getElementById('passwordInput');
    const passwordForm = document.getElementById('passwordForm');
    const passwordError = document.getElementById('passwordError');
    const passwordBtn = document.getElementById('passwordBtn');
    const togglePasswordBtn = document.getElementById('togglePassword');

    const uploadContainer = document.getElementById('uploadContainer');
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    const uploadBtn = document.getElementById('uploadBtn');
    const clearBtn = document.getElementById('clearBtn');
    const uploadActions = document.getElementById('uploadActions');
    const progressWrapper = document.getElementById('progressWrapper');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const statusMessage = document.getElementById('statusMessage');

    // Translation helper
    function t(key) {
        return i18n[currentLang][key] || i18n.en[key] || key;
    }

    function applyTranslations() {
        document.getElementById('htmlRoot').lang = currentLang;

        document.querySelectorAll('[data-i18n]').forEach((el) => {
            el.textContent = t(el.dataset.i18n);
        });
        document.querySelectorAll('[data-i18n-html]').forEach((el) => {
            el.innerHTML = t(el.dataset.i18nHtml);
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach((el) => {
            el.placeholder = t(el.dataset.i18nPlaceholder);
        });
        document.querySelectorAll('[data-i18n-aria]').forEach((el) => {
            el.setAttribute('aria-label', t(el.dataset.i18nAria));
        });
    }

    // Language selection
    function handleLanguageSelect(lang) {
        currentLang = lang;
        applyTranslations();
        languageOverlay.classList.remove('active');
        overlay.classList.add('active');
        passwordInput.focus();
    }

    // Initialize
    function init() {
        languageOverlay.classList.add('active');

        // Language buttons
        document.getElementById('langEn').addEventListener('click', () => handleLanguageSelect('en'));
        document.getElementById('langDe').addEventListener('click', () => handleLanguageSelect('de'));

        // Password form
        passwordForm.addEventListener('submit', handlePasswordSubmit);
        togglePasswordBtn.addEventListener('click', togglePasswordVisibility);

        // Drop zone
        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', handleDragOver);
        dropZone.addEventListener('dragleave', handleDragLeave);
        dropZone.addEventListener('drop', handleDrop);
        fileInput.addEventListener('change', handleFileSelect);

        // Buttons
        uploadBtn.addEventListener('click', handleUpload);
        clearBtn.addEventListener('click', clearFiles);
    }

    // Password handling
    async function handlePasswordSubmit(e) {
        e.preventDefault();
        const password = passwordInput.value.trim();

        if (!password) {
            showPasswordError(t('passwordEmpty'));
            return;
        }

        setPasswordLoading(true);
        hidePasswordError();

        try {
            const response = await fetch('api/verify-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password }),
            });

            const data = await response.json();

            if (data.success && data.token) {
                uploadToken = data.token;
                overlay.classList.remove('active');
                uploadContainer.classList.add('active');
            } else {
                showPasswordError(data.error || t('passwordWrong'));
                passwordInput.value = '';
                passwordInput.focus();
            }
        } catch {
            showPasswordError(t('passwordConnError'));
        } finally {
            setPasswordLoading(false);
        }
    }

    function togglePasswordVisibility() {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        togglePasswordBtn.innerHTML = isPassword
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    }

    function showPasswordError(msg) {
        passwordError.textContent = msg;
    }

    function hidePasswordError() {
        passwordError.textContent = '';
    }

    function setPasswordLoading(loading) {
        passwordBtn.disabled = loading;
        passwordBtn.innerHTML = loading
            ? '<div class="spinner"></div> ' + t('passwordChecking')
            : t('passwordBtn');
    }

    // Drag & Drop
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('dragover');
    }

    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('dragover');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('dragover');

        const items = e.dataTransfer.items;
        if (items) {
            const entries = [];
            for (let i = 0; i < items.length; i++) {
                const entry = items[i].webkitGetAsEntry?.();
                if (entry) {
                    entries.push(entry);
                }
            }
            if (entries.length > 0) {
                processEntries(entries);
                return;
            }
        }

        addFiles(Array.from(e.dataTransfer.files));
    }

    async function processEntries(entries) {
        const files = [];

        async function readEntry(entry, path) {
            if (entry.isFile) {
                return new Promise((resolve) => {
                    entry.file((file) => {
                        const relativePath = path ? path + '/' + file.name : file.name;
                        Object.defineProperty(file, 'relativePath', {
                            value: relativePath,
                            writable: false,
                        });
                        files.push(file);
                        resolve();
                    });
                });
            } else if (entry.isDirectory) {
                const reader = entry.createReader();
                return new Promise((resolve) => {
                    const readBatch = () => {
                        reader.readEntries(async (entries) => {
                            if (entries.length === 0) {
                                resolve();
                                return;
                            }
                            const dirPath = path ? path + '/' + entry.name : entry.name;
                            for (const child of entries) {
                                await readEntry(child, dirPath);
                            }
                            readBatch();
                        });
                    };
                    readBatch();
                });
            }
        }

        for (const entry of entries) {
            await readEntry(entry, '');
        }

        addFiles(files);
    }

    function handleFileSelect(e) {
        addFiles(Array.from(e.target.files));
        fileInput.value = '';
    }

    // File management
    function addFiles(files) {
        for (const file of files) {
            const exists = selectedFiles.some(
                (f) => f.name === file.name && f.size === file.size
            );
            if (!exists) {
                selectedFiles.push(file);
            }
        }
        renderFileList();
        updateActions();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        renderFileList();
        updateActions();
    }

    function clearFiles() {
        selectedFiles = [];
        renderFileList();
        updateActions();
        hideStatus();
    }

    function renderFileList() {
        if (selectedFiles.length === 0) {
            fileList.innerHTML = '';
            return;
        }

        fileList.innerHTML = selectedFiles
            .map(
                (file, index) => `
            <li class="file-item">
                <div class="file-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <div class="file-info">
                    <div class="file-name" title="${escapeHtml(file.relativePath || file.name)}">${escapeHtml(file.relativePath || file.name)}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                </div>
                <button class="file-remove" onclick="window.__removeFile(${index})" title="${t('removeTitle')}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </li>
        `
            )
            .join('');
    }

    // Expose remove function
    window.__removeFile = removeFile;

    function updateActions() {
        const hasFiles = selectedFiles.length > 0;
        uploadActions.style.display = hasFiles ? 'flex' : 'none';
    }

    // Upload
    async function handleUpload() {
        if (selectedFiles.length === 0) return;

        const formData = new FormData();
        formData.append('upload_token', uploadToken);

        for (const file of selectedFiles) {
            formData.append('files[]', file, file.relativePath || file.name);
        }

        setUploading(true);
        hideStatus();

        try {
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = percent + '%';
                    progressText.textContent = `${percent}% \u2014 ${formatFileSize(e.loaded)} / ${formatFileSize(e.total)}`;
                }
            });

            const result = await new Promise((resolve, reject) => {
                xhr.onload = () => {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch {
                        reject(new Error(t('invalidResponse')));
                    }
                };
                xhr.onerror = () => reject(new Error(t('networkError')));
                xhr.open('POST', 'api/upload.php');
                xhr.send(formData);
            });

            if (result.success) {
                showStatus('success', result.message || t('uploadSuccess'));
                selectedFiles = [];
                renderFileList();
                updateActions();
            } else {
                let errorMsg = result.message || t('uploadFailed');
                if (result.results) {
                    const failed = result.results.filter((r) => !r.success);
                    if (failed.length > 0) {
                        errorMsg += ': ' + failed.map((f) => `${f.name} (${f.error})`).join(', ');
                    }
                }
                showStatus('error', errorMsg);
            }
        } catch (err) {
            if (err.message.includes('Token') || err.message.includes('401')) {
                showStatus('error', t('sessionExpired'));
            } else {
                showStatus('error', err.message || t('uploadFailed'));
            }
        } finally {
            setUploading(false);
        }
    }

    function setUploading(uploading) {
        uploadBtn.disabled = uploading;
        clearBtn.disabled = uploading;
        progressWrapper.classList.toggle('active', uploading);

        if (uploading) {
            progressFill.style.width = '0%';
            progressText.textContent = t('uploading');
            uploadBtn.innerHTML = '<div class="spinner"></div> ' + t('uploading');
        } else {
            uploadBtn.innerHTML = t('uploadBtn');
        }
    }

    // Status messages
    function showStatus(type, message) {
        statusMessage.className = 'status-message active ' + type;
        statusMessage.textContent = message;
    }

    function hideStatus() {
        statusMessage.className = 'status-message';
    }

    // Utilities
    function formatFileSize(bytes) {
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
        return bytes + ' B';
    }

    function escapeHtml(text) {
        const el = document.createElement('span');
        el.textContent = text;
        return el.innerHTML;
    }

    // Start
    document.addEventListener('DOMContentLoaded', init);
})();
