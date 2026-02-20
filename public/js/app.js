/**
 * NOTTO Frontend Application
 *
 * - 검색 (부분 일치)
 * - 인피니티 스크롤 (전체 목록)
 * - 정렬 (4종)
 * - 이름 등록 (AJAX)
 */

(() => {
    'use strict';

    // ─── Config ───
    const API_BASE = './api';
    const PER_PAGE = 20;

    // ─── DOM Elements ───
    const hero = document.getElementById('hero');
    const searchForm = document.getElementById('search-form');
    const searchInput = document.getElementById('search-input');
    const sortControls = document.getElementById('sort-controls');
    const registerPrompt = document.getElementById('register-prompt');
    const resultsGrid = document.getElementById('results-grid');
    const resultsStatus = document.getElementById('results-status');
    const loader = document.getElementById('loader');
    const sentinel = document.getElementById('sentinel');
    const toast = document.getElementById('toast');

    // ─── State ───
    let currentMode = 'browse'; // 'browse' | 'search'
    let currentSort = 'newest';
    let currentQuery = '';
    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    let isHeroCompact = false;
    let searchName = ''; // 현재 검색한 이름 (등록용)

    // ─── Init ───
    function init() {
        setupEventListeners();
        setupInfiniteScroll();
        loadUsers();
    }

    // ─── Event Listeners ───
    function setupEventListeners() {
        searchForm.addEventListener('submit', handleSearch);

        sortControls.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', () => handleSort(btn.dataset.sort));
        });

        // registerBtn is now dynamic, we handle its listener in showRegisterPrompt
    }

    // ─── Search ───
    async function handleSearch(e) {
        e.preventDefault();
        const query = searchInput.value.trim();

        if (query === '') {
            // 빈 검색 = 전체 목록으로 복귀
            currentMode = 'browse';
            currentQuery = '';
            resetAndLoad();
            return;
        }

        currentMode = 'search';
        currentQuery = query;
        searchName = query;
        resetAndLoad();
    }

    // ─── Sort ───
    function handleSort(sort) {
        currentSort = sort;

        sortControls.querySelectorAll('.sort-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.sort === sort);
        });

        resetAndLoad();
    }

    // ─── Reset & Reload ───
    function resetAndLoad() {
        currentPage = 1;
        hasMore = true;
        resultsGrid.innerHTML = '';
        registerPrompt.style.display = 'none';
        compactHero();
        showSortControls();
        loadUsers();
    }

    // ─── Load Users ───
    async function loadUsers() {
        if (isLoading || !hasMore) return;
        isLoading = true;
        showLoader(true);

        try {
            let url;
            if (currentMode === 'search') {
                url = `${API_BASE}/search.php?q=${encodeURIComponent(currentQuery)}&page=${currentPage}&per_page=${PER_PAGE}`;
            } else {
                url = `${API_BASE}/users.php?page=${currentPage}&per_page=${PER_PAGE}&sort=${currentSort}`;
            }

            const response = await fetch(url);
            const json = await response.json();

            if (!json.success) {
                showToast(json.error?.message || '데이터를 불러올 수 없습니다.', 'error');
                return;
            }

            const users = json.data;
            const meta = json.meta;

            if (users.length === 0 && currentPage === 1) {
                if (currentMode === 'search') {
                    showRegisterPrompt(currentQuery);
                } else {
                    resultsStatus.textContent = '아직 등록된 이름이 없습니다.';
                }
                hasMore = false;
                return;
            }

            // 검색 모드에서 특별 처리 (정확히 일치하는 이름)
            if (currentMode === 'search') {
                const exactMatchIndex = users.findIndex(u => u.name === currentQuery);

                if (exactMatchIndex !== -1) {
                    const exactMatch = users.splice(exactMatchIndex, 1)[0];
                    showExactMatchPrompt(exactMatch);
                } else {
                    showRegisterPrompt(currentQuery);
                }
            }

            resultsStatus.textContent = '';
            renderUsers(users, json.meta);

            hasMore = meta.has_more ?? (currentPage * PER_PAGE < meta.total);
            currentPage++;

        } catch (err) {
            showToast('서버와 연결할 수 없습니다.', 'error');
            console.error(err);
        } finally {
            isLoading = false;
            showLoader(false);
        }
    }

    // ─── Render Users ───
    function renderUsers(users) {
        users.forEach(user => {
            const card = createUserCard(user);
            resultsGrid.appendChild(card);
        });
    }

    function createUserCard(user) {
        const card = document.createElement('div');
        const isWaiting = user.status === 'pending' || (user.status === 'active' && !user.weekly_numbers);
        const isRejected = user.status === 'rejected';

        card.className = `user-card ${isWaiting || isRejected ? 'user-card--pending' : ''} ${isRejected ? 'user-card--rejected' : ''}`;

        let badgeClass = 'user-card__badge--pending';
        let badgeText = '대기중';

        if (user.status === 'active') {
            badgeClass = 'user-card__badge--active';
            badgeText = '활성';
        } else if (isRejected) {
            badgeClass = 'user-card__badge--rejected';
            badgeText = '반려';
        }

        let numbersHTML;
        if (isRejected) {
            numbersHTML = `<div class="user-card__numbers">사용할 수 없는 이름입니다.</div>`;
        } else if (isWaiting) {
            numbersHTML = `<div class="user-card__numbers">번호 생성 대기중...</div>`;
        } else {
            const winningNumbers = user.winning_numbers || [];
            numbersHTML = `<div class="user-card__numbers">
                ${user.weekly_numbers.map(n => {
                const isMatched = winningNumbers.includes(n);
                return `<span class="ball ${getBallClass(n)} ${isMatched ? 'ball--matched' : ''}">${n}</span>`;
            }).join('')}
            </div>`;
        }

        const metaHTML = [];
        if (user.round_number) metaHTML.push(`${user.round_number}회차`);
        if (user.matched_count !== null && user.matched_count !== undefined) {
            metaHTML.push(`적중 ${user.matched_count}개`);
        }

        card.innerHTML = `
            <div class="user-card__header">
                <span class="user-card__name">${escapeHtml(user.name)}</span>
                <span class="user-card__badge ${badgeClass}">${badgeText}</span>
            </div>
            ${numbersHTML}
            ${metaHTML.length > 0 ? `<div class="user-card__meta">${metaHTML.join(' · ')}</div>` : ''}
        `;

        return card;
    }

    // ─── Register ───
    async function handleRegister() {
        if (!searchName) return;

        const btn = document.getElementById('register-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = '등록 중...';
        }

        try {
            const formData = new FormData();
            formData.append('name', searchName);

            const response = await fetch(`${API_BASE}/register.php`, {
                method: 'POST',
                body: formData,
            });

            const json = await response.json();

            if (!json.success) {
                showToast(json.error?.message || '등록에 실패했습니다.', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '등록하기';
                }
                return;
            }

            showToast(`"${searchName}" 등록 완료! 곧 번호가 생성됩니다.`, 'success');

            // 등록 완료 시 방금 등록한 카드를 프롬프트 영역에 그대로 띄워주기
            const newCardUser = {
                id: json.data.id,
                name: json.data.name,
                status: 'pending',
                weekly_numbers: null,
                round_number: null,
                matched_count: null,
            };
            showExactMatchPrompt(newCardUser);

        } catch (err) {
            showToast('서버와 연결할 수 없습니다.', 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = '등록하기';
            }
        }
    }

    // ─── Infinite Scroll ───
    function setupInfiniteScroll() {
        const observer = new IntersectionObserver(entries => {
            if (entries[0].isIntersecting && !isLoading && hasMore) {
                loadUsers();
            }
        }, { threshold: 0.1 });

        observer.observe(sentinel);
    }

    // ─── UI Helpers ───
    function compactHero() {
        if (!isHeroCompact) {
            hero.classList.add('compact');
            isHeroCompact = true;
        }
    }

    function showSortControls() {
        sortControls.style.display = 'flex';
    }

    function showLoader(visible) {
        loader.classList.toggle('loader--hidden', !visible);
    }

    function showRegisterPrompt(name) {
        registerPrompt.innerHTML = `
            <p class="register-prompt__text" id="register-text">
                <strong>"${escapeHtml(name)}"</strong>은(는) 아직 등록되지 않은 이름입니다.
            </p>
            <button class="btn-register" id="register-btn">등록하기</button>
        `;

        // 다시 이벤트 리스너 연결 (innerHTML로 덮어썼으므로)
        document.getElementById('register-btn').addEventListener('click', handleRegister);
        registerPrompt.className = 'register-prompt';
        registerPrompt.style.display = 'block';
    }

    function showExactMatchPrompt(user) {
        const isPending = user.status === 'pending';
        const isGenerating = user.status === 'active' && (!user.weekly_numbers || user.weekly_numbers.length === 0);
        const isRejected = user.status === 'rejected';

        let badgeClass = 'user-card__badge--pending';
        let badgeText = '대기중';
        let statusClass = 'status-pending';

        if (user.status === 'active') {
            badgeClass = 'user-card__badge--active';
            badgeText = '활성';
            statusClass = 'status-active';
        } else if (isRejected) {
            badgeClass = 'user-card__badge--rejected';
            badgeText = '반려';
            statusClass = 'status-rejected';
        }

        let numbersHTML;
        if (isRejected) {
            numbersHTML = `
            <div class="user-card__numbers" style="flex-direction: column; gap: var(--space-sm); padding: var(--space-lg) 0;">
                <div style="font-size: 1.1rem;">사용할 수 없는 이름입니다.</div>
                <div style="font-size: 0.85rem; color: var(--color-error); font-weight: normal;">(사유: 추후 제공 예정)</div>
            </div>`;
        } else if (isGenerating) {
            numbersHTML = `
            <div class="user-card__numbers" style="flex-direction: column; gap: var(--space-sm); padding: var(--space-lg) 0;">
                <div style="font-size: 1.1rem;">행운의 번호 발급 대기 중...</div>
                <div style="font-size: 0.85rem; color: var(--color-text-muted); font-weight: normal;">이름 확인이 완료되었습니다. 곧 이번 주 행운의 번호가 생성됩니다!</div>
            </div>`;
        } else if (isPending) {
            numbersHTML = `
            <div class="user-card__numbers" style="flex-direction: column; gap: var(--space-sm); padding: var(--space-lg) 0;">
                <div style="font-size: 1.1rem;">등록 대기 중...</div>
                <div style="font-size: 0.85rem; color: var(--color-text-muted); font-weight: normal;">조금만 기다려주시면 곧 등록 처리가 완료됩니다!</div>
            </div>`;
        } else {
            const winningNumbers = user.winning_numbers || [];
            numbersHTML = `<div class="user-card__numbers">
                ${user.weekly_numbers.map(n => {
                const isMatched = winningNumbers.includes(n);
                return `<span class="ball ball--large ${getBallClass(n)} ${isMatched ? 'ball--matched' : ''}">${n}</span>`;
            }).join('')}
            </div>`;
        }

        const metaHTML = [];
        if (user.round_number) metaHTML.push(`${user.round_number}회차`);
        if (user.matched_count !== null && user.matched_count !== undefined) {
            metaHTML.push(`적중 ${user.matched_count}개`);
        }

        registerPrompt.innerHTML = `
            <div class="exact-match-container">
                <div class="exact-match-card ${statusClass}">
                    <span class="user-card__badge ${badgeClass}">${badgeText}</span>
                    <div class="exact-match-card__title">${escapeHtml(user.name)}</div>
                    ${numbersHTML}
                    ${metaHTML.length > 0 ? `<div class="user-card__meta">${metaHTML.join(' · ')}</div>` : ''}
                </div>
            </div>
        `;
        registerPrompt.style.display = 'block';
        registerPrompt.className = '';
    }

    function showToast(message, type = 'info') {
        toast.textContent = message;
        toast.className = `toast show toast--${type}`;

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // ─── Utility ───
    function getBallClass(n) {
        if (n <= 10) return 'ball--1-10';
        if (n <= 20) return 'ball--11-20';
        if (n <= 30) return 'ball--21-30';
        if (n <= 40) return 'ball--31-40';
        return 'ball--41-45';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ─── Start ───
    document.addEventListener('DOMContentLoaded', init);
})();
