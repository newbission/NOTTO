// GitHub URL
const GITHUB_RAW = "https://raw.githubusercontent.com/newbission/NOTTO/data";
const GITHUB_API = "https://api.github.com/repos/newbission/NOTTO/contents";

// 상태 저장소
let pendingRequests = []; // { name, timestamp, file }
let registeredNames = [];
let rejectedNames = {}; // { name: reason }

// 변경사항 추적
let changes = {
  approve: [], // 승인할 요청들 { name, timestamp }
  reject: [], // 반려할 요청들 { name, reason, timestamp }
  toRegistered: [], // 반려 → 등록
  toRejected: [], // 등록 → 반려 { name, reason }
  addRequest: [], // 요청 추가 { name, timestamp }
  deletePending: [], // 대기 요청 삭제 { name, timestamp }
  deleteRegistered: [], // 등록에서 삭제
  deleteRejected: [], // 반려에서 삭제
};

// 반려 모달용 임시 저장
let pendingRejectNames = [];
let rejectSource = "";

// 회차 계산
function getCurrentEpisode() {
  const BASE_DATE = new Date(2026, 0, 25);
  const BASE_EPISODE = 1209;
  const MS_PER_WEEK = 7 * 24 * 60 * 60 * 1000;
  const now = new Date();
  const weeks = Math.floor((now - BASE_DATE) / MS_PER_WEEK);
  return BASE_EPISODE + weeks;
}

// 완성형 한글 검사
function isCompleteHangul(str) {
  if (!str || str.length === 0) return false;
  return [...str].every((char) => {
    const code = char.charCodeAt(0);
    return code >= 0xac00 && code <= 0xd7a3;
  });
}

// 인증
function authenticate() {
  const password = document.getElementById("password").value;
  if (password) {
    localStorage.setItem("adminAuth", password);
    showAdminContent();
  } else {
    alert("비밀번호를 입력하세요.");
  }
}

function showAdminContent() {
  document.getElementById("auth-section").style.display = "none";
  document.getElementById("admin-content").classList.add("show");
  loadData();
}

// 데이터 로드
async function loadData() {
  try {
    const [registeredRes, rejectedRes] = await Promise.all([
      fetch(`${GITHUB_RAW}/names/registered.json`),
      fetch(`${GITHUB_RAW}/names/rejected.json`),
    ]);

    if (registeredRes.ok) registeredNames = await registeredRes.json();
    if (rejectedRes.ok) rejectedNames = await rejectedRes.json();

    const pendingRes = await fetch(`${GITHUB_API}/regist?ref=requests`);
    if (pendingRes.ok) {
      const files = await pendingRes.json();
      pendingRequests = files
        .filter((f) => f.type === "file")
        .map((f) => {
          const match = f.name.match(/^(.+)_(\d+)\.json$/);
          return {
            name: match ? match[1] : f.name,
            timestamp: match ? match[2] : "",
            file: f.name,
          };
        });
    }
  } catch (e) {
    console.error("데이터 로드 실패:", e);
  }
  renderAll();
}

// 렌더링
function renderAll() {
  renderPending();
  renderRegistered();
  renderRejected();
  updateChangesCount();
  updateMoveButtons();
  setupPanelClickHandlers();
}

