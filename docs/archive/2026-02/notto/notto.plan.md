# NOTTO Planning Document

> **Summary**: NOTTO — AI가 점지해주는 이번 주 행운의 번호
> **Date**: 2026-02-20
> **Updated**: 2026-02-22
> **Status**: Confirmed (MVP 완료, 문서 동기화)
> **Version**: v3.1

---

## 1. Overview

### 1.1 Purpose

NOTTO는 이름을 등록하면 매주 Google Gemini AI가 해당 이름만을 위한 로또 번호(6개, 1~45)를 추천해주는 **공개 웹 서비스**입니다. 사용자는 자신의 AI 예상번호와 실제 당첨번호를 비교하는 재미를 즐길 수 있습니다.

### 1.2 Background — v2.0에서의 개선

| 영역 | v2.0 | v3.0 |
|------|------|------|
| **사이트 플로우** | 단일 관리 페이지 | 검색 중심 메인 + 고유번호 페이지 |
| **번호 생성** | Node.js + GitHub Actions | PHP + 외부 크론 (InfinityFree 호환) |
| **DB 설계** | 단일 users 테이블 + JSON | 회차별 분리 + 프롬프트 관리 테이블 |
| **이름 등록** | 즉시 등록 | 대기열 → 배치 처리 (매 정각) |
| **고유번호** | ❌ 없음 | ✅ 최초 등록 시 AI 생성, 평생 고정 |
| **당첨 비교** | ❌ 없음 | ✅ 실제 당첨번호와 비교 |

### 1.3 기술 스택

| 구분 | 기술 | 비고 |
|------|------|------|
| Language | PHP 8.3 | InfinityFree 제공 |
| Database | MySQL 8.0 / MariaDB 11.4 | FK 미지원 (MyISAM) |
| Frontend | Vanilla HTML / CSS / JS | 프레임워크 없음 |
| AI | Google Gemini API (Free Tier) | gemini-2.5-flash |
| Hosting | InfinityFree | 추후 변경 가능 |
| Dev Env | Docker / Docker Compose | PHP 8.3-apache + MySQL 8.0 + Adminer |
| VCS | Git + GitHub | — |

---

## 2. Scope

### 2.1 MVP (In Scope) — ✅ 전체 완료

- [x] 이름 등록 (1~20자, UTF-8 전체 허용, 시스템 필터 없음)
- [x] 이름 부분 검색 (겹치는 이름 전부 표시)
- [x] 전체 결과 인피니티 스크롤 (정렬 4종)
- [x] 대기열 배치 처리 (매 정각 1시간 간격)
- [x] 고유번호 생성 (이름 최초 등록 시 1회, 평생 고정)
- [x] 매주 번호 생성 (자동 회차 계산, RoundHelper 기반)
- [x] 고유번호 별도 페이지
- [x] 실제 당첨번호 비교
- [x] 프롬프트 DB 관리 (weekly / fixed)
- [x] 관리자 API (번호 생성, 당첨번호 입력, 프롬프트 관리)
- [x] 서버 진단 API (healthcheck.php)
- [x] DB 마이그레이션 시스템 (migrator.php + migrations/)
- [x] 로그 조회 API (logs.php)
- [x] 회차 정보 공개 API (round.php)
- [x] Docker 로컬 개발 환경

### 2.2 아이디어 보관 (Out of Scope)

| 기능 | 설명 |
|------|------|
| 과거 회차 탐색 | 회차별 결과 / 이름별 히스토리 |
| 당첨 이름 보기 | 등수별 당첨자 나열 |
| 반려 이름 시스템 | 시스템 필터 + 관리자 반려 + 재요청 |
| 명예의 전당 | 당첨 이력 배지 |
| 행운 통계 | 적중률, 행운 점수 |
| 이번 주 핫 이름 | 적중 Top N |
| 당첨 시각 효과 | 적중 번호 반짝이는 효과 |
| 결과 공유 링크 | SNS/카톡 공유 |
| 회차 카운트다운 | 다음 추첨까지 타이머 |
| 홈 화면 추가 유도 | 모바일 배너 |

---

## 3. Requirements

### 3.1 Functional Requirements

