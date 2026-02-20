<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOTTO — AI가 점지해주는 이번 주 행운의 번호</title>
    <meta name="description" content="이름을 등록하면 매주 AI가 당신만을 위한 행운의 로또 번호를 추천해줍니다. 지금 바로 등록하세요!">
    <meta name="keywords" content="로또, AI, 번호 추천, 행운, NOTTO">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.min.css" rel="stylesheet">
    <link rel="stylesheet" href="public/css/style.css?v=2">
</head>

<body>

    <!-- Hero Section -->
    <header class="hero" id="hero">
        <h1 class="hero__logo">NOTTO</h1>
        <p class="hero__tagline">AI가 점지해주는 이번 주 행운의 번호</p>
        <div class="hero__round-badge" id="round-badge" style="display:none;">
            <span class="round-badge__icon">🎱</span>
            <span class="round-badge__text" id="round-text"></span>
        </div>

        <form class="search-bar" id="search-form" autocomplete="off">
            <input type="text" class="search-bar__input" id="search-input" placeholder="이름을 입력하세요..." maxlength="20"
                autofocus>
            <button type="submit" class="search-bar__btn">검색</button>
        </form>

        <nav style="margin-top: var(--space-md);">
            <a href="fixed/" class="nav-link">🔮 고유번호 조회</a>
        </nav>
    </header>

    <!-- Sort Controls -->
    <div class="sort-controls" id="sort-controls" style="display:none;">
        <button class="sort-btn active" data-sort="newest">최신등록순</button>
        <button class="sort-btn" data-sort="name_asc">이름 ↑</button>
        <button class="sort-btn" data-sort="name_desc">이름 ↓</button>
        <button class="sort-btn" data-sort="oldest">오래된순</button>
    </div>

    <!-- Register Prompt (검색 시 미등록 이름) -->
    <div class="register-prompt" id="register-prompt" style="display:none;">
        <p class="register-prompt__text" id="register-text"></p>
        <button class="btn-register" id="register-btn">등록하기</button>
    </div>

    <!-- Results -->
    <section class="results" id="results">
        <div class="results__status" id="results-status"></div>
        <div class="results__grid" id="results-grid"></div>
    </section>

    <!-- Loading Spinner -->
    <div class="loader loader--hidden" id="loader">
        <div class="loader__spinner"></div>
    </div>

    <!-- Infinite Scroll Sentinel -->
    <div id="sentinel" style="height: 1px;"></div>

    <!-- Toast Notification -->
    <div class="toast" id="toast"></div>

    <!-- Footer -->
    <footer class="nav-footer">
        <p>NOTTO — AI 기반 로또 번호 추천 서비스</p>
        <p>© 2026 NOTTO. 이 서비스는 재미를 위한 것이며, 실제 당첨을 보장하지 않습니다.</p>
    </footer>

    <script src="public/js/app.js?v=2"></script>
</body>

</html>