// 이동 버튼 상태 업데이트
function updateMoveButtons() {
  const pendSelected = document.querySelectorAll('#pending-panel input[type="checkbox"]:checked:not(:disabled)').length;
  const pendTotal = document.querySelectorAll('#pending-panel input[type="checkbox"]:not(:disabled)').length;
  const regSelected = document.querySelectorAll('#registered-panel input[type="checkbox"]:checked:not(:disabled)').length;
  const rejSelected = document.querySelectorAll('#rejected-panel input[type="checkbox"]:checked:not(:disabled)').length;
  const regTotal = document.querySelectorAll('#registered-panel input[type="checkbox"]:not(:disabled)').length;
  const rejTotal = document.querySelectorAll('#rejected-panel input[type="checkbox"]:not(:disabled)').length;

  document.getElementById("btn-to-rejected").disabled = regSelected === 0;
  document.getElementById("btn-to-registered").disabled = rejSelected === 0;

  // 전체선택 체크박스 상태 업데이트
  const pendSelectAll = document.getElementById("pending-select-all");
  const regSelectAll = document.getElementById("registered-select-all");
  const rejSelectAll = document.getElementById("rejected-select-all");
  if (pendSelectAll) {
    pendSelectAll.checked = pendTotal > 0 && pendSelected === pendTotal;
    pendSelectAll.indeterminate = pendSelected > 0 && pendSelected < pendTotal;
  }
  if (regSelectAll) {
    regSelectAll.checked = regTotal > 0 && regSelected === regTotal;
    regSelectAll.indeterminate = regSelected > 0 && regSelected < regTotal;
  }
  if (rejSelectAll) {
    rejSelectAll.checked = rejTotal > 0 && rejSelected === rejTotal;
    rejSelectAll.indeterminate = rejSelected > 0 && rejSelected < rejTotal;
  }
}

// 전체 선택/해제
function toggleSelectAll(panel) {
  const selectAll = document.getElementById(`${panel}-select-all`);
  const checkboxes = document.querySelectorAll(`#${panel}-panel input[type="checkbox"]:not(:disabled)`);

  checkboxes.forEach(cb => cb.checked = selectAll.checked);
  updateMoveButtons();
}

// 패널 아이템 클릭 핸들러 설정
function setupPanelClickHandlers() {
  document.querySelectorAll(".panel-item").forEach((item) => {
    item.onclick = (e) => {
      const checkbox = item.querySelector('input[type="checkbox"]');
      if (checkbox && !checkbox.disabled) {
        checkbox.checked = !checkbox.checked;
        updateMoveButtons();
      }
    };
  });
}

function renderPending() {
  const panel = document.getElementById("pending-panel");

  // 모든 요청 (기존 + 추가)
  const all = [
    ...pendingRequests.map((r) => ({ ...r, isNew: false })),
    ...changes.addRequest.map((r) => ({ ...r, isNew: true })),
  ];

  // 승인/반려/삭제된 것 표시
  const items = all.map((r) => {
    const isApproved = changes.approve.find((a) => a.name === r.name);
    const isRejected = changes.reject.find((a) => a.name === r.name);
    const isDeleted = changes.deletePending.find((d) => d.name === r.name);
    // 새로 추가한 것 중 삭제된 것은 제외
    const isAddedThenDeleted = r.isNew && changes.addRequest.findIndex((a) => a.name === r.name) === -1;

    return { ...r, isApproved, isRejected, isDeleted, isAddedThenDeleted };
  }).filter((r) => !r.isAddedThenDeleted);

  const activeCount = items.filter((r) => !r.isApproved && !r.isRejected && !r.isDeleted).length;
  document.getElementById("pending-count").textContent = activeCount;

  if (items.length === 0) {
    panel.innerHTML = '<div style="color: var(--pico-muted-color);">대기 중인 요청이 없습니다.</div>';
    return;
  }

  panel.innerHTML = items.map((r) => {
    let cls = "";
    let status = "";
    if (r.isDeleted) { cls = "deleted"; status = "삭제"; }
    else if (r.isApproved) { cls = "changed"; status = "승인"; }
    else if (r.isRejected) { cls = "changed"; status = "반려"; }
    else if (r.isNew) { cls = "changed"; status = "새 요청"; }
    else { status = formatTimestamp(r.timestamp) || "-"; }

    return `
      <div class="panel-item ${cls}">
        <input type="checkbox" data-name="${r.name}" data-timestamp="${r.timestamp}" ${r.isDeleted || r.isApproved || r.isRejected ? "disabled" : ""}>
        <span class="name">${r.name}</span>
        <span class="meta">${status}</span>
      </div>
    `;
  }).join("");
}

