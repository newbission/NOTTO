<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOTTO 관리자 도구</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f1117; color: #e4e6eb; padding: 24px; max-width: 960px; margin: 0 auto; }
        h1 { font-size: 1.8rem; margin-bottom: 8px; background: linear-gradient(135deg, #a29bfe, #ffd32a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        h2 { font-size: 1.2rem; margin-bottom: 12px; color: #a29bfe; }
        h3 { font-size: 1rem; margin: 16px 0 8px; color: #8b95a5; }

        .subtitle { color: #8b95a5; margin-bottom: 24px; font-size: 0.9rem; }
        hr { border: none; border-top: 1px solid #2a3448; margin: 24px 0; }

        /* Token Input */
        .token-bar { display: flex; gap: 8px; align-items: center; margin-bottom: 24px; padding: 16px; background: #141a2a; border-radius: 12px; border: 1px solid #2a3448; }
        .token-bar label { font-weight: 600; font-size: 0.9rem; white-space: nowrap; }
        .token-bar input { flex: 1; padding: 8px 12px; background: #1a2235; border: 1px solid #2a3448; border-radius: 8px; color: #e4e6eb; font-size: 0.9rem; }

        /* Cards */
        .card { background: #141a2a; border: 1px solid #2a3448; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .card:hover { border-color: #6c5ce7; }

        /* Buttons */
        .btn { padding: 10px 20px; font-size: 0.9rem; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #6c5ce7, #a29bfe); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(108,92,231,0.4); }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-secondary { background: #2a3448; color: #e4e6eb; }
        .btn-secondary:hover { background: #3a4868; }
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Forms */
        textarea { width: 100%; min-height: 120px; padding: 12px; background: #1a2235; border: 1px solid #2a3448; border-radius: 8px; color: #e4e6eb; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; resize: vertical; }
        textarea:focus { border-color: #6c5ce7; outline: none; }
        select { padding: 8px 12px; background: #1a2235; border: 1px solid #2a3448; border-radius: 8px; color: #e4e6eb; font-size: 0.9rem; }

        /* Result output */
        .result-output { background: #1a2235; border: 1px solid #2a3448; border-radius: 8px; padding: 16px; min-height: 80px; white-space: pre-wrap; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #8b95a5; max-height: 300px; overflow-y: auto; }

        /* Prompt history */
        .prompt-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px; background: #1a2235; border-radius: 8px; margin-bottom: 8px; gap: 12px; }
        .prompt-item.active { border: 1px solid #2ecc71; }
        .prompt-info { flex: 1; min-width: 0; }
        .prompt-meta { font-size: 0.75rem; color: #5a6477; margin-bottom: 4px; }
        .prompt-content { font-size: 0.8rem; color: #8b95a5; white-space: pre-wrap; word-break: break-all; max-height: 60px; overflow: hidden; }
        .prompt-actions { display: flex; gap: 6px; flex-shrink: 0; }

        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
        .badge-active { background: rgba(46,204,113,0.15); color: #2ecc71; }
        .badge-inactive { background: rgba(139,149,165,0.15); color: #8b95a5; }
        .badge-weekly { background: rgba(108,92,231,0.15); color: #a29bfe; }
        .badge-fixed { background: rgba(255,211,42,0.15); color: #ffd32a; }

        .flex-row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .section-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 640px) { .section-grid { grid-template-columns: 1fr; } }

        .tab-bar { display: flex; gap: 4px; margin-bottom: 16px; }
        .tab { padding: 8px 16px; border-radius: 8px 8px 0 0; background: #1a2235; color: #8b95a5; cursor: pointer; font-size: 0.85rem; font-weight: 600; border: 1px solid transparent; border-bottom: none; }
        .tab.active { background: #141a2a; color: #a29bfe; border-color: #2a3448; }

        /* DB Browser */
        .table-list { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
        .table-chip { padding: 6px 14px; background: #1a2235; border: 1px solid #2a3448; border-radius: 8px; cursor: pointer; font-size: 0.8rem; color: #8b95a5; transition: all 0.2s; }
        .table-chip:hover { border-color: #6c5ce7; color: #e4e6eb; }
        .table-chip.active { background: #6c5ce7; color: white; border-color: #6c5ce7; }
        .table-chip .chip-count { font-size: 0.65rem; opacity: 0.7; margin-left: 4px; }
        .data-grid { width: 100%; overflow-x: auto; margin-top: 12px; }
        .data-grid table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
        .data-grid th { background: #1a2235; color: #a29bfe; padding: 8px 10px; text-align: left; font-weight: 600; white-space: nowrap; border-bottom: 2px solid #2a3448; }
        .data-grid td { padding: 6px 10px; border-bottom: 1px solid #1e2a3d; color: #c4c9d4; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .data-grid tr:hover td { background: #1a223566; }
        .data-grid td[contenteditable] { cursor: text; }
        .data-grid td[contenteditable]:focus { outline: 2px solid #6c5ce7; background: #1a2235; white-space: normal; }
        .data-grid td.edited { color: #ffd32a; }
        .data-grid .row-actions { white-space: nowrap; }
        .pager { display: flex; gap: 8px; align-items: center; margin-top: 12px; font-size: 0.8rem; color: #8b95a5; }
        .pager button { padding: 4px 12px; }
    </style>
</head>

<body>
    <h1>🎱 NOTTO Admin</h1>
    <p class="subtitle">관리자 도구</p>

    <!-- Token -->
    <div class="token-bar">
        <label>🔑 Admin Token:</label>
        <input type="password" id="adminToken" placeholder="관리자 토큰 입력...">
    </div>

    <!-- Actions -->
    <div class="section-grid">
        <div class="card">
            <h2>📋 대기열 등록</h2>
            <p style="font-size:0.85rem; color:#8b95a5; margin-bottom:12px;">Pending → Active + 고유번호 생성</p>
            <button class="btn btn-primary" onclick="processPending()">대기열 처리</button>
        </div>
        <div class="card">
            <h2>🎰 다음 회차 추첨</h2>
            <p style="font-size:0.85rem; color:#8b95a5; margin-bottom:12px;">새 회차 생성 + 주간번호 일괄 생성</p>
            <button class="btn btn-primary" onclick="drawWeekly()">추첨 실행</button>
        </div>
    </div>

    <hr>

    <!-- Prompt Management -->
    <div class="card">
        <h2>📝 프롬프트 관리</h2>

        <!-- Tab Bar -->
        <div class="tab-bar">
            <div class="tab active" data-tab="current" onclick="switchTab('current')">현재 활성</div>
            <div class="tab" data-tab="create" onclick="switchTab('create')">새로 만들기</div>
            <div class="tab" data-tab="history" onclick="switchTab('history')">히스토리</div>
        </div>

        <!-- Current Active Prompts -->
        <div id="tab-current">
            <div id="active-prompts-container">
                <p style="color:#5a6477;">토큰 입력 후 "현재 활성" 탭을 클릭하면 로드됩니다.</p>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="loadPrompts()" style="margin-top:12px;">🔄 새로고침</button>
        </div>

        <!-- Create New Prompt -->
        <div id="tab-create" style="display:none;">
            <div class="flex-row" style="margin-bottom:12px;">
                <select id="prompt-type">
                    <option value="weekly">weekly (주간번호)</option>
                    <option value="fixed">fixed (고유번호)</option>
                </select>
                <label style="font-size:0.85rem;">
                    <input type="checkbox" id="prompt-activate" checked> 생성 즉시 활성화
                </label>
            </div>
            <textarea id="prompt-content" placeholder="프롬프트 내용을 입력하세요... ({names} 플레이스홀더 사용)"></textarea>
            <button class="btn btn-primary" onclick="createPrompt()" style="margin-top:12px;">프롬프트 생성</button>
        </div>

        <!-- History -->
        <div id="tab-history" style="display:none;">
            <div id="history-container">
                <p style="color:#5a6477;">"히스토리" 탭을 클릭하면 로드됩니다.</p>
            </div>
        </div>
    </div>

    <hr>

    <!-- DB Management -->
    <div class="card" style="border-color:#e74c3c33;">
        <h2>🗄️ DB 관리</h2>
        <div class="flex-row">
            <button class="btn btn-secondary" onclick="backupDB()">📥 백업 다운로드</button>
            <button class="btn btn-danger" onclick="resetDB()">🗑️ 전체 초기화</button>
        </div>
        <p style="font-size:0.75rem; color:#5a6477; margin-top:8px;">초기화: 모든 테이블 삭제 → schema.sql 재실행 → 현재 회차 자동 생성</p>
    </div>

    <hr>

    <!-- DB Browser -->
    <div class="card">
        <h2>🔍 DB 브라우저</h2>
        <div id="db-tables" class="table-list">
            <p style="color:#5a6477;">토큰 입력 후 "테이블 로드" 클릭</p>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="loadTables()">📋 테이블 로드</button>
        <div id="db-grid" class="data-grid"></div>
        <div id="db-pager" class="pager"></div>
    </div>

    <hr>

    <!-- Result Output -->
    <h2>📤 실행 결과</h2>
    <pre class="result-output" id="resultOutput">결과가 여기 표시됩니다...</pre>

    <script>
        const API_BASE = '/api';

        function getToken() {
            const t = document.getElementById('adminToken').value.trim();
            if (!t) { alert('관리자 토큰을 입력해주세요.'); document.getElementById('adminToken').focus(); return null; }
            return t;
        }

        function showResult(data) {
            document.getElementById('resultOutput').textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
        }

        // ─── API Calls ───

        async function runApi(url, method = 'POST', bodyData = null) {
            const token = getToken();
            if (!token) return null;
            showResult('요청 중... 기다려주세요.');

            try {
                const opts = { method, headers: {} };
                if (method === 'POST' && bodyData) {
                    const fd = new URLSearchParams();
                    fd.append('token', token);
                    for (const [k, v] of Object.entries(bodyData)) fd.append(k, v);
                    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    opts.body = fd.toString();
                } else if (method === 'POST') {
                    const fd = new URLSearchParams();
                    fd.append('token', token);
                    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    opts.body = fd.toString();
                } else {
                    url += (url.includes('?') ? '&' : '?') + `token=${encodeURIComponent(token)}`;
                }

                const response = await fetch(url, opts);
                const data = await response.json();
                showResult(data);
                return data;
            } catch (error) {
                showResult('통신 에러: ' + error.message);
                return null;
            }
        }

        function processPending() {
            if (confirm('대기열에 있는 이름들을 등록하시겠습니까?')) runApi(`${API_BASE}/process-pending.php`);
        }

        function drawWeekly() {
            if (confirm('다음 회차 추첨을 실행하시겠습니까?')) runApi(`${API_BASE}/draw.php`);
        }

        // ─── DB Management ───

        function backupDB() {
            const token = getToken();
            if (!token) return;
            // 파일 다운로드 트리거
            window.location.href = `${API_BASE}/db-manage.php?action=backup&token=${encodeURIComponent(token)}`;
            showResult('백업 파일 다운로드 중...');
        }

        function resetDB() {
            const token = getToken();
            if (!token) return;
            if (!confirm('⚠️ 정말로 DB를 초기화하시겠습니까?\n\n모든 데이터가 삭제됩니다!')) return;
            if (!confirm('🚨 마지막 확인: 되돌릴 수 없습니다. 계속하시겠습니까?')) return;
            runApi(`${API_BASE}/db-manage.php`, 'POST', { action: 'reset' });
        }

        // ─── Tabs ───

        function switchTab(tabName) {
            document.querySelectorAll('[data-tab]').forEach(t => t.classList.toggle('active', t.dataset.tab === tabName));
            document.getElementById('tab-current').style.display = tabName === 'current' ? 'block' : 'none';
            document.getElementById('tab-create').style.display = tabName === 'create' ? 'block' : 'none';
            document.getElementById('tab-history').style.display = tabName === 'history' ? 'block' : 'none';

            if (tabName === 'current' || tabName === 'history') loadPrompts();
        }

        // ─── Prompts ───

        let allPrompts = [];

        async function loadPrompts() {
            const token = getToken();
            if (!token) return;

            try {
                const resp = await fetch(`${API_BASE}/prompts.php?token=${encodeURIComponent(token)}&action=list`);
                const json = await resp.json();
                if (!json.success) { showResult(json); return; }
                allPrompts = json.data;
                renderActivePrompts();
                renderHistory();
            } catch (e) {
                showResult('프롬프트 로드 실패: ' + e.message);
            }
        }

        function renderActivePrompts() {
            const container = document.getElementById('active-prompts-container');
            const activeFixed = allPrompts.find(p => p.type === 'fixed' && p.is_active);
            const activeWeekly = allPrompts.find(p => p.type === 'weekly' && p.is_active);

            container.innerHTML = `
                <h3>Weekly (주간번호) 프롬프트</h3>
                ${activeWeekly ? renderPromptCard(activeWeekly) : '<p style="color:#e74c3c;">⚠️ 활성 weekly 프롬프트 없음</p>'}
                <h3>Fixed (고유번호) 프롬프트</h3>
                ${activeFixed ? renderPromptCard(activeFixed) : '<p style="color:#e74c3c;">⚠️ 활성 fixed 프롬프트 없음</p>'}
            `;
        }

        function renderPromptCard(p) {
            return `<div class="prompt-item active">
                <div class="prompt-info">
                    <div class="prompt-meta">
                        <span class="badge badge-${p.type}">${p.type}</span>
                        <span class="badge badge-active">활성</span>
                        ID: ${p.id} · ${p.created_at}
                    </div>
                    <div class="prompt-content">${escapeHtml(p.content)}</div>
                </div>
            </div>`;
        }

        function renderHistory() {
            const container = document.getElementById('history-container');
            if (allPrompts.length === 0) {
                container.innerHTML = '<p style="color:#5a6477;">프롬프트가 없습니다.</p>';
                return;
            }

            container.innerHTML = allPrompts.map(p => `
                <div class="prompt-item ${p.is_active ? 'active' : ''}">
                    <div class="prompt-info">
                        <div class="prompt-meta">
                            <span class="badge badge-${p.type}">${p.type}</span>
                            ${p.is_active ? '<span class="badge badge-active">활성</span>' : '<span class="badge badge-inactive">비활성</span>'}
                            ID: ${p.id} · ${p.created_at}
                        </div>
                        <div class="prompt-content">${escapeHtml(p.content)}</div>
                    </div>
                    <div class="prompt-actions">
                        ${!p.is_active ? `<button class="btn btn-secondary btn-sm" onclick="activatePrompt(${p.id})">활성화</button>` : ''}
                    </div>
                </div>
            `).join('');
        }

        async function createPrompt() {
            const type = document.getElementById('prompt-type').value;
            const content = document.getElementById('prompt-content').value.trim();
            const activate = document.getElementById('prompt-activate').checked;

            if (!content) { alert('프롬프트 내용을 입력해주세요.'); return; }

            const result = await runApi(`${API_BASE}/prompts.php`, 'POST', {
                action: 'create',
                type: type,
                content: content,
                activate: activate ? 'true' : 'false',
            });

            if (result && result.success) {
                alert('프롬프트가 생성되었습니다!');
                document.getElementById('prompt-content').value = '';
                loadPrompts();
            }
        }

        async function activatePrompt(id) {
            if (!confirm(`프롬프트 #${id}을 활성화하시겠습니까? 같은 타입의 기존 활성 프롬프트는 비활성화됩니다.`)) return;

            const token = getToken();
            if (!token) return;

            const result = await runApi(`${API_BASE}/prompts.php?action=activate&id=${id}`, 'GET');
            if (result && result.success) {
                loadPrompts();
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // ─── DB Browser ───

        let dbState = { currentTable: null, pk: null, page: 1 };

        async function loadTables() {
            const token = getToken();
            if (!token) return;
            try {
                const resp = await fetch(`${API_BASE}/db-browse.php?action=tables&token=${encodeURIComponent(token)}`);
                const json = await resp.json();
                if (!json.success) { showResult(json); return; }
                const container = document.getElementById('db-tables');
                container.innerHTML = json.data.map(t =>
                    `<div class="table-chip" onclick="queryTable('${t.name}')">${t.name}<span class="chip-count">(${t.rows})</span></div>`
                ).join('');
            } catch (e) {
                showResult('테이블 로드 실패: ' + e.message);
            }
        }

        async function queryTable(table, page = 1) {
            const token = getToken();
            if (!token) return;
            dbState.currentTable = table;
            dbState.page = page;

            // 칩 활성화
            document.querySelectorAll('.table-chip').forEach(c => c.classList.toggle('active', c.textContent.startsWith(table)));

            try {
                const resp = await fetch(`${API_BASE}/db-browse.php?action=query&table=${table}&page=${page}&limit=30&token=${encodeURIComponent(token)}`);
                const json = await resp.json();
                if (!json.success) { showResult(json); return; }

                const { columns, rows, pk, pagination } = json.data;
                dbState.pk = pk;

                if (rows.length === 0) {
                    document.getElementById('db-grid').innerHTML = '<p style="color:#5a6477;margin-top:12px;">데이터 없음</p>';
                    document.getElementById('db-pager').innerHTML = '';
                    return;
                }

                // 테이블 렌더
                const colNames = columns.map(c => c.name);
                let html = '<table><thead><tr>';
                colNames.forEach(c => { html += `<th>${escapeHtml(c)}</th>`; });
                if (pk) html += '<th></th>';
                html += '</tr></thead><tbody>';

                rows.forEach(row => {
                    const pkVal = pk ? row[pk] : null;
                    html += '<tr>';
                    colNames.forEach(col => {
                        const val = row[col] === null ? '<em style="opacity:0.4">NULL</em>' : escapeHtml(String(row[col]));
                        const isPk = col === pk;
                        if (isPk) {
                            html += `<td>${val}</td>`;
                        } else if (pk) {
                            html += `<td contenteditable="true" data-pk="${escapeHtml(String(pkVal))}" data-field="${col}" data-original="${escapeHtml(String(row[col] ?? ''))}" onblur="handleCellEdit(this)">${val}</td>`;
                        } else {
                            html += `<td>${val}</td>`;
                        }
                    });
                    if (pk) {
                        html += `<td class="row-actions"><button class="btn btn-danger btn-sm" onclick="deleteRow('${escapeHtml(String(pkVal))}')">삭제</button></td>`;
                    }
                    html += '</tr>';
                });
                html += '</tbody></table>';
                document.getElementById('db-grid').innerHTML = html;

                // 페이저
                const { total_pages, total_rows } = pagination;
                let pagerHtml = `<span>${total_rows}건 · ${page}/${total_pages}페이지</span>`;
                if (page > 1) pagerHtml += `<button class="btn btn-secondary btn-sm" onclick="queryTable('${table}',${page-1})">◀ 이전</button>`;
                if (page < total_pages) pagerHtml += `<button class="btn btn-secondary btn-sm" onclick="queryTable('${table}',${page+1})">다음 ▶</button>`;
                document.getElementById('db-pager').innerHTML = pagerHtml;
            } catch (e) {
                showResult('쿼리 실패: ' + e.message);
            }
        }

        async function handleCellEdit(td) {
            const newVal = td.textContent.trim();
            const original = td.dataset.original;
            if (newVal === original) return;

            td.classList.add('edited');
            const token = getToken();
            if (!token) return;

            try {
                const fd = new URLSearchParams();
                fd.append('token', token);
                fd.append('action', 'update');
                fd.append('table', dbState.currentTable);
                fd.append('pk', dbState.pk);
                fd.append('pk_value', td.dataset.pk);
                fd.append('field', td.dataset.field);
                fd.append('value', newVal);

                const resp = await fetch(`${API_BASE}/db-browse.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString(),
                });
                const json = await resp.json();
                showResult(json);
                td.dataset.original = newVal;
            } catch (e) {
                showResult('수정 실패: ' + e.message);
                td.textContent = original;
                td.classList.remove('edited');
            }
        }

        async function deleteRow(pkValue) {
            if (!confirm(`PK=${pkValue} 행을 삭제하시겠습니까?`)) return;
            const result = await runApi(`${API_BASE}/db-browse.php`, 'POST', {
                action: 'delete',
                table: dbState.currentTable,
                pk: dbState.pk,
                pk_value: pkValue,
            });
            if (result && result.success) {
                queryTable(dbState.currentTable, dbState.page);
                loadTables(); // 건수 갱신
            }
        }
    </script>
</body>

</html>