| ID | 설명 | 우선순위 |
|----|------|:--------:|
| FR-01 | 사용자는 이름(1~20자, UTF-8)을 입력하여 등록 신청할 수 있다 | 🔴 필수 |
| FR-02 | 등록된 이름은 대기열(pending)에 추가되고, 매 정각 배치 처리된다 | 🔴 필수 |
| FR-03 | 배치 처리 시 고유번호(fixed)가 AI로 생성되어 이름에 매칭된다 | 🔴 필수 |
| FR-04 | 사용자는 이름을 검색하여 부분 일치하는 모든 결과를 볼 수 있다 | 🔴 필수 |
| FR-05 | 검색 결과에 이번 회차 예상번호가 표시된다 | 🔴 필수 |
| FR-06 | 메인 페이지 스크롤 시 전체 결과가 인피니티 스크롤로 표시된다 | � 필수 |
| FR-07 | 정렬: 신규 등록순(기본) / 이름 오름차순 / 이름 내림차순 / 최신순 / 오래된순 | 🔴 필수 |
| FR-08 | 관리자는 API로 전체 활성 사용자의 매주 번호를 생성할 수 있다 | � 필수 |
| FR-09 | 관리자는 API로 실제 당첨번호를 입력할 수 있다 | 🔴 필수 |
| FR-10 | 고유번호 페이지에서 이름의 평생 고유번호를 조회할 수 있다 | � 필수 |
| FR-11 | 관리자는 API로 프롬프트를 CRUD하고 활성 프롬프트를 설정할 수 있다 | 🔴 필수 |
| FR-12 | 사용자는 자신의 번호와 실제 당첨번호를 비교할 수 있다 | � 권장 |

### 3.2 Non-Functional Requirements

| 영역 | 요구사항 |
|------|---------|
| 성능 | 페이지 로드 3초 이내, API 응답 2초 이내 |
| 호환성 | Chrome, Safari, Firefox 최신 버전 |
| 접근성 | 시맨틱 HTML, 키보드 네비게이션 |
| 보안 | Prepared Statements, htmlspecialchars(), 관리 API 토큰 보호 |
| SEO | 멀티 페이지 PHP, 적절한 meta 태그 |

---

## 4. 사이트 플로우

### 4.1 페이지 구성

```
NOTTO
├── 🏠 index.php (루트, 메인 + 결과 조회 통합)
│   ├── 히어로: 검색바 중앙 배치 (임팩트)
│   ├── 회차 배지: 현재 회차 정보 표시
│   ├── 검색 시: 아래에 검색 결과 리스트 표시
│   ├── 스크롤 시: 전체 결과 인피니티 스크롤
│   ├── 정렬: 최신등록순 / 이름순(오름·내림) / 오래된순
│   └── 미등록 이름 → "등록하기" 버튼 → pending 등록
│
├── 🔮 fixed/index.php (고유번호 조회)
│   ├── 이름 검색 → 정확히 일치하는 이름의 고유번호 표시
│   └── 고유번호 = 최초 등록 시 AI가 생성한 평생 고정 번호
│
└── 📡 api/ (백엔드 API)
    ├── register.php       POST  — 이름 등록
    ├── check-name.php     GET   — 이름 중복 체크
    ├── search.php         GET   — 이름 부분 검색
    ├── users.php          GET   — 전체 목록 (페이지네이션+정렬)
    ├── fixed.php          GET   — 고유번호 조회
    ├── round.php          GET   — 현재 회차 정보 조회
    ├── draw.php           POST  — 매주 번호 생성 🔒
    ├── process-pending.php POST  — 대기열 처리 🔒
    ├── winning.php        GET   — 당첨번호 입력 🔒
    ├── prompts.php        GET   — 프롬프트 CRUD 🔒
    ├── healthcheck.php    GET   — 서버 진단 🔒
    ├── migrate.php        POST  — DB 마이그레이션 실행 🔒
    └── logs.php           GET   — 로그 조회/관리 🔒
```

### 4.2 사용자 시나리오

**A. 신규 사용자**
1. `index.php` → 검색바에 이름 입력 → "없는 이름입니다"
2. "등록하기" 버튼 → `api/register.php` → pending 상태
3. "등록 대기중" 표시 → 정각 배치 처리 후 active + 고유번호 생성
4. 다음 주 일요일 이후 → 검색 시 매주 번호 표시

**B. 기존 사용자 (번호 확인)**
1. `index.php` → 검색바에 이름 입력 → 부분 일치 결과 표시
2. 이번 회차 예상번호 + 실제 당첨번호 비교

**C. 구경꾼**
1. `index.php` → 스크롤 → 전체 결과 인피니티 스크롤

**D. 고유번호 확인**
1. `fixed.php` → 이름 검색 → 평생 고유번호 표시

### 4.3 관리자 플로우

