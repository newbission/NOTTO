<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOTTO 관리자 도구</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0f1117; color: #e4e6eb; padding: 24px; max-width: 960px; margin: 0 auto; }

        /* Login Overlay */
        .login-overlay { position: fixed; inset: 0; background: #0f1117; display: flex; align-items: center; justify-content: center; z-index: 9999; }
        .login-overlay.hidden { display: none; }
        .login-box { background: #141a2a; border: 1px solid #2a3448; border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; text-align: center; }
        .login-box h2 { font-size: 1.5rem; margin-bottom: 8px; background: linear-gradient(135deg, #a29bfe, #ffd32a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .login-box p { color: #8b95a5; font-size: 0.9rem; margin-bottom: 24px; }
        .login-box input { width: 100%; padding: 12px 16px; background: #1a2235; border: 1px solid #2a3448; border-radius: 8px; color: #e4e6eb; font-size: 1rem; margin-bottom: 12px; text-align: center; letter-spacing: 2px; }
        .login-box input:focus { border-color: #6c5ce7; outline: none; }
        .login-box .error { color: #e74c3c; font-size: 0.85rem; margin-bottom: 12px; min-height: 20px; }
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

    <!-- Login Overlay -->
    <div class="login-overlay" id="loginOverlay">
        <div class="login-box">
            <h2>🎱 NOTTO Admin</h2>
            <p>관리자 키를 입력하세요</p>
            <input type="password" id="adminKeyInput" placeholder="Admin Key" autocomplete="off">
            <div class="error" id="loginError"></div>
            <button class="btn btn-primary" style="width:100%;" onclick="submitAdminKey()">입력</button>
        </div>
    </div>

    <h1>🎱 NOTTO Admin</h1>
    <p class="subtitle">관리자 도구</p>

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

    <!-- Redraw -->
    <div class="card">
        <h2>🔄 고유번호 다시 뽑기</h2>
        <p style="font-size:0.85rem; color:#8b95a5; margin-bottom:12px;">현재 회차를 유지하고 선택한 이름의 고유번호만 재생성합니다. 기존 번호는 히스토리에 자동 보존됩니다.</p>
        <div class="flex-row" style="margin-bottom:12px;">
            <button class="btn btn-secondary btn-sm" onclick="loadRedrawNames()">이름 목록 로드</button>
            <button class="btn btn-secondary btn-sm" onclick="toggleAllRedraw(true)">전체 선택</button>
            <button class="btn btn-secondary btn-sm" onclick="toggleAllRedraw(false)">전체 해제</button>
        </div>
        <div id="redraw-name-list" style="max-height:240px; overflow-y:auto; background:#1a2235; border-radius:8px; padding:8px; margin-bottom:12px; display:none;"></div>
        <button class="btn btn-primary" id="btn-redraw-exec" onclick="execRedraw()" disabled>선택한 이름 다시 뽑기</button>
    </div>

    <hr>

    <!-- Redraw Weekly -->
    <div class="card">
        <h2>🔁 주간번호 다시 뽑기</h2>
        <p style="font-size:0.85rem; color:#8b95a5; margin-bottom:12px;">이번 회차 주간번호를 재생성합니다. 기존 번호는 덮어씌워집니다. (이유 없는 이름, 번호 교체 요청 등)</p>
        <div class="flex-row" style="margin-bottom:12px;">
            <button class="btn btn-secondary btn-sm" onclick="loadRedrawWeeklyNames()">이름 목록 로드</button>
            <button class="btn btn-secondary btn-sm" onclick="toggleAllRedrawWeekly(true)">전체 선택</button>
            <button class="btn btn-secondary btn-sm" onclick="toggleAllRedrawWeekly(false)">전체 해제</button>
            <span id="redraw-weekly-round" style="font-size:0.8rem; color:#5a6477;"></span>
        </div>
        <div id="redraw-weekly-list" style="max-height:240px; overflow-y:auto; background:#1a2235; border-radius:8px; padding:8px; margin-bottom:12px; display:none;"></div>
        <button class="btn btn-primary" id="btn-redraw-weekly-exec" onclick="execRedrawWeekly()" disabled>선택한 이름 다시 뽑기</button>
    </div>

    <hr>

    <!-- Fill Missing Weekly -->
    <div class="card">
        <h2>🗓 현재 회차 누락 보충</h2>
        <p style="font-size:0.85rem; color:#8b95a5; margin-bottom:12px;">이번 회차 주간번호가 없는 active 이름에 번호를 생성합니다. (대기열 오류 등으로 누락된 경우)</p>
        <div class="flex-row" style="margin-bottom:12px;">
            <button class="btn btn-secondary btn-sm" onclick="checkMissingWeekly()">누락 확인</button>
            <span id="fill-weekly-status" style="font-size:0.85rem; color:#8b95a5;"></span>
        </div>
        <button class="btn btn-primary" id="btn-fill-weekly" onclick="execFillWeekly()" disabled>누락 번호 생성</button>
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
            <div style="display:flex; gap:8px; margin-top:12px;">
                <button class="btn btn-secondary btn-sm" onclick="loadPrompts()">🔄 새로고침</button>
                <button class="btn btn-secondary btn-sm" style="border-color:#e67e22; color:#e67e22;" onclick="resetPrompts()">🔤 기본 프롬프트 복원 (한글깨짐 수정)</button>
            </div>
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

    <!-- DB Migration -->
    <div class="card">
        <h2>🔧 DB 마이그레이션</h2>
        <p style="font-size:0.85rem; color:#8b95a5; margin-bottom:12px;">배포 후 새 스키마 적용. 이미 적용된 버전은 건너뜁니다.</p>
        <div class="flex-row" style="margin-bottom:12px;">
            <button class="btn btn-secondary btn-sm" onclick="checkMigrations()">상태 확인</button>
            <span id="migrate-status" style="font-size:0.85rem; color:#8b95a5;"></span>
        </div>
        <button class="btn btn-primary" id="btn-migrate" onclick="runMigrations()" disabled>마이그레이션 실행</button>
    </div>

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
        const SESSION_TOKEN_KEY = 'notto_admin_session';

        let _sessionToken = null;

        function getToken() { return _sessionToken; }

        function showLogin(errorMsg = '') {
            _sessionToken = null;
            localStorage.removeItem(SESSION_TOKEN_KEY);
            document.getElementById('loginOverlay').classList.remove('hidden');
            document.getElementById('loginError').textContent = errorMsg;
            document.getElementById('adminKeyInput').value = '';
            document.getElementById('adminKeyInput').focus();
        }

        function hideLogin() {
            document.getElementById('loginOverlay').classList.add('hidden');
        }

        async function submitAdminKey() {
            const key = document.getElementById('adminKeyInput').value.trim();
            if (!key) return;
            document.getElementById('loginError').textContent = '';

            try {
                const fd = new URLSearchParams();
                fd.append('key', key);
                const resp = await fetch(`${API_BASE}/auth.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString(),
                });
                const json = await resp.json();
                if (json.success && json.token) {
                    _sessionToken = json.token;
                    localStorage.setItem(SESSION_TOKEN_KEY, json.token);
                    hideLogin();
                } else {
                    document.getElementById('loginError').textContent = '키가 올바르지 않습니다.';
                }
            } catch (e) {
                document.getElementById('loginError').textContent = '서버 연결 실패: ' + e.message;
            }
        }

        document.getElementById('adminKeyInput').addEventListener('keydown', e => {
            if (e.key === 'Enter') submitAdminKey();
        });

        // 페이지 로드 시 저장된 세션 토큰 검증
        (async function initAuth() {
            const saved = localStorage.getItem(SESSION_TOKEN_KEY);
            if (!saved) { showLogin(); return; }

            try {
                const resp = await fetch(`${API_BASE}/auth.php?token=${encodeURIComponent(saved)}`);
                const json = await resp.json();
                if (json.valid) {
                    _sessionToken = saved;
                    hideLogin();
                } else {
                    showLogin();
                }
            } catch (e) {
                showLogin();
            }
        })();

        function showResult(data) {
            document.getElementById('resultOutput').textContent = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
        }

        // ─── API Calls ───

        async function runApi(url, method = 'POST', bodyData = null) {
            const token = getToken();
            if (!token) { showLogin('세션이 만료되었습니다.'); return null; }
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
                if (response.status === 401) { showLogin('세션이 만료되었습니다. 다시 로그인해주세요.'); return null; }
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

        // ─── Migration ───

        async function checkMigrations() {
            const token = getToken();
            if (!token) { showLogin('세션이 만료되었습니다.'); return; }
            const statusEl = document.getElementById('migrate-status');
            statusEl.textContent = '확인 중...';
            document.getElementById('btn-migrate').disabled = true;
            try {
                const resp = await fetch(`${API_BASE}/migrate.php?token=${encodeURIComponent(token)}&status=1`);
                if (resp.status === 401) { showLogin('세션이 만료되었습니다.'); return; }
                const json = await resp.json();
                showResult(json);
                const pending = json.data?.pending ?? [];
                if (pending.length === 0) {
                    statusEl.textContent = '최신 상태 ✅';
                } else {
                    statusEl.textContent = `미적용 ${pending.length}개: ${pending.join(', ')}`;
                    document.getElementById('btn-migrate').disabled = false;
                }
            } catch (e) {
                statusEl.textContent = '확인 실패: ' + e.message;
            }
        }

        async function runMigrations() {
            if (!confirm('미적용 마이그레이션을 실행하시겠습니까?')) return;
            const token = getToken();
            if (!token) { showLogin('세션이 만료되었습니다.'); return; }
            showResult('마이그레이션 실행 중...');
            document.getElementById('btn-migrate').disabled = true;
            try {
                const fd = new URLSearchParams();
                fd.append('token', token);
                const resp = await fetch(`${API_BASE}/migrate.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString(),
                });
                if (resp.status === 401) { showLogin('세션이 만료되었습니다.'); return; }
                const json = await resp.json();
                showResult(json);
                if (json.success) {
                    document.getElementById('migrate-status').textContent = '적용 완료 ✅';
                }
            } catch (e) {
                showResult('마이그레이션 실패: ' + e.message);
            }
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

        async function resetPrompts() {
            if (!confirm('기존 프롬프트를 모두 삭제하고 기본 한글 프롬프트로 복원합니다.\n한글이 깨진 경우에만 사용하세요. 계속하시겠습니까?')) return;

            const token = getToken();
            if (!token) return;

            const result = await runApi(`${API_BASE}/reset-prompts.php`, 'POST', { token });
            if (result && result.success) {
                showResult(`✅ 프롬프트 복원 완료 (weekly: #${result.weekly_id}, fixed: #${result.fixed_id})`);
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

        // ─── Redraw ───

        let redrawNames = [];

        async function loadRedrawNames() {
            const token = getToken();
            if (!token) return;
            try {
                const resp = await fetch(`${API_BASE}/redraw.php?token=${encodeURIComponent(token)}`);
                const json = await resp.json();
                if (!json.success) { showResult(json); return; }
                redrawNames = json.data;
                renderRedrawList();
            } catch (e) {
                showResult('이름 목록 로드 실패: ' + e.message);
            }
        }

        function renderRedrawList() {
            const container = document.getElementById('redraw-name-list');
            if (redrawNames.length === 0) {
                container.innerHTML = '<p style="color:#5a6477;padding:4px;">active 이름이 없습니다.</p>';
                container.style.display = 'block';
                document.getElementById('btn-redraw-exec').disabled = true;
                return;
            }
            container.innerHTML = redrawNames.map(n => `
                <label style="display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;border-bottom:1px solid #2a344820;">
                    <input type="checkbox" class="redraw-chk" value="${n.id}" checked>
                    <span style="font-size:0.9rem;">${escapeHtml(n.name)}</span>
                    <span style="font-size:0.75rem;color:#5a6477;">${n.has_fixed ? '고유번호 있음' : '미생성'}</span>
                </label>
            `).join('');
            container.style.display = 'block';
            document.getElementById('btn-redraw-exec').disabled = false;
            container.querySelectorAll('.redraw-chk').forEach(chk => chk.addEventListener('change', updateRedrawBtn));
        }

        function toggleAllRedraw(checked) {
            document.querySelectorAll('.redraw-chk').forEach(c => { c.checked = checked; });
            updateRedrawBtn();
        }

        function updateRedrawBtn() {
            const any = [...document.querySelectorAll('.redraw-chk')].some(c => c.checked);
            document.getElementById('btn-redraw-exec').disabled = !any;
        }

        async function execRedraw() {
            const checked = [...document.querySelectorAll('.redraw-chk:checked')].map(c => parseInt(c.value));
            if (checked.length === 0) { alert('다시 뽑을 이름을 선택해주세요.'); return; }
            const isAll = checked.length === redrawNames.length;
            const msg = isAll
                ? `전체 ${checked.length}명의 고유번호를 다시 뽑겠습니까?\n기존 번호는 히스토리에 보존됩니다.`
                : `선택한 ${checked.length}명의 고유번호를 다시 뽑겠습니까?\n기존 번호는 히스토리에 보존됩니다.`;
            if (!confirm(msg)) return;

            const token = getToken();
            if (!token) return;
            showResult('다시 뽑는 중... (Gemini API 호출 중, 잠시 기다려주세요)');

            try {
                const fd = new URLSearchParams();
                fd.append('token', token);
                checked.forEach(id => fd.append('name_ids[]', id));

                const resp = await fetch(`${API_BASE}/redraw.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString(),
                });
                const json = await resp.json();
                showResult(json);
            } catch (e) {
                showResult('다시 뽑기 실패: ' + e.message);
            }
        }

        // ─── Redraw Weekly ───

        let redrawWeeklyNames = [];

        async function loadRedrawWeeklyNames() {
            const token = getToken();
            if (!token) return;
            try {
                const resp = await fetch(`${API_BASE}/redraw-weekly.php?token=${encodeURIComponent(token)}`);
                const json = await resp.json();
                if (!json.success) { showResult(json); return; }
                redrawWeeklyNames = json.data;
                document.getElementById('redraw-weekly-round').textContent = `제 ${json.round_number}회 (${json.draw_date})`;
                renderRedrawWeeklyList();
            } catch (e) {
                showResult('이름 목록 로드 실패: ' + e.message);
            }
        }

        function renderRedrawWeeklyList() {
            const container = document.getElementById('redraw-weekly-list');
            if (redrawWeeklyNames.length === 0) {
                container.innerHTML = '<p style="color:#5a6477;padding:4px;">active 이름이 없습니다.</p>';
                container.style.display = 'block';
                document.getElementById('btn-redraw-weekly-exec').disabled = true;
                return;
            }
            container.innerHTML = redrawWeeklyNames.map(n => `
                <label style="display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;border-bottom:1px solid #2a344820;">
                    <input type="checkbox" class="redraw-weekly-chk" value="${n.id}">
                    <span style="font-size:0.9rem;">${escapeHtml(n.name)}</span>
                    <span style="font-size:0.75rem;color:#5a6477;">${n.has_weekly ? (n.weekly_numbers ? n.weekly_numbers.join(', ') : '번호있음') : '❌ 미생성'}</span>
                    ${!n.weekly_reason ? '<span style="font-size:0.7rem;color:#e67e22;">이유없음</span>' : ''}
                </label>
            `).join('');
            container.style.display = 'block';
            document.getElementById('btn-redraw-weekly-exec').disabled = true;
            container.querySelectorAll('.redraw-weekly-chk').forEach(chk => chk.addEventListener('change', updateRedrawWeeklyBtn));
        }

        function toggleAllRedrawWeekly(checked) {
            document.querySelectorAll('.redraw-weekly-chk').forEach(c => { c.checked = checked; });
            updateRedrawWeeklyBtn();
        }

        function updateRedrawWeeklyBtn() {
            const any = [...document.querySelectorAll('.redraw-weekly-chk')].some(c => c.checked);
            document.getElementById('btn-redraw-weekly-exec').disabled = !any;
        }

        async function execRedrawWeekly() {
            const checked = [...document.querySelectorAll('.redraw-weekly-chk:checked')].map(c => parseInt(c.value));
            if (checked.length === 0) { alert('다시 뽑을 이름을 선택해주세요.'); return; }
            const roundText = document.getElementById('redraw-weekly-round').textContent;
            if (!confirm(`${roundText}\n선택한 ${checked.length}명의 주간번호를 다시 뽑겠습니까?\n기존 번호는 덮어씌워집니다.`)) return;

            const token = getToken();
            if (!token) return;
            showResult('다시 뽑는 중... (Gemini API 호출 중, 잠시 기다려주세요)');
            document.getElementById('btn-redraw-weekly-exec').disabled = true;

            try {
                const fd = new URLSearchParams();
                fd.append('token', token);
                checked.forEach(id => fd.append('name_ids[]', id));

                const resp = await fetch(`${API_BASE}/redraw-weekly.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString(),
                });
                const json = await resp.json();
                showResult(json);
                if (json.success) loadRedrawWeeklyNames(); // 결과 반영해서 재로드
            } catch (e) {
                showResult('다시 뽑기 실패: ' + e.message);
                document.getElementById('btn-redraw-weekly-exec').disabled = false;
            }
        }

        // ─── Fill Missing Weekly ───

        async function checkMissingWeekly() {
            const token = getToken();
            if (!token) return;
            const statusEl = document.getElementById('fill-weekly-status');
            statusEl.textContent = '확인 중...';
            document.getElementById('btn-fill-weekly').disabled = true;
            try {
                const resp = await fetch(`${API_BASE}/fill-weekly.php?token=${encodeURIComponent(token)}`);
                const json = await resp.json();
                if (!json.success) { showResult(json); statusEl.textContent = '오류 발생'; return; }
                const { round_number, missing_count, missing_names } = json;
                if (missing_count === 0) {
                    statusEl.textContent = `제 ${round_number}회 — 누락 없음 ✅`;
                    document.getElementById('btn-fill-weekly').disabled = true;
                } else {
                    statusEl.textContent = `제 ${round_number}회 — ${missing_count}명 누락: ${missing_names.join(', ')}`;
                    document.getElementById('btn-fill-weekly').disabled = false;
                }
            } catch (e) {
                showResult('확인 실패: ' + e.message);
                statusEl.textContent = '오류 발생';
            }
        }

        async function execFillWeekly() {
            const statusEl = document.getElementById('fill-weekly-status');
            const countText = statusEl.textContent;
            if (!confirm(`누락된 주간번호를 생성하시겠습니까?\n${countText}`)) return;

            const token = getToken();
            if (!token) return;
            showResult('누락 주간번호 생성 중... (Gemini API 호출 중, 잠시 기다려주세요)');
            document.getElementById('btn-fill-weekly').disabled = true;

            try {
                const fd = new URLSearchParams();
                fd.append('token', token);
                const resp = await fetch(`${API_BASE}/fill-weekly.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString(),
                });
                const json = await resp.json();
                showResult(json);
                if (json.success) {
                    statusEl.textContent = `완료: ${json.generated}명 생성, ${json.failed}명 실패`;
                }
            } catch (e) {
                showResult('생성 실패: ' + e.message);
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