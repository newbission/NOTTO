# NOTTO Schema Document

> **Date**: 2026-02-22
> **Status**: Confirmed
> **Plan Doc**: notto.plan.md
> **Schema Version**: V002

---

## 1. 도메인 용어 사전

| 한국어 | 영문 (코드명) | 설명 |
|--------|-------------|------|
| 이름 | name | 등록되는 식별 문자열 (1~20자, UTF-8). 이 서비스의 핵심 엔티티 |
| 회차 | round | 로또 추첨의 단위 (매주 1회) |
| 매주번호 | weekly_numbers | 매 회차 AI가 생성하는 6개 번호 (1~45) |
| 고유번호 | fixed_numbers | 이름 최초 등록 시 AI가 1회 생성하는 평생 고정 6개 번호 |
| 당첨번호 | winning_numbers | 실제 로또 당첨 번호 (관리자 수동 입력) |
| 보너스번호 | bonus_number | 실제 로또 보너스 번호 |
| 대기열 | pending | 등록 신청 후 아직 고유번호가 생성되지 않은 상태 |
| 활성 | active | 고유번호 생성 완료 → 매주 번호 생성 대상 |
| 삭제됨 | rejected | 관리자에 의해 제거된 이름 (soft delete) |
| 프롬프트 | prompt | AI에게 전달하는 번호 생성 지시문 |
| 배치 처리 | batch process | 매 정각 대기열 이름들을 일괄 고유번호 생성 |
| 추첨 | draw | 매주 번호 생성 작업 |

> **Note**: 이 서비스에는 "사용자(user)" 개념이 없습니다. 로그인/인증 없이 "이름(name)"만 등록하여 번호를 받는 구조입니다.

---

## 2. 데이터 모델

### 2.1 ERD (개념)

```
┌───────────────┐       ┌─────────────────┐
│    names      │       │     rounds      │
├───────────────┤       ├─────────────────┤
│ id (PK)       │       │ id (PK)         │
│ name          │       │ round_number    │
│ status        │       │ draw_date       │
│ fixed_numbers │       │ winning_numbers │
│ created_at    │       │ bonus_number    │
│ updated_at    │       │ created_at      │
└───────┬───────┘       └────────┬────────┘
        │                        │
        │    ┌───────────────┐   │
        └───▶│  name_rounds  │◀──┘
             ├───────────────┤
             │ id (PK)       │
             │ name_id       │ ← names.id (FK 없음, PHP에서 관리)
             │ round_id      │ ← rounds.id (FK 없음, PHP에서 관리)
             │ numbers       │
             │ matched_count │
             │ created_at    │
             └───────────────┘

┌──────────────────┐    ┌──────────────────────┐
│    prompts       │    │   schema_versions    │
├──────────────────┤    ├──────────────────────┤
│ id (PK)          │    │ version (PK)         │
│ type             │    │ description          │
│ content          │    │ applied_at           │
│ is_active        │    └──────────────────────┘
│ created_at       │
│ updated_at       │
└──────────────────┘
```

> **Note**: InfinityFree는 Foreign Key를 지원하지 않습니다.
> 모든 관계는 PHP 코드에서 관리합니다.

### 2.2 테이블 상세

#### names

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 이름 고유 ID |
| `name` | VARCHAR(80) | UNIQUE, NOT NULL | 등록 이름 (UTF-8, 한 글자 최대 4바이트 × 20자 = 80바이트) |
| `status` | ENUM('pending','active','rejected') | NOT NULL, DEFAULT 'pending' | 이름 상태 |
| `fixed_numbers` | JSON | DEFAULT NULL | 고유번호 (6개, 1~45), 생성 전 NULL |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 등록일 |
| `updated_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 수정일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_name (name)`
- `INDEX idx_status (status)`

