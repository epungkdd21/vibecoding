<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workspace.php';

require_login();

$user = get_logged_in_user();
$targetStudentId = (int) ($_GET['student_id'] ?? $user['id']);

if ($user['role'] === 'student' && $targetStudentId !== (int) $user['id']) {
    redirect('dashboard.php');
}

if ($user['role'] === 'teacher' || $user['role'] === 'admin') {
    $targetStudentId = max(1, $targetStudentId);
}

$targetUser = null;
if ($targetStudentId > 0) {
    $pdo = get_main_db();
    $stmt = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $targetStudentId]);
    $targetUser = $stmt->fetch();
}

if (!$targetUser) {
    redirect('dashboard.php');
}

ensure_student_workspace($targetStudentId);
$studentTree = get_student_tree($targetStudentId);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace - EduCode</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
    <style>
        body {
            padding: 24px 18px 60px;
        }

        .workspace-shell {
            max-width: 1280px;
            margin: 0 auto;
        }

        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .workspace-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 18px;
            min-height: 700px;
        }

        .panel {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 18px;
            overflow: hidden;
        }

        .sidebar {
            padding: 18px;
        }

        .sidebar h2 {
            margin-top: 0;
            margin-bottom: 14px;
        }

        .tree {
            list-style: none;
            padding-left: 0;
            margin: 0;
            color: #dfeaf9;
        }

        .tree li {
            margin: 6px 0;
        }

        .tree button {
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            text-align: left;
            padding: 6px 8px;
            width: 100%;
            border-radius: 10px;
        }

        .tree button:hover {
            background: rgba(148, 163, 184, 0.08);
        }

        .main-panel {
            display: flex;
            flex-direction: column;
            min-height: 700px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        .action-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .button-like {
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(148, 163, 184, 0.07);
            border-radius: 10px;
            padding: 10px 12px;
            color: #e2e8f0;
            cursor: pointer;
            font-weight: 600;
        }

        .primary {
            background: linear-gradient(135deg, #60a5fa, #2563eb);
            border-color: transparent;
        }

        .danger {
            background: rgba(239, 68, 68, 0.14);
            border-color: rgba(239, 68, 68, 0.22);
            color: #fecaca;
        }

        .workspace-editor-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(280px, 0.7fr);
            gap: 18px;
            padding: 18px;
            height: calc(100% - 72px);
            min-height: 620px;
        }

        .editor-panel,
        .preview-panel {
            min-height: 560px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .file-meta {
            padding: 14px 18px 0;
            color: #93a4b8;
        }

        .editor-shell {
            flex: 1;
            min-height: 420px;
            border-top: 1px solid rgba(148, 163, 184, 0.12);
            margin-top: 12px;
        }

        .CodeMirror {
            height: 100%;
            min-height: 420px;
            font-size: 0.92rem;
            line-height: 1.7;
            background: rgba(2, 6, 23, 0.65);
        }

        .preview-header {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            color: #dfeaf9;
        }

        .preview-frame {
            width: 100%;
            height: 100%;
            min-height: 420px;
            border: 0;
            background: white;
        }

        .sql-panel {
            margin-top: 18px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 18px;
            overflow: hidden;
        }

        .sql-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        }

        .sql-editor {
            width: 100%;
            min-height: 140px;
            background: rgba(2, 6, 23, 0.7);
            color: #e2e8f0;
            border: 0;
            padding: 16px 18px;
            font-family: 'SFMono-Regular', Consolas, monospace;
            resize: vertical;
        }

        .sql-results {
            padding: 14px 18px 18px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 12px;
            background: rgba(2, 6, 23, 0.4);
        }

        .button-like:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            color: #dfeaf9;
        }

        th, td {
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: rgba(148, 163, 184, 0.06);
            color: #f8fafc;
        }

        .status-bar {
            padding: 14px 18px;
            border-top: 1px solid rgba(148, 163, 184, 0.12);
            color: #93a4b8;
            font-size: 0.88rem;
        }

        .cm-error-line {
            background: rgba(239, 68, 68, 0.12);
            border-left: 3px solid #ef4444;
        }

        .sqlite-table-list {
            padding: 12px 14px;
            color: #dfeaf9;
        }

        .sqlite-table-list ul {
            margin: 10px 0 0;
            padding-left: 18px;
            line-height: 1.8;
        }

        .sqlite-table-button {
            padding: 2px 0;
            border: 0;
            background: transparent;
            color: #93c5fd;
            cursor: pointer;
            font: inherit;
            text-align: left;
        }

        .sqlite-table-button:hover {
            color: #dbeafe;
            text-decoration: underline;
        }

        .create-item-dialog {
            width: min(420px, calc(100% - 32px));
            padding: 0;
            color: #e2e8f0;
            background: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 12px;
        }

        .create-item-dialog::backdrop {
            background: rgba(2, 6, 23, 0.72);
        }

        .create-item-dialog form {
            padding: 20px;
        }

        .create-item-dialog h2 {
            margin: 0 0 18px;
            font-size: 1.15rem;
        }

        .create-item-dialog label {
            display: block;
            margin-bottom: 8px;
            color: #cbd5e1;
        }

        .create-item-dialog input {
            width: 100%;
            padding: 10px 12px;
            color: #f8fafc;
            background: #020617;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 6px;
            font: inherit;
        }

        .create-item-dialog input:focus {
            outline: 2px solid #60a5fa;
            outline-offset: 1px;
        }

        .create-item-error {
            min-height: 1.25em;
            margin: 8px 0 0;
            color: #fca5a5;
            font-size: 0.9rem;
        }

        .create-item-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 18px;
        }

        @media (max-width: 900px) {
            .workspace-layout {
                grid-template-columns: 1fr;
            }

            .workspace-editor-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="workspace-shell">
        <div class="workspace-header">
            <div>
                <span class="badge secondary">Workspace</span>
                <h1 style="margin: 12px 0 0;"><?= htmlspecialchars($targetUser['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="action-group">
                <a class="button-like" href="dashboard.php">Kembali</a>
                <?php if ($user['role'] === 'student'): ?>
                    <button class="button-like primary" id="new-folder-btn" type="button">+ Folder</button>
                    <button class="button-like primary" id="new-file-btn" type="button">+ File</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="workspace-layout">
            <aside class="panel sidebar">
                <h2>File Tree</h2>
                <ul class="tree" id="file-tree"></ul>
            </aside>

            <section class="panel main-panel">
                <div class="toolbar">
                    <div class="action-group">
                        <button class="button-like" id="refresh-btn" type="button">Refresh</button>
                        <button class="button-like" id="format-btn" type="button">Format</button>
                        <?php if ($user['role'] === 'student'): ?>
                            <button class="button-like primary" id="save-btn" type="button">Save</button>
                            <button class="button-like danger" id="delete-btn" type="button">Delete</button>
                        <?php endif; ?>
                    </div>
                    <div id="current-path" class="muted">/</div>
                </div>

                <div class="workspace-editor-layout">
                    <div class="panel editor-panel">
                        <div class="file-meta">File aktif: <strong id="active-file">Belum dipilih</strong></div>
                        <div id="editor-container" class="editor-shell"></div>
                    </div>

                    <div class="panel preview-panel">
                        <div class="preview-header">
                            <span>Live Preview</span>
                            <button type="button" id="preview-refresh-btn" class="button-like primary">Refresh</button>
                        </div>
                        <iframe id="live-preview" class="preview-frame" sandbox="allow-scripts allow-same-origin"></iframe>
                    </div>
                </div>

                <div class="sql-panel">
                    <div class="sql-toolbar">
                        <strong>SQLite Student Database</strong>
                        <button type="button" id="run-sql-btn" class="button-like primary">Run SQL</button>
                    </div>
                    <textarea id="sql-editor" class="sql-editor" placeholder="Contoh: CREATE TABLE IF NOT EXISTS siswa (id INTEGER PRIMARY KEY, nama TEXT); INSERT INTO siswa (nama) VALUES ('Dewi'); SELECT * FROM siswa;">SELECT name FROM sqlite_master WHERE type = 'table';</textarea>
                    <div class="sql-results">
                        <div id="sql-results" class="table-wrap"></div>
                    </div>
                </div>

                <div class="status-bar" id="status-bar">Siap.</div>
            </section>
        </div>
    </div>

    <dialog id="create-item-dialog" class="create-item-dialog" aria-labelledby="create-item-title">
        <form id="create-item-form">
            <h2 id="create-item-title">Buat item baru</h2>
            <label for="create-item-name">Nama</label>
            <input id="create-item-name" name="name" type="text" maxlength="64" autocomplete="off" required>
            <p id="create-item-error" class="create-item-error" role="alert"></p>
            <div class="create-item-actions">
                <button class="button-like" id="cancel-create-item" type="button">Batal</button>
                <button class="button-like primary" id="submit-create-item" type="submit">Buat</button>
            </div>
        </form>
    </dialog>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/selection/active-line.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/show-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/javascript-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/css-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/hint/html-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.1/beautifier.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.1/beautify-html.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.1/beautify-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/js-beautify/1.15.1/beautify.min.js"></script>
    <script>
        const userRole = <?= json_encode($user['role']); ?>;
        const studentId = <?= (int) $targetStudentId; ?>;
        const state = {
            tree: [],
            currentPath: '',
            currentFile: '',
            currentFileName: '',
            parentPath: '',
            autosaveTimer: null,
            isLoadingFile: false,
            pendingCreateType: '',
        };

        const fileTree = document.getElementById('file-tree');
        const statusBar = document.getElementById('status-bar');
        const currentPathLabel = document.getElementById('current-path');
        const activeFileLabel = document.getElementById('active-file');
        const previewFrame = document.getElementById('live-preview');
        const newFolderButton = document.getElementById('new-folder-btn');
        const newFileButton = document.getElementById('new-file-btn');

        if (newFolderButton) newFolderButton.addEventListener('click', () => createItem('folder'));
        if (newFileButton) newFileButton.addEventListener('click', () => createItem('file'));

        const editor = CodeMirror(document.getElementById('editor-container'), {
            mode: 'htmlmixed',
            theme: 'material-darker',
            lineNumbers: true,
            tabSize: 2,
            value: '',
            autoCloseBrackets: true,
            matchBrackets: true,
            styleActiveLine: true,
            extraKeys: {
                'Ctrl-Space': 'autocomplete',
                'Cmd-Space': 'autocomplete',
            },
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showStatus(message, isError = false) {
            statusBar.textContent = message;
            statusBar.style.color = isError ? '#fecaca' : '#93a4b8';
        }

        function updateCurrentPath() {
            currentPathLabel.textContent = state.currentPath || '/';
        }

        function detectMode(fileName) {
            const lower = (fileName || '').toLowerCase();
            if (lower.endsWith('.css')) return 'text/css';
            if (lower.endsWith('.js')) return 'javascript';
            if (lower.endsWith('.json')) return 'application/json';
            if (lower.endsWith('.php')) return 'php';
            if (lower.endsWith('.sqlite') || lower.endsWith('.db')) return 'sql';
            return 'htmlmixed';
        }

        function getFileExtension(fileName) {
            return (fileName || '').split('.').pop()?.toLowerCase() || '';
        }

        function isDatabaseFile(fileName) {
            return ['sqlite', 'db', 'db3'].includes(getFileExtension(fileName));
        }

        function getHintWords(fileName) {
            const ext = getFileExtension(fileName);
            if (ext === 'html' || ext === 'htm') {
                return ['html', 'head', 'body', 'div', 'section', 'main', 'header', 'nav', 'footer', 'article', 'aside', 'button', 'form', 'input', 'img', 'a', 'p', 'span', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'script', 'style', 'meta', 'link'];
            }
            if (ext === 'css') {
                return ['display', 'position', 'margin', 'padding', 'color', 'background', 'font-size', 'border', 'flex', 'grid', 'width', 'height', 'justify-content', 'align-items', 'gap', '@media', '@keyframes'];
            }
            if (ext === 'js') {
                return ['const', 'let', 'var', 'function', 'return', 'if', 'else', 'for', 'while', 'document', 'querySelector', 'addEventListener', 'console', 'alert', 'appendChild', 'innerHTML', 'classList', 'setAttribute'];
            }
            if (ext === 'php') {
                return ['<' + '?php', 'echo', 'if', 'else', 'foreach', 'function', 'return', 'include', 'require', 'array', 'count', 'strlen', 'session_start', 'header'];
            }
            return ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'FROM', 'WHERE', 'ORDER', 'BY', 'LIMIT'];
        }

        function suggestCodeHints(cm) {
            const fileName = state.currentFileName || 'index.html';
            const ext = getFileExtension(fileName);
            const words = getHintWords(fileName);
            const cursor = cm.getCursor();
            const token = cm.getTokenAt(cursor);
            const start = token.start;
            const end = cursor.ch;
            const currentText = cm.getRange({ line: cursor.line, ch: start }, { line: cursor.line, ch: end });

            if (ext === 'html' || ext === 'htm') {
                const beforeCursor = cm.getRange({ line: 0, ch: 0 }, cursor);
                const openIndex = beforeCursor.lastIndexOf('<');
                const closeIndex = beforeCursor.lastIndexOf('>');
                if (openIndex > closeIndex) {
                    const tagText = beforeCursor.slice(openIndex);
                    const closingTag = /^<\//.test(tagText);
                    const nameMatch = tagText.match(/^<\/?([A-Za-z][\w:-]*)?/);
                    const nameStart = openIndex + (closingTag ? 2 : 1);

                    if (/^<\/?[A-Za-z\w:-]*$/.test(tagText)) {
                        const candidates = closingTag ? getOpenHtmlTags(beforeCursor.slice(0, openIndex)) : words;
                        const typedName = tagText.slice(closingTag ? 2 : 1).toLowerCase();
                        return {
                            list: candidates.filter((word) => word.toLowerCase().startsWith(typedName)),
                            from: cm.posFromIndex(nameStart),
                            to: cursor,
                        };
                    }

                    const attributePrefix = tagText.match(/[\w:-]*$/)?.[0] || '';
                    const attributes = ['id', 'class', 'style', 'title', 'href', 'src', 'alt', 'type', 'name', 'value', 'placeholder', 'onclick', 'disabled', 'required'];
                    return {
                        list: attributes.filter((attribute) => attribute.startsWith(attributePrefix.toLowerCase())),
                        from: cm.posFromIndex(beforeCursor.length - attributePrefix.length),
                        to: cursor,
                    };
                }
            }

            return {
                list: words.filter((word) => word.toLowerCase().includes((currentText || '').toLowerCase())),
                from: { line: cursor.line, ch: start },
                to: { line: cursor.line, ch: end },
            };
        }

        function getOpenHtmlTags(code) {
            const source = code.replace(/<!--[\s\S]*?-->/g, '');
            const voidTags = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
            const stack = [];
            const tags = source.matchAll(/<\/?([A-Za-z][A-Za-z0-9-]*)(?:\s+(?:"[^"]*"|'[^']*'|[^'">])*)?\s*\/?>/g);
            for (const match of tags) {
                const tag = match[1].toLowerCase();
                if (match[0].startsWith('</')) {
                    const index = stack.lastIndexOf(tag);
                    if (index >= 0) stack.splice(index, 1);
                } else if (!voidTags.has(tag) && !/\/\s*>$/.test(match[0])) {
                    stack.push(tag);
                }
            }
            return [...new Set(stack.reverse())];
        }

        function clearErrorLines() {
            editor.eachLine((line) => editor.removeLineClass(line, 'wrap', 'cm-error-line'));
        }

        function markUnclosedBlocks(code, ext) {
            const issues = [];
            const lines = code.split('\n');

            if (ext === 'html' || ext === 'htm') {
                const voidTags = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
                const stack = [];
                const source = code.replace(/<!--[\s\S]*?-->/g, (comment) => comment.replace(/[^\n]/g, ' '));
                const tags = source.matchAll(/<\/?([A-Za-z][A-Za-z0-9-]*)(?:\s+(?:"[^"]*"|'[^']*'|[^'">])*)?\s*\/?>/g);

                for (const match of tags) {
                    const raw = match[0];
                    const tag = match[1].toLowerCase();
                    const lineNo = source.slice(0, match.index).split('\n').length;
                    if (raw.startsWith('</')) {
                        let matchingIndex = -1;
                        for (let index = stack.length - 1; index >= 0; index -= 1) {
                            if (stack[index].tag === tag) {
                                matchingIndex = index;
                                break;
                            }
                        }
                        if (matchingIndex >= 0) {
                            stack.slice(matchingIndex + 1).forEach((entry) => issues.push(entry.line));
                            stack.splice(matchingIndex);
                        } else {
                            issues.push(lineNo);
                        }
                    } else if (!voidTags.has(tag) && !/\/\s*>$/.test(raw)) {
                        stack.push({ tag, line: lineNo });
                    }
                }
                stack.forEach((entry) => issues.push(entry.line));
            }

            if (ext === 'css' || ext === 'js' || ext === 'php') {
                const masked = maskCodeCommentsAndStrings(code, ext);
                const openDelimiters = [];
                const closingPairs = { ')': '(', ']': '[', '}': '{' };
                let lineNo = 1;
                for (const char of masked.code) {
                    if (char === '(' || char === '[' || char === '{') {
                        openDelimiters.push({ char, line: lineNo });
                    } else if (char in closingPairs) {
                        let matchingIndex = -1;
                        for (let index = openDelimiters.length - 1; index >= 0; index -= 1) {
                            if (openDelimiters[index].char === closingPairs[char]) {
                                matchingIndex = index;
                                break;
                            }
                        }
                        if (matchingIndex >= 0) {
                            openDelimiters.slice(matchingIndex + 1).forEach((entry) => issues.push(entry.line));
                            openDelimiters.splice(matchingIndex);
                        } else {
                            issues.push(lineNo);
                        }
                    }
                    if (char === '\n') lineNo += 1;
                }
                issues.push(...openDelimiters.map((entry) => entry.line));
                if (masked.unclosedStringLine) issues.push(masked.unclosedStringLine);
            }

            return [...new Set(issues.filter((line) => line > 0))];
        }

        function maskCodeCommentsAndStrings(code, ext) {
            const chars = [...code];
            let stateName = '';
            let quote = '';
            let escaped = false;
            let stringStartLine = 0;
            let lineNo = 1;

            for (let index = 0; index < chars.length; index += 1) {
                const char = chars[index];
                const next = chars[index + 1];

                if (stateName === 'line-comment') {
                    if (char === '\n') stateName = '';
                    else chars[index] = ' ';
                    if (char === '\n') lineNo += 1;
                    continue;
                }
                if (stateName === 'block-comment') {
                    if (char === '*' && next === '/') {
                        chars[index] = ' ';
                        chars[index + 1] = ' ';
                        index += 1;
                        stateName = '';
                    } else if (char !== '\n') {
                        chars[index] = ' ';
                    }
                    if (char === '\n') lineNo += 1;
                    continue;
                }
                if (stateName === 'string') {
                    if (char !== '\n') chars[index] = ' ';
                    if (escaped) {
                        escaped = false;
                    } else if (char === '\\') {
                        escaped = true;
                    } else if (char === quote) {
                        stateName = '';
                    }
                    if (char === '\n') lineNo += 1;
                    continue;
                }

                if (char === '/' && next === '/') {
                    chars[index] = ' ';
                    chars[index + 1] = ' ';
                    index += 1;
                    stateName = 'line-comment';
                } else if (char === '/' && next === '*') {
                    chars[index] = ' ';
                    chars[index + 1] = ' ';
                    index += 1;
                    stateName = 'block-comment';
                } else if (ext === 'php' && char === '#') {
                    chars[index] = ' ';
                    stateName = 'line-comment';
                } else if (char === '"' || char === "'" || (ext === 'js' && char === '`')) {
                    quote = char;
                    stringStartLine = lineNo;
                    chars[index] = ' ';
                    stateName = 'string';
                }
                if (char === '\n') lineNo += 1;
            }

            return {
                code: chars.join(''),
                unclosedStringLine: stateName === 'string' ? stringStartLine : 0,
            };
        }

        function prettyFormatCode(code, ext) {
            const trimmed = (code || '').trim();
            if (!trimmed) {
                return '';
            }

            if (ext === 'html' || ext === 'htm') {
                return typeof html_beautify === 'function'
                    ? html_beautify(trimmed, { indent_size: 2, preserve_newlines: true, max_preserve_newlines: 2 })
                    : trimmed;
            }

            if (ext === 'css') {
                return typeof css_beautify === 'function'
                    ? css_beautify(trimmed, { indent_size: 2 })
                    : trimmed;
            }

            if (ext === 'js') {
                return typeof js_beautify === 'function'
                    ? js_beautify(trimmed, { indent_size: 2, preserve_newlines: true, max_preserve_newlines: 2 })
                    : trimmed;
            }

            if (ext === 'php') {
                let indent = 0;
                const lines = trimmed.split('\n').map((line) => line.trim()).filter((line) => line.length > 0);
                const formatted = [];
                lines.forEach((line) => {
                    if (/^\s*(\}|\))/.test(line)) {
                        indent = Math.max(0, indent - 1);
                    }
                    formatted.push('  '.repeat(indent) + line);
                    if (/(\{|\()\s*$/.test(line) && !line.includes('else')) {
                        indent += 1;
                    }
                });
                return formatted.join('\n');
            }

            return trimmed;
        }

        function formatCurrentCode() {
            if (!state.currentFile) {
                showStatus('Pilih file yang ingin diformat.', true);
                return;
            }

            const ext = getFileExtension(state.currentFileName || '');
            const formatterAvailable = ext === 'html' || ext === 'htm'
                ? typeof html_beautify === 'function'
                : ext === 'css'
                    ? typeof css_beautify === 'function'
                    : ext === 'js'
                        ? typeof js_beautify === 'function'
                        : true;
            if (!formatterAvailable) {
                showStatus('Formatter belum dapat dimuat. Periksa koneksi internet lalu coba lagi.', true);
                return;
            }
            const formatted = prettyFormatCode(editor.getValue(), ext);
            editor.setValue(formatted);
            updateEditorWarnings();
            scheduleCurrentFileAutosave(formatted);
            showStatus('Kode berhasil diformat.');
        }

        function updateEditorWarnings() {
            clearErrorLines();
            const fileName = state.currentFileName || 'index.html';
            const ext = getFileExtension(fileName);
            const issues = markUnclosedBlocks(editor.getValue(), ext);
            issues.forEach((lineNo) => {
                editor.addLineClass(lineNo - 1, 'wrap', 'cm-error-line');
            });
            if (issues.length > 0) {
                showStatus('Terdapat blok yang belum ditutup atau syntax tidak lengkap.', true);
            } else if (state.currentFile) {
                showStatus(`File siap: ${state.currentFile}`);
            }
        }

        function renderPreview() {
            const fileName = state.currentFileName || '';
            const code = editor.getValue();
            const extension = (fileName.split('.').pop() || '').toLowerCase();

            let doc = '<!doctype html><html><body></body></html>';

            if (extension === 'html' || extension === 'htm') {
                doc = code;
            } else if (extension === 'css') {
                doc = `<!doctype html><html><head><style>${code}</style></head><body><div style="padding:16px; font-family:sans-serif;">Preview CSS</div></body></html>`;
            } else if (extension === 'js') {
                doc = `<!doctype html><html><body><script>${code}<\/script></body></html>`;
            } else if (extension === 'php') {
                doc = `<!doctype html><html><body><pre>${escapeHtml(code)}</pre></body></html>`;
            } else if (code.trim() !== '') {
                doc = `<!doctype html><html><body>${code}</body></html>`;
            }

            previewFrame.srcdoc = doc;
        }

        function renderTree(items, depth = 0) {
            if (!items || !items.length) {
                return '';
            }

            return items.map((item) => {
                const indent = depth * 16;
                const itemPath = item.path || item.name;
                const label = item.name;
                const isFolder = item.type === 'folder';
                const children = isFolder ? renderTree(item.children, depth + 1) : '';

                return `
                    <li style="padding-left:${indent}px;">
                        <button type="button" data-path="${escapeHtml(itemPath)}" data-type="${item.type}" data-name="${escapeHtml(label)}">
                            ${isFolder ? '📁' : '📄'} ${escapeHtml(label)}
                        </button>
                        ${children ? `<ul class="tree">${children}</ul>` : ''}
                    </li>
                `;
            }).join('');
        }

        async function fetchWorkspace(action, payload = {}) {
            const params = new URLSearchParams({ action, student_id: String(studentId), ...payload });
            const response = await fetch(`api/workspace.php?${params.toString()}`);
            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Permintaan gagal.');
            }
            return data;
        }

        async function fetchWorkspaceByPost(action, payload = {}) {
            const response = await fetch('api/workspace.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ action, student_id: String(studentId), ...payload }).toString(),
            });
            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Permintaan gagal.');
            }
            return data;
        }

        async function openFile(path, fileName) {
            state.currentPath = path.includes('/') ? path.substring(0, path.lastIndexOf('/')) : '';
            state.currentFile = path;
            state.currentFileName = fileName;
            state.isLoadingFile = true;
            updateCurrentPath();
            activeFileLabel.textContent = fileName;
            editor.setOption('mode', detectMode(fileName));
            editor.setOption('readOnly', isDatabaseFile(fileName));
            const saveButton = document.getElementById('save-btn');
            if (saveButton) {
                saveButton.disabled = isDatabaseFile(fileName);
                saveButton.title = isDatabaseFile(fileName) ? 'Database SQLite hanya dapat dilihat dari workspace.' : '';
            }

            if (isDatabaseFile(fileName)) {
                try {
                    const response = await fetch(`api/student-db.php?student_id=${studentId}&action=tables`);
                    const data = await response.json();
                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || 'Gagal memuat daftar tabel SQLite.');
                    }
                    const tables = Array.isArray(data.tables) ? data.tables : [];
                    const html = tables.length
                        ? `<ul>${tables.map((table) => `<li><button type="button" class="sqlite-table-button" data-sqlite-table="${escapeHtml(table)}">${escapeHtml(table)}</button></li>`).join('')}</ul>`
                        : '<p>Belum ada tabel di database ini.</p>';
                    document.getElementById('sql-results').innerHTML = `<div class="sqlite-table-list"><strong>Daftar tabel SQLite</strong>${html}</div>`;
                    editor.setValue(`-- Database: ${fileName}\n-- Tabel tersedia:\n${tables.map((table) => `SELECT * FROM ${quoteSqlIdentifier(table)} LIMIT 100;`).join('\n') || '-- belum ada tabel'}`);
                    state.isLoadingFile = false;
                    showStatus(`Database SQLite terhubung: ${tables.length} tabel terdeteksi.`);
                    return;
                } catch (error) {
                    state.isLoadingFile = false;
                    showStatus(error.message, true);
                    return;
                }
            }

            fetchWorkspace('read', { path }).then((data) => {
                editor.setValue(data.content || '');
                state.isLoadingFile = false;
                renderPreview();
                updateEditorWarnings();
                showStatus(`Membuka file: ${path}`);
            }).catch((error) => {
                editor.setValue('');
                state.isLoadingFile = false;
                renderPreview();
                updateEditorWarnings();
                showStatus(error.message, true);
            });
        }

        function bindTreeClickEvents() {
            fileTree.querySelectorAll('button[data-path]').forEach((button) => {
                button.addEventListener('click', () => {
                    const itemPath = button.dataset.path;
                    const itemType = button.dataset.type;
                    if (itemType === 'folder') {
                        state.currentPath = itemPath;
                        updateCurrentPath();
                        const path = itemPath || '/';
                        showStatus(`Folder aktif: ${path}`);
                    } else {
                        openFile(itemPath, button.dataset.name);
                    }
                });
            });
        }

        function renderTreeFromData(treeData) {
            state.tree = treeData;
            fileTree.innerHTML = renderTree(treeData.items || []);
            bindTreeClickEvents();
        }

        async function refreshTree() {
            try {
                const data = await fetchWorkspace('tree');
                renderTreeFromData(data);
                showStatus('Workspace berhasil dimuat.');
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        function scheduleCurrentFileAutosave(content = editor.getValue()) {
            if (userRole !== 'student' || !state.currentFile || state.isLoadingFile || isDatabaseFile(state.currentFileName)) return;

            window.clearTimeout(state.autosaveTimer);
            const path = state.currentFile;
            state.autosaveTimer = window.setTimeout(async () => {
                state.autosaveTimer = null;
                try {
                    const data = await fetchWorkspaceByPost('save-file', { path, content });
                    if (state.currentFile === path) showStatus(`Disimpan otomatis: ${data.path}`);
                } catch (error) {
                    if (state.currentFile === path) showStatus(`Autosave gagal: ${error.message}`, true);
                }
            }, 700);
        }

        async function saveCurrentFile() {
            if (!state.currentFile) {
                showStatus('Pilih file terlebih dahulu.', true);
                return;
            }

            if (isDatabaseFile(state.currentFileName)) {
                showStatus('File database SQLite tidak dapat disimpan sebagai teks.', true);
                return;
            }

            window.clearTimeout(state.autosaveTimer);
            state.autosaveTimer = null;
            try {
                const payload = { path: state.currentFile, content: editor.getValue() };
                const data = await fetchWorkspaceByPost('save-file', payload);
                renderPreview();
                showStatus(`Disimpan: ${data.path}`);
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        function createItem(type) {
            const dialog = document.getElementById('create-item-dialog');
            const title = document.getElementById('create-item-title');
            const nameInput = document.getElementById('create-item-name');
            const errorMessage = document.getElementById('create-item-error');
            state.pendingCreateType = type;
            title.textContent = type === 'folder' ? 'Buat folder baru' : 'Buat file baru';
            nameInput.value = '';
            errorMessage.textContent = '';
            dialog.showModal();
            nameInput.focus();
        }

        async function submitCreateItem(event) {
            event.preventDefault();
            const type = state.pendingCreateType;
            const name = document.getElementById('create-item-name').value.trim();
            const errorMessage = document.getElementById('create-item-error');
            const submitButton = document.getElementById('submit-create-item');
            if (!name) {
                errorMessage.textContent = 'Nama tidak boleh kosong.';
                return;
            }

            const parentPath = state.currentPath || '';
            submitButton.disabled = true;
            errorMessage.textContent = '';
            try {
                const data = await fetchWorkspaceByPost(type === 'folder' ? 'create-folder' : 'create-file', {
                    parent_path: parentPath,
                    name,
                });
                document.getElementById('create-item-dialog').close();
                showStatus(`${type === 'folder' ? 'Folder' : 'File'} dibuat: ${data.path}`);
                await refreshTree();
            } catch (error) {
                errorMessage.textContent = error.message;
            } finally {
                submitButton.disabled = false;
            }
        }

        document.getElementById('create-item-form').addEventListener('submit', submitCreateItem);
        document.getElementById('cancel-create-item').addEventListener('click', () => {
            document.getElementById('create-item-dialog').close();
        });

        async function deleteCurrentFile() {
            if (!state.currentFile) {
                showStatus('Pilih file atau folder yang ingin dihapus.', true);
                return;
            }

            if (isDatabaseFile(state.currentFileName)) {
                showStatus('Database SQLite tidak dapat dihapus dari editor.', true);
                return;
            }

            const confirmed = window.confirm(`Hapus ${state.currentFile}?`);
            if (!confirmed) {
                return;
            }

            try {
                const data = await fetchWorkspaceByPost('delete-item', { path: state.currentFile });
                showStatus(`Dihapus: ${data.path}`);
                state.currentFile = '';
                activeFileLabel.textContent = 'Belum dipilih';
                editor.setValue('');
                renderPreview();
                await refreshTree();
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        editor.on('change', () => {
            if (state.currentFile) {
                renderPreview();
                updateEditorWarnings();
            }
        });

        editor.on('change', (cm, change) => {
            if (change.origin !== 'setValue') scheduleCurrentFileAutosave(cm.getValue());
        });

        window.addEventListener('beforeunload', () => {
            if (userRole !== 'student' || !state.currentFile || state.isLoadingFile || isDatabaseFile(state.currentFileName)) return;

            window.clearTimeout(state.autosaveTimer);
            const payload = new URLSearchParams({
                action: 'save-file',
                student_id: String(studentId),
                path: state.currentFile,
                content: editor.getValue(),
            });
            navigator.sendBeacon('api/workspace.php', payload);
        });

        editor.on('keydown', (cm, event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === ' ') {
                event.preventDefault();
                CodeMirror.showHint(cm, suggestCodeHints, { completeSingle: false, alignWithWord: true });
            }
        });

        editor.on('inputRead', (cm, change) => {
            const extension = getFileExtension(state.currentFileName);
            const insertedText = change.text.join('\n');
            if (change.origin !== 'complete' && ['html', 'htm', 'css', 'js', 'php'].includes(extension) && /[A-Za-z0-9_-]$/.test(insertedText)) {
                const hint = suggestCodeHints(cm);
                const prefixLength = cm.indexFromPos(hint.to) - cm.indexFromPos(hint.from);
                if (prefixLength >= 2 && hint.list.length) {
                    CodeMirror.showHint(cm, suggestCodeHints, { completeSingle: false, alignWithWord: true });
                }
            }

            if (!['html', 'htm'].includes(extension) || !insertedText.includes('>')) return;
            const cursor = cm.getCursor();
            const line = cm.getLine(cursor.line);
            const openingTag = line.slice(0, cursor.ch).match(/<([A-Za-z][\w:-]*)(?:\s[^<>]*)?>$/);
            if (!openingTag) return;

            const tagName = openingTag[1];
            const voidTags = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
            if (voidTags.has(tagName.toLowerCase()) || /\/\s*>$/.test(openingTag[0])) return;
            if (new RegExp(`^\\s*<\\/${tagName}\\s*>`, 'i').test(line.slice(cursor.ch))) return;

            cm.replaceRange(`</${tagName}>`, cursor, cursor, '+input');
            cm.setCursor(cursor);
        });

        async function runSqlQuery() {
            const query = document.getElementById('sql-editor').value.trim();
            if (!query) {
                showStatus('Query SQL tidak boleh kosong.', true);
                return;
            }

            try {
                await executeSqlQuery(query);
                showStatus('Query SQLite berhasil dijalankan.');
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        function quoteSqlIdentifier(identifier) {
            return `"${String(identifier).replace(/"/g, '""')}"`;
        }

        async function executeSqlQuery(query) {
            const form = new URLSearchParams({
                action: 'execute',
                student_id: String(studentId),
                query,
            });
            const response = await fetch('api/student-db.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: form.toString(),
            });
            const data = await response.json();

            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Query gagal dieksekusi.');
            }

            const tableContainer = document.getElementById('sql-results');
            if (data.type === 'read' && data.rows && data.rows.length) {
                const headers = data.columns || Object.keys(data.rows[0]);
                const rowsHtml = data.rows.map((row) => {
                    return `<tr>${headers.map((header) => `<td>${escapeHtml(String(row[header] ?? ''))}</td>`).join('')}</tr>`;
                }).join('');

                const tableList = tableContainer.querySelector('.sqlite-table-list')?.outerHTML || '';
                tableContainer.innerHTML = `${tableList}
                    <table>
                        <thead><tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                `;
            } else {
                const summary = data.type === 'write' ? `Affected rows: ${data.affected_rows ?? 0}` : 'Query berhasil dieksekusi.';
                const tableList = tableContainer.querySelector('.sqlite-table-list')?.outerHTML || '';
                tableContainer.innerHTML = `${tableList}<div style="padding:12px; color:#dfeaf9;">${escapeHtml(summary)}</div>`;
            }
        }

        async function previewSqliteTable(tableName) {
            const query = `SELECT * FROM ${quoteSqlIdentifier(tableName)} LIMIT 100;`;
            document.getElementById('sql-editor').value = query;
            try {
                await executeSqlQuery(query);
                showStatus(`Menampilkan hingga 100 baris dari tabel ${tableName}.`);
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        document.getElementById('sql-results').addEventListener('click', (event) => {
            const tableButton = event.target.closest('button[data-sqlite-table]');
            if (tableButton) previewSqliteTable(tableButton.dataset.sqliteTable);
        });

        document.getElementById('refresh-btn').addEventListener('click', refreshTree);
        document.getElementById('format-btn').addEventListener('click', formatCurrentCode);
        document.getElementById('save-btn').addEventListener('click', saveCurrentFile);
        document.getElementById('delete-btn').addEventListener('click', deleteCurrentFile);
        document.getElementById('preview-refresh-btn').addEventListener('click', renderPreview);
        document.getElementById('run-sql-btn').addEventListener('click', runSqlQuery);

        refreshTree();
        runSqlQuery();
    </script>
</body>
</html>
