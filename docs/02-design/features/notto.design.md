# NOTTO Design Document

> **Summary**: NOTTO v3.0 MVP 상세 설계 — 데이터 모델, API 스펙, UI/UX, 보안, 구현 가이드
> **Date**: 2026-02-20
> **Status**: Confirmed
> **Planning Doc**: notto.plan.md

---

## 1. Overview

### 1.1 설계 목표
- InfinityFree 제약(FK 미지원, Node.js 불가, Cron 불가) 내에서 동작하는 설계
- 다른 컴퓨터에서 이 문서만으로 구현 가능한 수준의 상세도
- 확장 가능한 구조 (아이디어 보관함 기능들을 추후 추가 가능)

### 1.2 설계 원칙
- **Simple First**: 프레임워크 없이 Vanilla PHP/JS/CSS
- **보안 필수**: Prepared Statements, XSS 방지, 토큰 보호
- **API 중심**: 프론트엔드는 API를 통해 데이터 취득 (AJAX)
- **관리자 API**: 모든 관리 기능은 REST API로만 (관리자 UI 없음)

---

## 2. Data Model

> 상세 스키마는 `docs/01-plan/schema.md` 참조

### 2.1 DDL (schema.sql)

```sql
-- ============================================
-- NOTTO Database Schema
-- InfinityFree 호환 (FK 미지원, MyISAM 호환)
-- ============================================

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL COMMENT '등록 이름 (UTF-8, 최대 20자)',
    `status` ENUM('pending','active','deleted') NOT NULL DEFAULT 'pending' COMMENT '상태',
    `fixed_numbers` JSON DEFAULT NULL COMMENT '고유번호 (6개, 1~45)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name` (`name`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rounds` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `round_number` INT NOT NULL COMMENT '회차 번호',
    `draw_date` DATE NOT NULL COMMENT '추첨 날짜',
    `winning_numbers` JSON DEFAULT NULL COMMENT '당첨번호 6개',
    `bonus_number` TINYINT DEFAULT NULL COMMENT '보너스 번호',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_round_number` (`round_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_rounds` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL COMMENT 'users.id',
    `round_id` INT NOT NULL COMMENT 'rounds.id',
    `numbers` JSON NOT NULL COMMENT 'AI 생성 번호 6개',
    `matched_count` TINYINT DEFAULT NULL COMMENT '적중 수',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_round` (`user_id`, `round_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prompts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `type` ENUM('weekly','fixed') NOT NULL COMMENT '프롬프트 용도',
    `content` TEXT NOT NULL COMMENT '프롬프트 내용',
    `is_active` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '현재 사용 여부',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type_active` (`type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. API Specification

### 3.1 공개 API

---

#### POST `/api/register.php` — 이름 등록

**Request**:
```
Content-Type: application/x-www-form-urlencoded

name=홍길동
```

**Response (201)**:
```json
{
    "success": true,
    "data": {
        "id": 42,
        "name": "홍길동",
        "status": "pending",
        "message": "등록이 완료되었습니다. 곧 번호가 생성됩니다."
    }
}
```

**Errors**:
| 조건 | HTTP | 코드 |
|------|:----:|------|
| 이름 비어있음 | 400 | `NAME_EMPTY` |
| 20자 초과 | 400 | `NAME_TOO_LONG` |
| 이미 등록됨 | 400 | `NAME_ALREADY_EXISTS` |
| 삭제된 이름으로 재등록 시도 | 400 | `NAME_DELETED` |

---

#### GET `/api/check-name.php` — 이름 중복 체크

**Request**: `?name=홍길동`

**Response (200)**:
```json
{
    "success": true,
    "data": {
        "exists": true,
        "status": "active"
    }
}
```

> `status` 값: `null`(미등록) / `pending` / `active` / `deleted`

---

#### GET `/api/search.php` — 이름 부분 검색

**Request**: `?q=김가&page=1&per_page=20`

**Response (200)**:
```json
{
    "success": true,
    "data": [
        {
            "id": 10,
            "name": "김가연",
            "status": "active",
            "weekly_numbers": [3, 12, 17, 28, 33, 41],
            "round_number": 1160,
            "winning_numbers": [5, 12, 17, 22, 33, 40],
            "bonus_number": 28,
            "matched_count": 3
        },
        {
            "id": 25,
            "name": "강김가",
            "status": "pending",
            "weekly_numbers": null,
            "round_number": null,
            "winning_numbers": null,
            "bonus_number": null,
            "matched_count": null
        }
    ],
    "meta": {
        "page": 1,
        "per_page": 20,
        "total": 6,
        "query": "김가"
    }
}
```

> - 부분 일치 검색 (`LIKE '%김가%'`)
> - `pending` 상태도 표시 (번호는 null)
> - `deleted` 상태는 제외
> - 최신 회차 번호 + 당첨번호 함께 반환

---

#### GET `/api/users.php` — 전체 목록 (인피니티 스크롤)

**Request**: `?page=1&per_page=20&sort=newest`

| 파라미터 | 값 | 설명 |
|---------|-----|------|
| `sort` | `newest` (기본) | 신규 등록순 (created_at DESC) |
| `sort` | `oldest` | 오래된순 (created_at ASC) |
| `sort` | `name_asc` | 이름 오름차순 |
| `sort` | `name_desc` | 이름 내림차순 |

**Response (200)**:
```json
{
    "success": true,
    "data": [
        {
            "id": 42,
            "name": "홍길동",
            "status": "active",
            "weekly_numbers": [7, 14, 21, 28, 35, 42],
            "round_number": 1160,
            "matched_count": 2
        }
    ],
    "meta": {
        "page": 1,
        "per_page": 20,
        "total": 500,
        "has_more": true
    }
}
```

> - `deleted` 상태 제외
> - `pending` 상태는 포함 (번호 null)

---

#### GET `/api/fixed.php` — 고유번호 조회

**Request**: `?name=홍길동`

**Response (200)**:
```json
{
    "success": true,
    "data": {
        "id": 42,
        "name": "홍길동",
        "fixed_numbers": [4, 11, 19, 27, 36, 43],
        "created_at": "2026-02-20T14:30:00+09:00"
    }
}
```

> 정확히 일치하는 이름만 반환 (부분 검색 아님)

---

### 3.2 관리자 API (🔒 토큰 필수)

모든 관리자 API는 `token` 파라미터로 `ADMIN_TOKEN` 검증.
토큰 불일치 시 `401 + INVALID_TOKEN`.

---

#### POST `/api/draw.php` — 매주 번호 생성

**Request**: `?token=XXX`

Body:
```
round_number=1160&draw_date=2026-02-22
```

**처리 로직**:
1. `rounds` 테이블에 새 회차 생성
2. `users` 테이블에서 `status='active'` 전체 조회
3. 10~20명 단위 청크로 분할
4. `prompts` 테이블에서 `type='weekly' AND is_active=1` 프롬프트 조회
5. 프롬프트 + 이름 목록 → Gemini API 호출
6. 응답 파싱 → `user_rounds` 테이블에 저장
7. 요청 간 2~3초 딜레이 (RPM 보호)

**Response (200)**:
```json
{
    "success": true,
    "data": {
        "round_id": 15,
        "round_number": 1160,
        "total_users": 150,
        "generated": 148,
        "failed": 2,
        "elapsed_seconds": 45
    }
}
```

---

#### POST `/api/process-pending.php` — 대기열 처리

**Request**: `?token=XXX`

**처리 로직**:
1. `users` 테이블에서 `status='pending'` 조회
2. 이름이 없으면 종료
3. `prompts` 테이블에서 `type='fixed' AND is_active=1` 프롬프트 조회
4. 청크 단위로 Gemini API 호출 → 고유번호 생성
5. `users.fixed_numbers` 업데이트 + `status='active'` 변경

**Response (200)**:
```json
{
    "success": true,
    "data": {
        "processed": 5,
        "failed": 0,
        "elapsed_seconds": 8
    }
}
```

---

#### GET `/api/winning.php` — 당첨번호 입력

**Request**: `?token=XXX&round_number=1160&numbers=5,12,17,22,33,40&bonus=28`

**처리 로직**:
1. `rounds` 테이블에서 해당 회차 조회
2. `winning_numbers`, `bonus_number` 업데이트
3. 해당 회차의 모든 `user_rounds`에 대해 `matched_count` 계산 및 업데이트

**matched_count 계산**:
```
matched_count = count(user_numbers ∩ winning_numbers)
```
> 보너스 번호는 별도 표시 (matched_count에는 미포함, 2등 판정용)

**Response (200)**:
```json
{
    "success": true,
    "data": {
        "round_number": 1160,
        "winning_numbers": [5, 12, 17, 22, 33, 40],
        "bonus_number": 28,
        "matched_updated": 150
    }
}
```

---

#### GET `/api/prompts.php` — 프롬프트 관리

**목록 조회**: `?token=XXX&action=list`

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "type": "weekly",
            "content": "다음 사용자들의 이름으로...",
            "is_active": true,
            "updated_at": "2026-02-20T10:00:00"
        },
        {
            "id": 2,
            "type": "fixed",
            "content": "다음 사용자의 이름에서...",
            "is_active": true,
            "updated_at": "2026-02-20T10:00:00"
        }
    ]
}
```

**프롬프트 추가**: `?token=XXX&action=create&type=weekly&content=...&activate=true`
- `activate=true` 시 기존 같은 type 활성 프롬프트 비활성화 후 새 것 활성화

**활성 전환**: `?token=XXX&action=activate&id=3`
- 해당 프롬프트의 type과 같은 기존 활성 비활성화 → 이것만 활성화

---

## 4. UI/UX Design

### 4.1 index.php — 메인 + 결과 조회

```
┌────────────────────────────────────────────┐
│                  NOTTO                      │
│      AI가 점지해주는 이번 주 행운의 번호       │
│                                            │
│    ┌──────────────────────┬──────┐         │
│    │  이름을 입력하세요...   │ 검색 │         │
│    └──────────────────────┴──────┘         │
│                                            │
│    [최신순 ▼] [이름순↑] [이름순↓] [오래된순]   │
│                                            │
│  ┌──────────────────────────────────────┐  │
│  │ 🟢 홍길동                            │  │
│  │ ⚪3  ⚪12  ⚪17  ⚪28  ⚪33  ⚪41    │  │
│  │ 1160회차 | 적중 3개                   │  │
│  └──────────────────────────────────────┘  │
│  ┌──────────────────────────────────────┐  │
│  │ 🟡 이순신 (대기중)                    │  │
│  │ 번호 생성 대기중...                    │  │
│  └──────────────────────────────────────┘  │
│  ┌──────────────────────────────────────┐  │
│  │ 🟢 김철수                            │  │
│  │ ⚪7  ⚪14  ⚪21  ⚪28  ⚪35  ⚪42    │  │
│  │ 1160회차 | 적중 2개                   │  │
│  └──────────────────────────────────────┘  │
│                                            │
│           ⏳ 로딩 중...                     │
└────────────────────────────────────────────┘
```

**동작 상세**:

1. **초기 상태**: 검색바만 화면 중앙에 크게 표시 (히어로)
2. **스크롤 시**: 검색바가 상단에 고정(sticky), 아래로 전체 결과 인피니티 스크롤
3. **검색 시**: 검색바 아래에 검색 결과만 표시 (전체 목록 대체)
4. **미등록 이름 검색 시**: "등록하기" 버튼 표시, 클릭 → AJAX 등록 → "대기중" 카드 표시
5. **번호 카드**: 6개 공(ball) UI, 당첨번호와 적중 시 강조 색상

**정렬 버튼**: 상단에 버튼 그룹, 클릭 시 목록 갱신

### 4.2 fixed.php — 고유번호 조회

```
┌────────────────────────────────────────────┐
│              NOTTO 고유번호                  │
│     이름에 새겨진 당신만의 운명의 번호          │
│                                            │
│    ┌──────────────────────┬──────┐         │
│    │  정확한 이름을 입력...  │ 조회 │         │
│    └──────────────────────┴──────┘         │
│                                            │
│  ┌──────────────────────────────────────┐  │
│  │          🔮 홍길동의 고유번호          │  │
│  │                                      │  │
│  │    ⭐4  ⭐11  ⭐19  ⭐27  ⭐36  ⭐43  │  │
│  │                                      │  │
│  │    등록일: 2026-02-20               │  │
│  │    이 번호는 평생 변하지 않습니다 🔒    │  │
│  └──────────────────────────────────────┘  │
│                                            │
│  [← 메인으로 돌아가기]                       │
└────────────────────────────────────────────┘
```

**동작 상세**:
1. 이름 입력 → 정확히 일치하는 이름 조회
2. 없으면 "등록되지 않은 이름입니다" 표시
3. pending이면 "고유번호 생성 대기중" 표시

### 4.3 번호 공(Ball) UI

```css
/* 번호 공 색상 체계 (한국 로또 공식 색상) */
1~10:   노란색 (#FBC400)
11~20:  파란색 (#69C8F2)
21~30:  빨간색 (#FF7272)
31~40:  회색   (#AAAAAA)
41~45:  초록색 (#B0D840)

/* 적중 시 */
matched: 골드 테두리 + 글로우 효과

/* 고유번호 */
fixed:  별(⭐) 마크 + 특별 색상
```

### 4.4 반응형 브레이크포인트

| 범위 | 레이아웃 |
|------|---------|
| ~480px | 모바일: 카드 1열, 검색바 전체폭 |
| 481~768px | 태블릿: 카드 1~2열 |
| 769px~ | 데스크탑: 카드 2~3열 |

---

## 5. Error Handling

### 5.1 프론트엔드

```javascript
// API 호출 래퍼
async function apiCall(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const data = await response.json();

        if (!data.success) {
            showError(data.error.message);
            return null;
        }
        return data;
    } catch (e) {
        showError('서버와 연결할 수 없습니다.');
        return null;
    }
}
```

### 5.2 백엔드

```php
// 에러 응답 헬퍼
function errorResponse(int $httpCode, string $code, string $message): never {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'error' => ['code' => $code, 'message' => $message]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
```

### 5.3 Gemini API 에러 처리

```
Gemini 호출 실패 시:
1. 해당 청크 스킵 (전체 중단 아님)
2. 실패한 이름들 로깅
3. 최종 결과에 failed 카운트 포함
4. 재시도 로직은 없음 (다음 배치 처리 시 자동 재처리)
```

---

## 6. Security

| 영역 | 구현 방법 |
|------|----------|
| SQL Injection | `PDO::prepare()` + 바인딩, 직접 쿼리 금지 |
| XSS | `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')` 출력 시 필수 |
| 관리 API 보호 | `$_GET['token'] === getenv('ADMIN_TOKEN')` 검증 |
| CORS | 동일 도메인이므로 별도 설정 불필요 |
| Rate Limit | InfinityFree 자체 제한에 의존 |
| 환경 변수 | `.env` 파일, 웹 루트 외부 배치, `.gitignore`에 포함 |

---

## 7. Implementation Guide

### 7.1 구현 순서

```
Phase 1: 기반 구축
├── 1-1. database/schema.sql 작성 ✦ (이 문서의 DDL 그대로 사용)
├── 1-2. .env.example + src/config/database.php (DB 연결)
├── 1-3. src/helpers/response.php (JSON 응답 헬퍼)
└── 1-4. src/helpers/validator.php (입력 검증 헬퍼)

Phase 2: 핵심 API
├── 2-1. api/register.php (이름 등록)
├── 2-2. api/check-name.php (중복 체크)
├── 2-3. api/search.php (부분 검색)
└── 2-4. api/users.php (전체 목록 + 페이지네이션 + 정렬)

Phase 3: AI 연동
├── 3-1. src/services/GeminiService.php (Gemini API 클라이언트)
├── 3-2. src/services/DrawService.php (번호 생성 비즈니스 로직)
├── 3-3. api/prompts.php (프롬프트 CRUD)
├── 3-4. api/process-pending.php (대기열 처리 + 고유번호 생성)
└── 3-5. api/draw.php (매주 번호 생성)

Phase 4: 당첨 비교
├── 4-1. api/winning.php (당첨번호 입력 + matched_count 계산)
└── 4-2. api/fixed.php (고유번호 조회 API)

Phase 5: 프론트엔드
├── 5-1. public/css/style.css (디자인 시스템)
├── 5-2. public/index.php (메인 페이지 HTML)
├── 5-3. public/js/app.js (검색, 인피니티 스크롤, 등록)
├── 5-4. public/fixed.php (고유번호 페이지)
└── 5-5. 반응형 + 번호 공(Ball) UI

Phase 6: 배포
├── 6-1. InfinityFree 계정 설정
├── 6-2. 파일 업로드 (FTP)
├── 6-3. DB 생성 + schema.sql 실행
├── 6-4. .env 파일 생성
├── 6-5. 외부 크론 서비스 설정 (cron-job.org)
└── 6-6. 동작 검증
```

### 7.2 핵심 파일별 책임

| 파일 | 역할 | 의존 |
|------|------|------|
| `src/config/database.php` | PDO 연결 생성, .env 로드 | — |
| `src/helpers/response.php` | `jsonResponse()`, `errorResponse()` | — |
| `src/helpers/validator.php` | `validateName()`, `validateToken()` | — |
| `src/models/User.php` | 사용자 CRUD | database.php |
| `src/models/Round.php` | 회차 CRUD | database.php |
| `src/models/Prompt.php` | 프롬프트 CRUD + 활성 전환 | database.php |
| `src/services/GeminiService.php` | API 호출, JSON 파싱 | — |
| `src/services/DrawService.php` | 청크 분할, 배치 처리 오케스트레이션 | GeminiService, User, Round, Prompt |

### 7.3 GeminiService 설계

```php
class GeminiService {
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gemini-2.5-flash') { ... }

    /**
     * 여러 이름에 대해 번호 생성
     * @param string $prompt 프롬프트 텍스트 ({names} 플레이스홀더 포함)
     * @param string[] $names 이름 배열
     * @return array [['name' => '홍길동', 'numbers' => [1,2,3,4,5,6]], ...]
     */
    public function generateNumbers(string $prompt, array $names): array { ... }
}
```

**Gemini API 호출 방식**:
- REST API 직접 호출 (`file_get_contents()` 또는 `curl`)
- 엔드포인트: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- `responseMimeType: "application/json"` 설정으로 JSON 강제
- `responseSchema` 설정으로 출력 형식 강제

```php
// Gemini API Request Body 예시
$requestBody = [
    'contents' => [
        ['parts' => [['text' => $promptWithNames]]]
    ],
    'generationConfig' => [
        'responseMimeType' => 'application/json',
        'responseSchema' => [
            'type' => 'ARRAY',
            'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'name' => ['type' => 'STRING'],
                    'numbers' => [
                        'type' => 'ARRAY',
                        'items' => ['type' => 'INTEGER']
                    ]
                ],
                'required' => ['name', 'numbers']
            ]
        ]
    ]
];
```

### 7.4 InfinityFree 배포 매핑

```
InfinityFree 서버 구조:
htdocs/                          ← Document Root
├── index.php                    ← public/index.php
├── fixed.php                    ← public/fixed.php
├── css/style.css                ← public/css/style.css
├── js/app.js                    ← public/js/app.js
└── api/                         ← api/
    ├── register.php
    ├── ...
    └── draw.php

htdocs/../src/                   ← src/ (웹 루트 외부)
├── config/database.php
├── models/
├── services/
└── helpers/

htdocs/../.env                   ← 환경 변수 파일
```

> `src/` 내 파일들은 PHP `require_once __DIR__ . '/../src/...'` 로 참조.
> 웹 브라우저에서 직접 접근 불가.

### 7.5 외부 크론 서비스 설정

[cron-job.org](https://cron-job.org) 등을 사용:

| 작업 | URL | 주기 |
|------|-----|------|
| 대기열 처리 | `POST https://notto.example.com/api/process-pending.php?token=XXX` | 매 정각 (*/60 * * * *) |
| 매주 번호 생성 | `POST https://notto.example.com/api/draw.php?token=XXX` | 매주 일요일 06:00 KST |

---

## 8. Test Plan

### 8.1 수동 테스트 체크리스트

**이름 등록**:
- [ ] 1자 이름 등록 가능
- [ ] 20자 이름 등록 가능
- [ ] 21자 이상 에러 반환
- [ ] 한글, 영문, 이모지, 한자 등 UTF-8 전체 허용
- [ ] 중복 이름 에러 반환
- [ ] 등록 후 pending 상태 확인

**검색**:
- [ ] 부분 일치 검색 동작 (예: "김가" → "김가연", "강김가" 등)
- [ ] pending 상태도 검색 결과에 포함 (번호 null)
- [ ] deleted 상태는 검색 결과에서 제외

**인피니티 스크롤**:
- [ ] 스크롤 시 다음 페이지 로드
- [ ] 정렬 변경 시 목록 갱신
- [ ] 더 이상 데이터 없을 때 로딩 중지

**관리자 API**:
- [ ] 토큰 없이 호출 시 401 반환
- [ ] 잘못된 토큰 시 401 반환
- [ ] 대기열 처리 → pending → active + 고유번호 생성
- [ ] 매주 번호 생성 → user_rounds 레코드 생성
- [ ] 당첨번호 입력 → matched_count 업데이트

**고유번호**:
- [ ] 정확한 이름 일치 시 고유번호 표시
- [ ] 미등록 이름 시 에러 메시지
- [ ] pending 상태 시 "생성 대기중" 메시지

### 8.2 엣지 케이스

- Gemini API 응답에서 이름이 변형된 경우 (예: "홍길동" → "Hong Gildong")
- Gemini API 타임아웃
- UTF-8 특수 문자 이름이 JSON 파싱에서 깨지는 경우
- 동시에 같은 이름 등록 시도 (UNIQUE 제약으로 방지)
- 매우 많은 사용자 (1,000명+)에 대한 draw 처리 시간

---

## 9. Clean Architecture

이 프로젝트는 프레임워크 없는 소규모 PHP 프로젝트이므로
엄격한 Clean Architecture 대신 **실용적 레이어 분리**를 따릅니다.

```
┌─────────────────────────────────────────┐
│  public/ (Presentation)                 │
│  - HTML 렌더링                           │
│  - 사용자 입력 받기                       │
├─────────────────────────────────────────┤
│  api/ (Controller 역할)                  │
│  - 입력 검증                             │
│  - Service/Model 호출                    │
│  - JSON 응답 반환                        │
├─────────────────────────────────────────┤
│  src/services/ (Application Logic)      │
│  - 비즈니스 로직 오케스트레이션             │
│  - 외부 API 연동 (Gemini)               │
├─────────────────────────────────────────┤
│  src/models/ (Data Access)              │
│  - DB CRUD                              │
│  - SQL 쿼리                             │
├─────────────────────────────────────────┤
│  src/helpers/ (Utilities)               │
│  - 응답 포매터                           │
│  - 입력 검증기                           │
├─────────────────────────────────────────┤
│  src/config/ (Infrastructure)           │
│  - DB 연결                              │
│  - 환경 변수 로드                        │
└─────────────────────────────────────────┘
```

**의존성 방향**: `public/ → api/ → services/ → models/ → config/`
(역방향 의존 금지)