// 정렬 토글
function toggleSort(panel) {
  const el = document.getElementById(`${panel}-sort`);
  const panelId = `${panel}-panel`;

  // 선택 상태 저장
  const selected = new Set(
    [...document.querySelectorAll(`#${panelId} input[type="checkbox"]:checked`)]
      .map(cb => cb.dataset.name)
  );

  const current = el.dataset.sort;
  const next = current === "asc" ? "desc" : current === "desc" ? "none" : "asc";
  const arrows = { asc: "↑", desc: "↓", none: "-" };

  el.dataset.sort = next;
  el.textContent = `정렬 ${arrows[next]}`;

  if (panel === "registered") renderRegistered();
  else renderRejected();

  // 선택 상태 복원
  document.querySelectorAll(`#${panelId} input[type="checkbox"]`).forEach(cb => {
    if (selected.has(cb.dataset.name)) cb.checked = true;
  });

  setupPanelClickHandlers();
  updateMoveButtons();
}

function renderRegistered() {
  const panel = document.getElementById("registered-panel");
  const sortOrder = document.getElementById("registered-sort").dataset.sort;

  // 모든 등록 이름 수집 (원본 + 이동 + 승인)
  const allNames = new Set([
    ...registeredNames,
    ...changes.toRegistered,
    ...changes.approve.map((a) => a.name),
  ]);

  // 각 이름의 상태 계산
  const items = [...allNames].map((name) => {
    const isOriginal = registeredNames.includes(name);
    const isFromApprove = changes.approve.find((a) => a.name === name);
    const isFromMove = changes.toRegistered.includes(name);
    const isMovedOut = changes.toRejected.find((r) => r.name === name);
    const isDeleted = changes.deleteRegistered.includes(name);

    return { name, isOriginal, isFromApprove, isFromMove, isMovedOut, isDeleted };
  });

  // 정렬
  if (sortOrder !== "none") {
    items.sort((a, b) => {
      const cmp = a.name.localeCompare(b.name, "ko");
      return sortOrder === "desc" ? -cmp : cmp;
    });
  }

  const activeCount = items.filter((i) => !i.isMovedOut && !i.isDeleted).length;
  document.getElementById("registered-count").textContent = activeCount;

  if (items.length === 0) {
    panel.innerHTML = '<div style="color: var(--pico-muted-color);">등록된 이름이 없습니다.</div>';
    return;
  }

  panel.innerHTML = items
    .filter((i) => !i.isMovedOut || i.isDeleted) // 이동된 것은 숨기되, 삭제된 것은 표시
    .map((i) => {
      let cls = "";
      if (i.isDeleted) cls = "deleted";
      else if (i.isFromApprove || i.isFromMove) cls = "changed";

      return `
        <div class="panel-item ${cls}">
          <input type="checkbox" data-name="${i.name}" ${i.isDeleted ? "disabled" : ""}>
          <span class="name">${i.name}</span>
        </div>
      `;
    }).join("");
}