```
[매 정각] 외부 크론 → POST api/process-pending.php?token=XXX
  → pending 이름 조회 → Gemini API(fixed 프롬프트)로 고유번호 생성 → active으로 변경

[매주 일요일] 외부 크론 → POST api/draw.php?token=XXX
  → RoundHelper로 자동 회차 계산 → 새 회차 생성 → 전체 active 이름 → Gemini API(weekly 프롬프트)로 번호 생성

[로또 추첨 후] 관리자 수동 → GET api/winning.php?token=XXX&round_number=N&numbers=1,2,3,4,5,6&bonus=7
  → 해당 회차 당첨번호 저장 + matched_count 자동 계산

[서버 진단] 관리자 → GET api/healthcheck.php?token=XXX
  → PHP 버전, DB 연결, 확장 모듈, 로그 디렉토리 상태 진단

[DB 마이그레이션] 관리자 → POST api/migrate.php?token=XXX
  → database/migrations/ 폴더의 미적용 SQL 파일 실행

[로그 조회] 관리자 → GET api/logs.php?token=XXX
  → 날짜별 로그 파일 조회/삭제
```

---

## 5. 번호 체계

| 구분 | 고유번호 (fixed) | 매주번호 (weekly) |
|------|:----------------:|:-----------------:|
| 형식 | 1~45 중 6개 | 1~45 중 6개 |
| 생성 시점 | 이름 최초 등록 시 1회 | 매주 일요일 |
| 변경 여부 | ❌ 평생 고정 | ✅ 매주 변경 |
| 프롬프트 | `prompts` 테이블 `type=fixed` | `prompts` 테이블 `type=weekly` |
| 조회 페이지 | `fixed.php` | `index.php` |

---

## 6. Success Criteria

### 6.1 Definition of Done — ✅ MVP 완료

- [x] 이름 등록 → pending → 배치 처리 → active + 고유번호 생성 완료
- [x] 이름 검색 시 이번 회차 번호 표시
- [x] 전체 결과 인피니티 스크롤 + 정렬 동작
- [x] 관리자 API로 매주 번호 생성 가능
- [x] 관리자 API로 당첨번호 입력 가능
- [x] 고유번호 페이지에서 이름 고유번호 조회 가능
- [x] 모바일 반응형
- [ ] InfinityFree 배포 완료 (미완)

### 6.2 Quality Criteria — ✅ 충족

- [x] SQL Injection 방지 (Prepared Statements)
- [x] XSS 방지 (`htmlspecialchars()`)
- [x] 관리 API 토큰 보호
- [x] Gemini API 실패 시 graceful 에러 처리
- [x] 글로벌 에러/예외 핸들러 (JSON 응답)
- [x] .htaccess 보안 (민감 디렉토리 차단)
- [ ] 50,000 일일 히트 내 운영 가능 (배포 후 검증 필요)

---

## 7. Risks and Mitigation

| 위험 | 영향 | 대응 |
|------|:----:|------|
| Gemini API 무료 한도 초과 | 🔴 | 청크 분할(10~20명), 요청간 딜레이, Flash-Lite 폴백 |
| InfinityFree 50K 히트 초과 | 🔴 | CSS/JS 최소화, 인라인 또는 결합 |
| InfinityFree FK 미지원 | 🟡 | PHP 코드에서 무결성 관리 |
| PHP 실행 시간 제한 (30초) | 🟡 | 청크 처리, 한 요청에 소수만 처리 |
| InfinityFree cron 미지원 | 🟡 | cron-job.org 등 외부 크론 서비스 |

---

## 8. Architecture Considerations

### 8.1 InfinityFree 제약

| 항목 | 제약 | 대응 |
|------|------|------|
| PHP 8.3 / 128MB | 충분 | — |
| MySQL MyISAM | FK 불가 | PHP에서 관계 관리 |
| 5GB 스토리지 | 충분 | — |
| 50K 일일 히트 | CSS/JS도 카운트 | 자산 최소화 |
| Node.js ❌ | draw.js 불가 | PHP로 재작성 |
| Cron ❌ | 자동 실행 불가 | 외부 크론 서비스 |

### 8.2 Gemini API 전략

- 모델: `gemini-2.5-flash` (15 RPM, 1,000 RPD, 250K TPM)
- 폴백: `gemini-2.5-flash-lite`
- 청크: 10~20명 단위, 요청 간 2~3초 딜레이
- 프롬프트: DB `prompts` 테이블에서 `is_active=true` 읽어서 사용
- JSON 출력 스키마 지정으로 파싱 안정성 확보

### 8.3 프롬프트 관리 정책

- `prompts` 테이블에 `type` (weekly / fixed) + `is_active` 컬럼
- **유니크 제약**: type별 `is_active=true`는 반드시 1개만 존재
- 관리자가 API로 새 프롬프트 추가 → 기존 활성 해제 → 새 것 활성화
- 시스템은 항상 `WHERE type=? AND is_active=true`로 현재 프롬프트 조회

### 8.4 디렉토리 구조

