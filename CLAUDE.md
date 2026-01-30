# NOTTO 프로젝트 컨텍스트

## 프로젝트 개요
이름을 기반으로 매주 로또번호를 추천해주는 사이트. 이름의 해시값으로 번호를 생성하여 같은 이름은 같은 회차에 항상 같은 번호를 받음.

## 완료된 작업

### 프론트엔드
- **메인 페이지** (`index.html`, `scripts/app.js`)
  - 회차 정보 표시 (로컬 계산: 2026-01-25 = 1209회차 기준, 매주 일요일 증가)
  - 이름별 추천번호 6개 리스트 (볼 색상: 1-10 노랑, 11-20 파랑, 21-30 빨강, 31-40 회색, 41-45 초록)
  - 이름 검색 (한글만 입력 가능, 완성형 한글만 등록 가능)
  - 상태별 표시: 등록(번호), 반려(사유), 대기중, 미등록(등록 신청 버튼)
  - 모바일 사이즈 고정 (max-width: 480px, min-width: 400px)

- **어드민 페이지** (`admin.html`, `scripts/admin.js`)
  - 비밀번호 인증 (localStorage 저장)
  - 요청 추가: 직접 이름 입력하여 대기열에 추가
  - 등록 요청 대기: 승인/반려, 전체선택, 삭제
  - 이름 관리: 등록 ↔ 반려 이동, 정렬(오름차순/내림차순/원본), 전체선택, 삭제
  - 변경사항 추적 후 일괄 저장 (API 미구현)
  - UI: panel-wrapper로 헤더+리스트 통합, 컬럼 정렬 일치

### Git 브랜치 구조
- **main**: 프론트엔드 코드
- **data** (orphan): 데이터 저장소
  - `names/registered.json` - 등록된 이름 배열
  - `names/rejected.json` - 반려된 이름 객체 {이름: 사유}
  - `episodes/{회차}.json` - 회차별 번호 {이름: [6개 번호]}
- **requests** (orphan): 등록 요청 대기열
  - `regist/{이름}_{timestamp}.json`

### 기술 결정사항
- HTMX 제거 (JSON API 방식이라 불필요)
- PicoCSS 사용 (CDN)
- GitHub Raw URL로 데이터 fetch
- GitHub API로 requests 브랜치 파일 목록 조회

## 미구현 (다음 작업)

### 1. 백엔드 API (Vercel/Netlify Functions)
- `POST /api/request` - 등록 요청 (requests 브랜치에 파일 생성)
- `POST /api/admin/save` - 어드민 변경사항 저장
  - 비밀번호 검증
  - data 브랜치 업데이트 (registered.json, rejected.json)
  - requests 브랜치 파일 삭제 (승인/반려된 요청)

### 2. GitHub Actions (주간 배치)
- 매주 토요일 자동 실행
- 다음 회차 번호 생성 (`episodes/{회차}.json`)
- 해시 기반 번호 생성 알고리즘:
  - 입력: 이름 + 회차번호
  - SHA256 등으로 해시 생성
  - 해시에서 1-45 범위의 중복 없는 6개 숫자 추출
  - 오름차순 정렬

### 3. 프론트엔드 연동
- 메인 페이지: 등록 신청 버튼 → API 호출
- 어드민 페이지: 저장 버튼 → API 호출

## 파일 구조
```
notto/
├── index.html          # 메인 페이지
├── admin.html          # 어드민 페이지
├── scripts/
│   ├── app.js          # 메인 로직
│   └── admin.js        # 어드민 로직
├── README.md
└── CLAUDE.md           # 이 파일
```

## GitHub 정보
- 저장소: https://github.com/newbission/NOTTO
- 사용자: newbission / newbission@gmail.com