function renderRejected() {
  const panel = document.getElementById("rejected-panel");
  const sortOrder = document.getElementById("rejected-sort").dataset.sort;

  // 모든 반려 이름 수집
  const allNames = new Set([
    ...Object.keys(rejectedNames),
    ...changes.toRejected.map((r) => r.name),
    ...changes.reject.map((r) => r.name),
  ]);

  // 각 이름의 상태 계산
  const items = [...allNames].map((name) => {
    const isOriginal = rejectedNames.hasOwnProperty(name);
    const originalReason = rejectedNames[name];
    const fromReject = changes.reject.find((r) => r.name === name);
    const fromMove = changes.toRejected.find((r) => r.name === name);
    const isMovedOut = changes.toRegistered.includes(name);
    const isDeleted = changes.deleteRejected.includes(name);

    const reason = fromMove?.reason || fromReject?.reason || originalReason;

    return { name, reason, isOriginal, fromReject, fromMove, isMovedOut, isDeleted };
  });

  // 정렬
  if (sortOrder !== "none") {
    items.sort((a, b) => {
      const cmp = a.name.localeCompare(b.name, "ko");
      return sortOrder === "desc" ? -cmp : cmp;
    });
  }

  const activeCount = items.filter((i) => !i.isMovedOut && !i.isDeleted).length;
  document.getElementById("rejected-count").textContent = activeCount;

  if (items.length === 0) {
    panel.innerHTML = '<div style="color: var(--pico-muted-color);">반려된 이름이 없습니다.</div>';
    return;
  }

  panel.innerHTML = items
    .filter((i) => !i.isMovedOut || i.isDeleted)
    .map((i) => {
      let cls = "";
      if (i.isDeleted) cls = "deleted";
      else if (i.fromReject || i.fromMove) cls = "changed";

      return `
        <div class="panel-item ${cls}">
          <input type="checkbox" data-name="${i.name}" ${i.isDeleted ? "disabled" : ""}>
          <span class="name">${i.name}</span>
          <span class="meta">${i.reason || "-"}</span>
        </div>
      `;
    }).join("");
}

function formatTimestamp(ts) {
  if (!ts || ts.length !== 14) return "";
  return `${ts.slice(4, 6)}/${ts.slice(6, 8)} ${ts.slice(8, 10)}:${ts.slice(10, 12)}`;
}

function updateChangesCount() {
  const count =
    changes.approve.length +
    changes.reject.length +
    changes.toRegistered.length +
    changes.toRejected.length +
    changes.addRequest.length +
    changes.deletePending.length +
    changes.deleteRegistered.length +
    changes.deleteRejected.length;
  document.getElementById("changes-count").textContent = count;
}

// 선택 항목 가져오기
function getSelectedNames(panelId) {
  const panel = document.getElementById(panelId);
  const checkboxes = panel.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)');
  return Array.from(checkboxes).map((cb) => ({
    name: cb.dataset.name,
    timestamp: cb.dataset.timestamp || "",
  }));
}

// 요청 승인
function approveSelected() {
  const selected = getSelectedNames("pending-panel");
  if (selected.length === 0) {
    alert("승인할 요청을 선택하세요.");
    return;
  }

  selected.forEach((s) => {
    if (!changes.approve.find((a) => a.name === s.name)) {
      changes.approve.push(s);
    }
  });
  renderAll();
}

// 반려 (pending 또는 registered에서)
function rejectSelected(source) {
  const panelId = source === "pending" ? "pending-panel" : "registered-panel";
  const selected = getSelectedNames(panelId);

  if (selected.length === 0) {
    alert("반려할 항목을 선택하세요.");
    return;
  }

  pendingRejectNames = selected.map((s) => s.name);
  rejectSource = source;
  document.getElementById("reject-reason").value = "";
  document.getElementById("reject-modal").showModal();
}

function closeRejectModal() {
  document.getElementById("reject-modal").close();
  pendingRejectNames = [];
  rejectSource = "";
}

function confirmReject() {
  const reason = document.getElementById("reject-reason").value.trim() || null;

  pendingRejectNames.forEach((name) => {
    if (rejectSource === "pending") {
      const req = pendingRequests.find((r) => r.name === name) ||
        changes.addRequest.find((r) => r.name === name);
      if (req && !changes.reject.find((r) => r.name === name)) {
        changes.reject.push({ name, reason, timestamp: req.timestamp });
      }
    } else {
      // toRegistered에 있으면 제거 (반려→등록 취소)
      const toRegIdx = changes.toRegistered.indexOf(name);
      if (toRegIdx !== -1) {
        changes.toRegistered.splice(toRegIdx, 1);
      }
      // approve에서 온 것이면 approve 제거하고 reject로
      const approveIdx = changes.approve.findIndex((a) => a.name === name);
      if (approveIdx !== -1) {
        const req = changes.approve[approveIdx];
        changes.approve.splice(approveIdx, 1);
        changes.reject.push({ name, reason, timestamp: req.timestamp });
      }
      // 원본 등록 목록에 있으면 toRejected에 추가
      else if (registeredNames.includes(name) && !changes.toRejected.find((r) => r.name === name)) {
        changes.toRejected.push({ name, reason });
      }
    }
  });

  closeRejectModal();
  renderAll();
}