```
notto/
├── index.php                   # 메인 + 결과 조회 (루트)
├── .htaccess                   # Apache 보안 설정
│
├── public/                     # 정적 자산
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── app.js
│   └── assets/images/
│       └── favicon.png
│
├── fixed/                      # 고유번호 조회 페이지
│   └── index.php
│
├── src/                        # 백엔드 로직 (웹 루트 내부지만 .htaccess로 차단)
│   ├── config/
│   │   └── database.php        # PDO 연결 + .env 로드 + 글로벌 에러 핸들러
│   ├── models/
│   │   ├── Name.php            # names 테이블 CRUD
│   │   ├── Round.php           # rounds 테이블 CRUD
│   │   └── Prompt.php          # prompts 테이블 CRUD
│   ├── services/
│   │   ├── GeminiService.php   # Gemini API 클라이언트
│   │   └── DrawService.php     # 번호 생성 비즈니스 로직
│   └── helpers/
│       ├── response.php        # JSON 응답 헬퍼 + 토큰 검증
│       ├── validator.php       # 입력 검증 + 페이지네이션/정렬 파서
│       ├── logger.php          # 날짜별 로그 시스템
│       ├── RoundHelper.php     # 회차 자동 계산 유틸리티
│       └── migrator.php        # DB 마이그레이션 실행기
│
├── api/                        # API 엔드포인트
│   ├── register.php            # POST — 이름 등록
│   ├── check-name.php          # GET  — 이름 중복 체크
│   ├── search.php              # GET  — 이름 부분 검색
│   ├── users.php               # GET  — 전체 목록
│   ├── fixed.php               # GET  — 고유번호 조회
│   ├── round.php               # GET  — 현재 회차 정보
│   ├── draw.php                # POST — 매주 번호 생성 🔒
│   ├── process-pending.php     # POST — 대기열 처리 🔒
│   ├── winning.php             # GET  — 당첨번호 입력 🔒
│   ├── prompts.php             # GET  — 프롬프트 CRUD 🔒
│   ├── healthcheck.php         # GET  — 서버 진단 🔒
│   ├── migrate.php             # POST — DB 마이그레이션 🔒
│   └── logs.php                # GET  — 로그 조회/관리 🔒
│
├── database/
│   ├── schema.sql              # 통합 스키마 (신규 설치용)
│   ├── sample.sql              # 샘플 데이터
│   └── migrations/             # 버전별 마이그레이션
│       ├── V001__initial_schema.sql
│       └── V002__initial_round_data.sql
│
├── logs/                       # 애플리케이션 로그 (날짜별 폴더)
│   └── {YYYY-MM-DD}/
│       ├── api.log
│       ├── model.log
│       └── error.log
│
├── docs/                       # PDCA 문서
│   ├── 01-plan/
│   ├── 02-design/
│   ├── 03-analysis/
│   └── 04-report/
│
├── Dockerfile                  # PHP 8.3-apache 이미지
├── docker-compose.yml          # app + db + adminer
├── docker-entrypoint.sh        # DB 초기화/마이그레이션 자동 실행
├── .env.example
├── .env.local                  # Docker 로컬 환경 변수
├── .gitignore
└── README.md
```

### 8.5 환경 변수

```ini
# Database
DB_HOST=sql123.infinityfree.com
DB_NAME=if0_xxxxxxx_notto
DB_USER=if0_xxxxxxx
DB_PASS=your_password

# Gemini API
GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-2.5-flash

# Admin
ADMIN_TOKEN=your_secure_random_token

# App
APP_ENV=production
APP_DEBUG=false
DIRECT_REGISTER=false          # true: 등록 시 즉시 active (배치 처리 생략)
```

---

## 9. 완료 상태 및 향후 계획

### 9.1 MVP 완료 이력

1. ✅ Plan 문서 확정
2. ✅ Schema 문서 (`docs/01-plan/schema.md`) — 용어 사전 + 데이터 모델
3. ✅ Convention 문서 (`docs/01-plan/conventions.md`) — 코딩 규칙
4. ✅ Design 문서 (`docs/02-design/features/notto.design.md`) — 상세 설계
5. ✅ MVP 구현 완료
6. ✅ Gap Analysis 100% 달성 (`docs/03-analysis/mvp-gap-analysis.md`)
7. ✅ Completion Report (`docs/04-report/mvp-completion-report.md`)
8. ✅ 문서 동기화 (v3.1 — 2026-02-22)

### 9.2 향후 확장 후보 (아이디어 보관함에서)

- 과거 회차 탐색 (회차별 결과 / 이름별 히스토리)
- 당첨 이름 보기 (등수별 당첨자 나열)
- 명예의 전당 (당첨 이력 배지)
- 행운 통계 (적중률, 행운 점수)
- 결과 공유 링크 (SNS/카톡 공유)
- InfinityFree 운영 배포
