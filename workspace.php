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
        };

        const fileTree = document.getElementById('file-tree');
        const statusBar = document.getElementById('status-bar');
        const currentPathLabel = document.getElementById('current-path');
        const activeFileLabel = document.getElementById('active-file');
        const previewFrame = document.getElementById('live-preview');

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
                const source = maskCodeCommentsAndStrings(code, ext);
                const openBraces = [];
                let lineNo = 1;
                for (const char of source) {
                    if (char === '{') {
                        openBraces.push(lineNo);
                    } else if (char === '}') {
                        if (openBraces.length) {
                            openBraces.pop();
                        } else {
                            issues.push(lineNo);
                        }
                    }
                    if (char === '\n') lineNo += 1;
                }
                issues.push(...openBraces);
            }

            return [...new Set(issues.filter((line) => line > 0))];
        }

        function maskCodeCommentsAndStrings(code, ext) {
            const chars = [...code];
            let stateName = '';
            let quote = '';
            let escaped = false;

            for (let index = 0; index < chars.length; index += 1) {
                const char = chars[index];
                const next = chars[index + 1];

                if (stateName === 'line-comment') {
                    if (char === '\n') stateName = '';
                    else chars[index] = ' ';
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
                    chars[index] = ' ';
                    stateName = 'string';
                }
            }

            return chars.join('');
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
            updateCurrentPath();
            activeFileLabel.textContent = fileName;
            editor.setOption('mode', detectMode(fileName));

            const ext = getFileExtension(fileName);
            if (ext === 'sqlite' || ext === 'db' || ext === 'db3') {
                try {
                    const response = await fetch(`api/student-db.php?student_id=${studentId}&action=tables`);
                    const data = await response.json();
                    const tables = Array.isArray(data.tables) ? data.tables : [];
                    const html = tables.length ? `<ul>${tables.map((table) => `<li>${escapeHtml(table)}</li>`).join('')}</ul>` : '<p>Belum ada tabel di database ini.</p>';
                    document.getElementById('sql-results').innerHTML = `<div class="sqlite-table-list"><strong>Daftar tabel SQLite</strong>${html}</div>`;
                    editor.setValue(`-- Database: ${escapeHtml(fileName)}\n-- Tabel tersedia:\n${tables.map((table) => `SELECT * FROM ${table};`).join('\n') || '-- belum ada tabel'}`);
                    showStatus(`Database SQLite terhubung: ${tables.length} tabel terdeteksi.`);
                    return;
                } catch (error) {
                    showStatus(error.message, true);
                    return;
                }
            }

            fetchWorkspace('read', { path }).then((data) => {
                editor.setValue(data.content || '');
                renderPreview();
                updateEditorWarnings();
                showStatus(`Membuka file: ${path}`);
            }).catch((error) => {
                editor.setValue('');
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

        async function saveCurrentFile() {
            if (!state.currentFile) {
                showStatus('Pilih file terlebih dahulu.', true);
                return;
            }

            try {
                const payload = { path: state.currentFile, content: editor.getValue() };
                const data = await fetchWorkspaceByPost('save-file', payload);
                renderPreview();
                showStatus(`Disimpan: ${data.path}`);
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        async function createItem(type) {
            const name = window.prompt(type === 'folder' ? 'Nama folder baru:' : 'Nama file baru:');
            if (!name) {
                return;
            }

            const parentPath = state.currentPath || '';
            try {
                const data = await fetchWorkspaceByPost(type === 'folder' ? 'create-folder' : 'create-file', {
                    parent_path: parentPath,
                    name,
                });
                showStatus(`${type === 'folder' ? 'Folder' : 'File'} dibuat: ${data.path}`);
                await refreshTree();
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        async function deleteCurrentFile() {
            if (!state.currentFile) {
                showStatus('Pilih file atau folder yang ingin dihapus.', true);
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

        editor.on('keydown', (cm, event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === ' ') {
                event.preventDefault();
                CodeMirror.showHint(cm, suggestCodeHints, { completeSingle: false, alignWithWord: true });
            }
        });

        editor.on('inputRead', (cm, change) => {
            if (!['html', 'htm'].includes(getFileExtension(state.currentFileName)) || !change.text.includes('>')) return;
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

                    tableContainer.innerHTML = `
                        <table>
                            <thead><tr>${headers.map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead>
                            <tbody>${rowsHtml}</tbody>
                        </table>
                    `;
                } else {
                    const summary = data.type === 'write' ? `Affected rows: ${data.affected_rows ?? 0}` : 'Query berhasil dieksekusi.';
                    tableContainer.innerHTML = `<div style="padding:12px; color:#dfeaf9;">${escapeHtml(summary)}</div>`;
                }

                showStatus('Query SQLite berhasil dijalankan.');
            } catch (error) {
                showStatus(error.message, true);
            }
        }

        document.getElementById('refresh-btn').addEventListener('click', refreshTree);
        document.getElementById('format-btn').addEventListener('click', formatCurrentCode);
        document.getElementById('save-btn').addEventListener('click', saveCurrentFile);
        document.getElementById('delete-btn').addEventListener('click', deleteCurrentFile);
        document.getElementById('new-folder-btn').addEventListener('click', () => createItem('folder'));
        document.getElementById('new-file-btn').addEventListener('click', () => createItem('file'));
        document.getElementById('preview-refresh-btn').addEventListener('click', renderPreview);
        document.getElementById('run-sql-btn').addEventListener('click', runSqlQuery);

        refreshTree();
        runSqlQuery();
    </script>
</body>
</html>