// 등록 → 반려
function moveToRejected() {
  rejectSelected("registered");
}

// 반려 → 등록
function moveToRegistered() {
  const selected = getSelectedNames("rejected-panel");
  if (selected.length === 0) {
    alert("등록으로 이동할 항목을 선택하세요.");
    return;
  }

  selected.forEach((s) => {
    // toRejected에 있으면 제거 (등록→반려 취소)
    const toRejIdx = changes.toRejected.findIndex((r) => r.name === s.name);
    if (toRejIdx !== -1) {
      changes.toRejected.splice(toRejIdx, 1);
    }
    // reject에서 온 것이면 reject에서 제거하고 approve로
    const rejIdx = changes.reject.findIndex((r) => r.name === s.name);
    if (rejIdx !== -1) {
      const req = changes.reject[rejIdx];
      changes.reject.splice(rejIdx, 1);
      changes.approve.push({ name: s.name, timestamp: req.timestamp });
    }
    // 원본 반려 목록에 있으면 toRegistered에 추가
    else if (rejectedNames.hasOwnProperty(s.name) && !changes.toRegistered.includes(s.name)) {
      changes.toRegistered.push(s.name);
    }
  });
  renderAll();
}

// 요청 추가
function addRequest() {
  const input = document.getElementById("direct-name");
  const name = input.value.trim();

  if (!name) { alert("이름을 입력하세요."); return; }
  if (!isCompleteHangul(name)) { alert("완성된 한글만 등록 가능합니다."); return; }
  if (registeredNames.includes(name)) { alert("이미 등록된 이름입니다."); return; }
  if (rejectedNames.hasOwnProperty(name)) { alert("반려된 이름입니다."); return; }
  if (pendingRequests.find((r) => r.name === name) || changes.addRequest.find((r) => r.name === name)) {
    alert("이미 대기 중인 요청입니다.");
    return;
  }

  const timestamp = new Date().toISOString().replace(/[-:T.Z]/g, "").slice(0, 14);
  changes.addRequest.push({ name, timestamp });
  input.value = "";
  renderAll();
}

// 대기 요청 삭제
function deletePending() {
  const selected = getSelectedNames("pending-panel");
  if (selected.length === 0) {
    alert("삭제할 요청을 선택하세요.");
    return;
  }

  selected.forEach((s) => {
    // 새로 추가한 요청이면 addRequest에서 제거
    const addIdx = changes.addRequest.findIndex((r) => r.name === s.name);
    if (addIdx !== -1) {
      changes.addRequest.splice(addIdx, 1);
    }
    // 기존 요청이면 deletePending에 추가
    else if (pendingRequests.find((r) => r.name === s.name)) {
      if (!changes.deletePending.find((d) => d.name === s.name)) {
        changes.deletePending.push(s);
      }
    }
  });
  renderAll();
}

