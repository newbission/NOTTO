<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOTTO 고유번호 — 이름에 새겨진 운명의 번호</title>
    <meta name="description" content="당신의 이름에만 부여되는 평생 고유번호를 확인하세요. AI가 분석한 운명의 번호입니다.">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../public/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="../public/assets/images/favicon.png">
    <!-- Fonts -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
</head>

<body>

    <!-- Hero -->
    <header class="hero compact">
        <a href="../" style="text-decoration:none;">
            <h1 class="hero__logo">NOTTO</h1>
        </a>
        <p class="hero__tagline" style="display:block; margin-bottom: var(--space-md);">🔮 이름에 새겨진 운명의 번호</p>

        <form class="search-bar" id="fixed-form" autocomplete="off">
            <input type="text" class="search-bar__input" id="fixed-input" placeholder="정확한 이름을 입력하세요..." maxlength="20"
                autofocus>
            <button type="submit" class="search-bar__btn">조회</button>
        </form>
    </header>

    <!-- Result -->
    <div class="fixed-result" id="fixed-result" style="display:none;">
        <div class="fixed-result__card">
            <p class="fixed-result__title">고유번호</p>
            <h2 class="fixed-result__name" id="fixed-name"></h2>
            <div class="fixed-result__numbers" id="fixed-numbers"></div>
            <div class="user-card__reason" id="fixed-reason" style="margin-top: var(--space-md); margin-bottom: var(--space-md); display:none;"></div>
            <p class="fixed-result__note" id="fixed-note">이 번호는 평생 변하지 않습니다 🔒</p>
            <p class="fixed-result__note" id="fixed-date" style="margin-top: var(--space-sm);"></p>
        </div>
    </div>

    <!-- Latest Lists -->
    <div class="results" id="latest-fixed" style="margin-top: var(--space-xl); display: none;">
        <h2 style="text-align: center; margin-bottom: var(--space-lg); font-size: 1.2rem; color: var(--color-text);">최근에 고유번호를 받은 행운의 주인공들</h2>
        <div class="results__grid" id="latest-fixed-grid"></div>
    </div>

    <!-- Message -->
    <div class="results" id="fixed-message" style="display:none;">
        <div class="results__status" id="message-text"></div>
    </div>

    <!-- Footer -->
    <footer class="nav-footer">
        <a href="../" class="nav-link">← 메인으로 돌아가기</a>
        <p style="margin-top: var(--space-sm);">© 2026 NOTTO</p>
    </footer>

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <script>
        (() => {
            'use strict';

            const API_BASE = '../api';
            const form = document.getElementById('fixed-form');
            const input = document.getElementById('fixed-input');
            const resultEl = document.getElementById('fixed-result');
            const nameEl = document.getElementById('fixed-name');
            const numbersEl = document.getElementById('fixed-numbers');
            const noteEl = document.getElementById('fixed-note');
            const dateEl = document.getElementById('fixed-date');
            const messageEl = document.getElementById('fixed-message');
            const messageText = document.getElementById('message-text');
            const toast = document.getElementById('toast');

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const name = input.value.trim();
                if (!name) return;

                resultEl.style.display = 'none';
                messageEl.style.display = 'none';

                try {
                    const response = await fetch(`${API_BASE}/fixed.php?name=${encodeURIComponent(name)}`);
                    const json = await response.json();

                    if (!json.success) {
                        messageText.textContent = json.error?.message || '조회할 수 없습니다.';
                        messageEl.style.display = 'block';
                        return;
                    }

                    const data = json.data;

                    if (data.status === 'pending') {
                        messageText.textContent = '🕐 고유번호 생성 대기중입니다. 잠시 후 다시 확인해주세요.';
                        messageEl.style.display = 'block';
                        return;
                    }

                    if (!data.fixed_numbers) {
                        messageText.textContent = '고유번호가 아직 생성되지 않았습니다.';
                        messageEl.style.display = 'block';
                        return;
                    }

                    // 결과 표시
                    nameEl.textContent = data.name;

                    numbersEl.innerHTML = data.fixed_numbers.map(n =>
                        `<span class="ball ball--large ball--fixed">${n}</span>`
                    ).join('');

                    if (data.fixed_reason) {
                        const reasonEl = document.getElementById('fixed-reason');
                        reasonEl.innerHTML = `"${escapeHtml(data.fixed_reason)}"`;
                        reasonEl.style.display = 'block';
                    } else {
                        document.getElementById('fixed-reason').style.display = 'none';
                    }

                    if (data.created_at) {
                        const date = new Date(data.created_at);
                        dateEl.textContent = `등록일: ${date.toLocaleDateString('ko-KR')}`;
                    }

                    resultEl.style.display = 'block';

                } catch (err) {
                    showToast('서버와 연결할 수 없습니다.', 'error');
                    console.error(err);
                }
            });

            // URL에 name 파라미터가 있으면 자동 조회
            const urlParams = new URLSearchParams(window.location.search);
            const nameParam = urlParams.get('name');
            if (nameParam) {
                input.value = nameParam;
                form.dispatchEvent(new Event('submit'));
            } else {
                // 파라미터가 없으면 최신 고유번호 목록을 불러옴
                loadLatestFixed();
            }

            async function loadLatestFixed() {
                try {
                    const response = await fetch(`${API_BASE}/latest_fixed.php`);
                    const json = await response.json();
                    
                    if (json.data && json.data.length > 0) {
                        const grid = document.getElementById('latest-fixed-grid');
                        const container = document.getElementById('latest-fixed');
                        
                        grid.innerHTML = json.data.map(user => {
                            const numbersHTML = user.fixed_numbers ? user.fixed_numbers.map(n => `<span class="ball ball--small ball--fixed">${n}</span>`).join('') : '';
                            const reasonHTML = user.fixed_reason ? `<div class="user-card__reason" style="margin-top: var(--space-sm); font-size: 0.85rem;">"${escapeHtml(user.fixed_reason)}"</div>` : '';
                            
                            return `
                            <div class="user-card" style="cursor: pointer;" onclick="document.getElementById('fixed-input').value='${user.name}'; document.getElementById('fixed-form').dispatchEvent(new Event('submit'));">
                                <div class="user-card__header">
                                    <span class="user-card__name">${escapeHtml(user.name)}</span>
                                </div>
                                <div class="user-card__numbers" style="margin-top: var(--space-sm);">
                                    ${numbersHTML}
                                </div>
                                ${reasonHTML}
                            </div>
                            `;
                        }).join('');
                        container.style.display = 'block';
                    }
                } catch (err) {
                    console.error('최신 고유번호 로딩 실패:', err);
                }
            }

            function showToast(message, type = 'info') {
                toast.textContent = message;
                toast.className = `toast show toast--${type}`;
                setTimeout(() => toast.classList.remove('show'), 3000);
            }

            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }
        })();
    </script>
</body>

</html>