#### rounds

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 회차 고유 ID |
| `round_number` | INT | UNIQUE, NOT NULL | 회차 번호 |
| `draw_date` | DATE | NOT NULL | 추첨 날짜 |
| `winning_numbers` | JSON | DEFAULT NULL | 당첨번호 6개, 입력 전 NULL |
| `bonus_number` | TINYINT | DEFAULT NULL | 보너스 번호, 입력 전 NULL |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 생성일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_round_number (round_number)`

#### name_rounds

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 레코드 고유 ID |
| `name_id` | INT | NOT NULL | names.id 참조 |
| `round_id` | INT | NOT NULL | rounds.id 참조 |
| `numbers` | JSON | NOT NULL | AI 생성 번호 6개 |
| `matched_count` | TINYINT | DEFAULT NULL | 당첨번호 대비 적중 수 (당첨번호 입력 후 계산) |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 생성일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_name_round (name_id, round_id)` -- 같은 이름+회차 중복 방지
- `INDEX idx_name_id (name_id)`
- `INDEX idx_round_id (round_id)`

#### prompts

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 프롬프트 고유 ID |
| `type` | ENUM('weekly','fixed') | NOT NULL | 프롬프트 용도 |
| `content` | TEXT | NOT NULL | 프롬프트 내용 |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 0 | 현재 사용 여부 (type별 1개만 1) |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 생성일 |
| `updated_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | 수정일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `INDEX idx_type_active (type, is_active)`

**제약 로직** (PHP에서 관리):
- `type='weekly' AND is_active=1` 인 레코드는 최대 1개
- `type='fixed' AND is_active=1` 인 레코드는 최대 1개
- 새 프롬프트 활성화 시 기존 활성 프롬프트를 비활성화

#### schema_versions

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `version` | VARCHAR(10) | PK, NOT NULL | 버전 번호 (V001, V002...) |
| `description` | VARCHAR(255) | NOT NULL | 마이그레이션 설명 |
| `applied_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | 적용 일시 |

**인덱스**:
- `PRIMARY KEY (version)`

---

## 3. 상태 전이도

### 3.1 이름(Name) 상태

```
[등록 신청]
     │
     ▼
  pending ──────── [정각 배치 처리] ──────▶ active
     │                                       │
     │                                       │ [관리자 삭제]
     │ [관리자 삭제]                          ▼
     └──────────────────────────────────▶ rejected
```

### 3.2 회차(Round) 생명주기

```
[관리자 draw.php 호출]
     │
     ▼
  Round 생성 ───▶ 번호 생성 완료 ───▶ [관리자 당첨번호 입력]
  (winning=NULL)  (name_rounds 생성)   (winning 저장 + matched_count 계산)
```

---

## 4. 마이그레이션 시스템

### 4.1 개요

데이터베이스 스키마 변경은 마이그레이션 파일로 관리합니다.

- **통합 스키마**: `database/schema.sql` -- 신규 설치 시 이 파일 하나만 실행하면 전체 DB 구성 완료
- **개별 마이그레이션**: `database/migrations/` 폴더 -- 기존 DB에 점진적 변경 적용

### 4.2 파일 네이밍 규칙

```
database/migrations/V{번호}__{설명}.sql
```

- 버전 번호: `V001`, `V002`, ... (3자리 zero-padded)
- 구분자: 언더스코어 2개 (`__`)
- 설명: snake_case 영문

### 4.3 현재 마이그레이션 목록

| 버전 | 파일 | 설명 |
|------|------|------|
| V001 | `V001__initial_schema.sql` | 최초 테이블 생성 (names, rounds, name_rounds, prompts) + 초기 프롬프트 데이터 |
| V002 | `V002__initial_round_data.sql` | 최초 회차 데이터 (1212회) |

### 4.4 버전 추적

`schema_versions` 테이블에 적용된 마이그레이션 버전이 기록됩니다.
새 마이그레이션 실행 시 해당 버전을 INSERT하여 중복 적용을 방지합니다.