// 등록/반려 삭제
function deleteSelected(source) {
  const panelId = source === "registered" ? "registered-panel" : "rejected-panel";
  const selected = getSelectedNames(panelId);

  if (selected.length === 0) {
    alert("삭제할 항목을 선택하세요.");
    return;
  }

  selected.forEach((s) => {
    if (source === "registered") {
      // approve에서 온 것이면 approve 제거
      const approveIdx = changes.approve.findIndex((a) => a.name === s.name);
      if (approveIdx !== -1) {
        changes.approve.splice(approveIdx, 1);
      }
      // toRegistered에 있으면 제거 + 원본 반려에서 삭제
      const toRegIdx = changes.toRegistered.indexOf(s.name);
      if (toRegIdx !== -1) {
        changes.toRegistered.splice(toRegIdx, 1);
        if (rejectedNames.hasOwnProperty(s.name) && !changes.deleteRejected.includes(s.name)) {
          changes.deleteRejected.push(s.name);
        }
      }
      // 원본 등록에 있으면 삭제 목록에 추가
      if (registeredNames.includes(s.name) && !changes.deleteRegistered.includes(s.name)) {
        changes.deleteRegistered.push(s.name);
      }
    } else {
      // reject에서 온 것이면 reject 제거
      const rejIdx = changes.reject.findIndex((r) => r.name === s.name);
      if (rejIdx !== -1) {
        changes.reject.splice(rejIdx, 1);
      }
      // toRejected에 있으면 제거 + 원본 등록에서 삭제
      const toRejIdx = changes.toRejected.findIndex((r) => r.name === s.name);
      if (toRejIdx !== -1) {
        changes.toRejected.splice(toRejIdx, 1);
        if (registeredNames.includes(s.name) && !changes.deleteRegistered.includes(s.name)) {
          changes.deleteRegistered.push(s.name);
        }
      }
      // 원본 반려에 있으면 삭제 목록에 추가
      if (rejectedNames.hasOwnProperty(s.name) && !changes.deleteRejected.includes(s.name)) {
        changes.deleteRejected.push(s.name);
      }
    }
  });
  renderAll();
}

// 변경사항 취소
function cancelChanges() {
  if (confirm("모든 변경사항을 취소하시겠습니까?")) {
    changes = {
      approve: [], reject: [], toRegistered: [], toRejected: [],
      addRequest: [], deletePending: [], deleteRegistered: [], deleteRejected: [],
    };
    renderAll();
  }
}

// 저장
// 저장
async function saveChanges() {
  const totalChanges =
    changes.approve.length + changes.reject.length +
    changes.toRegistered.length + changes.toRejected.length +
    changes.addRequest.length + changes.deletePending.length +
    changes.deleteRegistered.length + changes.deleteRejected.length;

  if (totalChanges === 0) {
    alert("변경사항이 없습니다.");
    return;
  }

  const password = localStorage.getItem("adminAuth");
  if (!password) {
    alert("로그인이 필요합니다.");
    location.reload();
    return;
  }

  // UI 로딩 표시
  const saveBtn = document.querySelector(".save-btn") || document.querySelector("button[onclick='saveChanges()']");
  const originalText = saveBtn ? saveBtn.textContent : "저장";
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.textContent = "저장 중...";
  }

  try {
    const res = await fetch("/api/admin", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${password}`
      },
      body: JSON.stringify({ changes }),
    });

    const data = await res.json();

    if (res.ok) {
      alert("변경사항이 성공적으로 저장되었습니다.");
      location.reload(); // 데이터 갱신을 위해 새로고침
    } else {
      throw new Error(data.error || "저장에 실패했습니다.");
    }
  } catch (error) {
    console.error("Save Error:", error);
    alert(`오류 발생: ${error.message}`);
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.textContent = originalText;
    }
  }
}

// 초기화
document.addEventListener("DOMContentLoaded", () => {
  const savedAuth = localStorage.getItem("adminAuth");
  if (savedAuth) showAdminContent();

  document.getElementById("direct-name").addEventListener("input", (e) => {
    const koreanOnly = e.target.value.replace(/[^\uAC00-\uD7A3\u3131-\u3163\u318D-\u318F]/g, "");
    if (e.target.value !== koreanOnly) e.target.value = koreanOnly;
  });
});
