# NOTTO - 이름별 로또번호 추천

이름을 기반으로 매주 로또번호를 추천해주는 사이트입니다.

## 현재 상태

프론트엔드 구현 완료 - GitHub 데이터 브랜치에서 이름/번호 데이터를 불러옵니다.

## 기능

### 메인 페이지 (`index.html`)
- 현재 회차 정보 표시 (로컬 계산)
- 이름별 추천 로또번호 6개 리스트
- 이름 실시간 검색 필터링 (한글만 입력 가능)
- 미등록 이름 등록 신청

### 어드민 페이지 (`admin.html`)
- 비밀번호 인증
- 등록 요청 관리 (승인/반려)
- 이름 관리 (등록 ↔ 반려 이동)
- 요청 직접 추가/삭제

## 프로젝트 구조

```
notto/
├── index.html          # 메인 페이지
├── admin.html          # 어드민 페이지
├── scripts/
│   ├── app.js          # 메인 페이지 로직
│   └── admin.js        # 어드민 페이지 로직
└── README.md
```

## 데이터 브랜치

- `data`: 이름 목록 및 회차별 번호 데이터
  - `names/registered.json` - 등록된 이름
  - `names/rejected.json` - 반려된 이름
  - `episodes/{회차}.json` - 회차별 추천번호
- `requests`: 등록 요청 대기열
  - `regist/{이름}_{timestamp}.json`

## 기술 스택

- PicoCSS (CDN)
- 순수 JavaScript
- GitHub Raw/API (데이터 저장소)

## 실행

`index.html` 또는 `admin.html`을 브라우저에서 직접 열면 됩니다.
