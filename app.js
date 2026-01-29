// GitHub raw URL 기본 경로
const GITHUB_BASE = "https://raw.githubusercontent.com/newbission/NOTTO/data";

// 데이터 저장소
let episodeData = {};
let registeredNames = [];
let rejectedNames = {};

// 번호 범위에 따른 볼 색상 클래스
function getBallClass(num) {
  if (num <= 10) return "ball-1-10";
  if (num <= 20) return "ball-11-20";
  if (num <= 30) return "ball-21-30";
  if (num <= 40) return "ball-31-40";
  return "ball-41-45";
}

// 로또 번호를 볼 HTML로 변환
function renderBall(num) {
  return `<span class="lotto-ball ${getBallClass(num)}">${num}</span>`;
}

// 회차 계산 (기준: 2026-01-25 일요일 = 1209회차 시작, 매주 일요일 갱신)
function getCurrentEpisode() {
  const BASE_DATE = new Date(2026, 0, 25);
  const BASE_EPISODE = 1209;
  const MS_PER_WEEK = 7 * 24 * 60 * 60 * 1000;

  const now = new Date();
  const weeks = Math.floor((now - BASE_DATE) / MS_PER_WEEK);
  return BASE_EPISODE + weeks;
}

function displayEpisode() {
  const el = document.getElementById("episode-info");
  el.textContent = `제${getCurrentEpisode()}회 추천번호`;
}

// 등록된 이름 렌더링
function renderRegisteredEntry(name, numbers) {
  const balls = numbers.map(renderBall).join("");
  return `
    <div class="name-entry">
      <span class="name-label">${name}</span>
      <div class="lotto-numbers">${balls}</div>
    </div>
  `;
}

// 반려된 이름 렌더링
function renderRejectedEntry(name, reason) {
  const reasonText = reason ? ` (사유: ${reason})` : "";
  return `
    <div class="name-entry rejected">
      <span class="name-label">${name}</span>
      <span class="rejected-msg">반려된 이름입니다${reasonText}</span>
    </div>
  `;
}

// 미등록 이름 렌더링
function renderUnregisteredEntry(name) {
  return `
    <div class="name-entry unregistered">
      <span class="name-label">${name}</span>
      <button class="register-btn" onclick="requestRegister('${name}')">등록 신청</button>
    </div>
  `;
}

// 등록 신청 처리
function requestRegister(name) {
  const btn = document.querySelector(`.register-btn`);
  if (btn) {
    btn.disabled = true;
    btn.textContent = "신청 완료";
    btn.classList.add("submitted");
  }
  // TODO: 실제로 requests 브랜치에 파일 생성하는 로직 (서버 필요)
  alert(`"${name}" 등록 신청이 완료되었습니다.`);
}

// 검색 결과 렌더링
function renderResults(query) {
  const container = document.getElementById("results");
  const trimmedQuery = query.trim();

  // 빈 검색어: 등록된 이름 전체 표시
  if (!trimmedQuery) {
    if (registeredNames.length === 0) {
      container.innerHTML = `<div class="no-results">데이터를 불러오는 중...</div>`;
      return;
    }
    const entries = registeredNames
      .filter((name) => episodeData[name])
      .map((name) => renderRegisteredEntry(name, episodeData[name]));
    container.innerHTML = entries.join("");
    return;
  }

  // 검색어가 있는 경우
  // 1. 등록된 이름 중 매칭
  const matchedRegistered = registeredNames.filter((name) =>
    name.includes(trimmedQuery)
  );

  if (matchedRegistered.length > 0) {
    const entries = matchedRegistered
      .filter((name) => episodeData[name])
      .map((name) => renderRegisteredEntry(name, episodeData[name]));
    container.innerHTML = entries.join("");
    return;
  }

  // 2. 반려된 이름인지 확인 (정확히 일치)
  if (rejectedNames.hasOwnProperty(trimmedQuery)) {
    container.innerHTML = renderRejectedEntry(
      trimmedQuery,
      rejectedNames[trimmedQuery]
    );
    return;
  }

  // 3. 미등록 이름
  container.innerHTML = renderUnregisteredEntry(trimmedQuery);
}

// 데이터 로드
async function loadData() {
  const episode = getCurrentEpisode();

  try {
    const [episodeRes, registeredRes, rejectedRes] = await Promise.all([
      fetch(`${GITHUB_BASE}/episodes/${episode}.json`),
      fetch(`${GITHUB_BASE}/names/registered.json`),
      fetch(`${GITHUB_BASE}/names/rejected.json`),
    ]);

    if (episodeRes.ok) {
      episodeData = await episodeRes.json();
    }
    if (registeredRes.ok) {
      registeredNames = await registeredRes.json();
    }
    if (rejectedRes.ok) {
      rejectedNames = await rejectedRes.json();
    }
  } catch (e) {
    console.error("데이터 로드 실패:", e);
  }

  renderResults("");
}

// 초기화
document.addEventListener("DOMContentLoaded", () => {
  displayEpisode();
  loadData();

  const searchInput = document.getElementById("search-input");
  searchInput.addEventListener("input", (e) => {
    const koreanOnly = e.target.value.replace(
      /[^\uAC00-\uD7A3\u3131-\u3163\u318D-\u318F]/g,
      ""
    );
    if (e.target.value !== koreanOnly) {
      e.target.value = koreanOnly;
    }
    renderResults(koreanOnly);
  });
});
