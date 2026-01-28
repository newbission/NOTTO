// 더미 데이터: 이름별 추천 로또번호 (1~45, 중복 없이, 오름차순)
const DUMMY_DATA = [
  { name: "김민수", numbers: [3, 11, 22, 28, 35, 44] },
  { name: "이서연", numbers: [1, 9, 17, 26, 33, 41] },
  { name: "박지훈", numbers: [5, 14, 23, 30, 37, 42] },
  { name: "최유진", numbers: [2, 10, 19, 27, 36, 45] },
  { name: "정하은", numbers: [7, 13, 21, 29, 38, 43] },
  { name: "강도현", numbers: [4, 12, 20, 31, 34, 40] },
  { name: "윤서아", numbers: [6, 15, 24, 32, 39, 44] },
  { name: "장민재", numbers: [8, 16, 25, 28, 36, 41] },
  { name: "한소율", numbers: [1, 11, 18, 27, 33, 45] },
  { name: "오준혁", numbers: [3, 14, 22, 30, 35, 42] },
  { name: "신예린", numbers: [5, 10, 19, 26, 37, 43] },
  { name: "임태우", numbers: [2, 13, 21, 29, 34, 40] },
  { name: "송지우", numbers: [7, 16, 24, 31, 38, 44] },
];

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

// 이름 항목 렌더링
function renderEntry(entry) {
  const balls = entry.numbers.map(renderBall).join("");
  return `
    <div class="name-entry">
      <span class="name-label">${entry.name}</span>
      <div class="lotto-numbers">${balls}</div>
    </div>
  `;
}

// 필터된 결과를 #results에 렌더링
function renderResults(query) {
  const container = document.getElementById("results");
  const filtered = DUMMY_DATA.filter((entry) =>
    entry.name.includes(query.trim())
  );

  if (filtered.length === 0) {
    container.innerHTML = `<div class="no-results">검색 결과가 없습니다.</div>`;
    return;
  }

  container.innerHTML = filtered.map(renderEntry).join("");
}

// 회차 계산 (기준: 2026-01-25 일요일 = 1209회차 시작, 매주 일요일 갱신)
function getCurrentEpisode() {
  const BASE_DATE = new Date(2026, 0, 25); // 2026-01-25 (일요일)
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

// 초기화
document.addEventListener("DOMContentLoaded", () => {
  renderResults("");
  displayEpisode();

  const searchInput = document.getElementById("search-input");
  searchInput.addEventListener("input", (e) => {
    const koreanOnly = e.target.value.replace(/[^\uAC00-\uD7A3\u3131-\u3163\u318D-\u318F]/g, "");
    if (e.target.value !== koreanOnly) {
      e.target.value = koreanOnly;
    }
    renderResults(koreanOnly);
  });
});
