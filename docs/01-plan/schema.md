# NOTTO Schema Document

> **Date**: 2026-02-20
> **Status**: Confirmed
> **Plan Doc**: notto.plan.md

---

## 1. 도메인 용어 사전

| 한국어 | 영문 (코드명) | 설명 |
|--------|-------------|------|
| 이름 | name | 사용자가 등록하는 식별 문자열 (1~20자, UTF-8) |
| 사용자 | user | name을 등록한 엔티티 |
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

---

## 2. 데이터 모델

### 2.1 ERD (개념)

```
┌───────────────┐       ┌─────────────────┐
│    users      │       │     rounds      │
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
        └───▶│  user_rounds  │◀──┘
             ├───────────────┤
             │ id (PK)       │
             │ user_id       │ ← users.id (FK 없음, PHP에서 관리)
             │ round_id      │ ← rounds.id (FK 없음, PHP에서 관리)
             │ numbers       │
             │ matched_count │
             │ created_at    │
             └───────────────┘

┌──────────────────┐
│    prompts       │
├──────────────────┤
│ id (PK)          │
│ type             │ ← 'weekly' | 'fixed'
│ content          │
│ is_active        │ ← type별 1개만 true
│ created_at       │
│ updated_at       │
└──────────────────┘
```

> **Note**: InfinityFree는 Foreign Key를 지원하지 않습니다.
> 모든 관계는 PHP 코드에서 관리합니다.

### 2.2 테이블 상세

#### users

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 사용자 고유 ID |
| `name` | VARCHAR(80) | UNIQUE, NOT NULL | 등록 이름 (UTF-8, 한 글자 최대 4바이트 × 20자 = 80바이트) |
| `status` | ENUM('pending','active','rejected') | NOT NULL, DEFAULT 'pending' | 이름 상태 |
| `fixed_numbers` | JSON | NULL | 고유번호 (6개, 1~45), 생성 전 NULL |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 등록일 |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | 수정일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY (name)`
- `INDEX (status)`

#### rounds

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 회차 고유 ID |
| `round_number` | INT | UNIQUE, NOT NULL | 회차 번호 |
| `draw_date` | DATE | NOT NULL | 추첨 날짜 |
| `winning_numbers` | JSON | NULL | 당첨번호 6개, 입력 전 NULL |
| `bonus_number` | TINYINT | NULL | 보너스 번호, 입력 전 NULL |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 생성일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY (round_number)`

#### user_rounds

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 레코드 고유 ID |
| `user_id` | INT | NOT NULL | users.id 참조 |
| `round_id` | INT | NOT NULL | rounds.id 참조 |
| `numbers` | JSON | NOT NULL | AI 생성 번호 6개 |
| `matched_count` | TINYINT | NULL | 당첨번호 대비 적중 수 (당첨번호 입력 후 계산) |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 생성일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `UNIQUE KEY (user_id, round_id)` — 같은 사용자+회차 중복 방지
- `INDEX (user_id)`
- `INDEX (round_id)`

#### prompts

| 컬럼 | 타입 | 제약조건 | 설명 |
|------|------|---------|------|
| `id` | INT AUTO_INCREMENT | PK | 프롬프트 고유 ID |
| `type` | ENUM('weekly','fixed') | NOT NULL | 프롬프트 용도 |
| `content` | TEXT | NOT NULL | 프롬프트 내용 |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 0 | 현재 사용 여부 (type별 1개만 1) |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | 생성일 |
| `updated_at` | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP | 수정일 |

**인덱스**:
- `PRIMARY KEY (id)`
- `INDEX (type, is_active)`

**제약 로직** (PHP에서 관리):
- `type='weekly' AND is_active=1` 인 레코드는 최대 1개
- `type='fixed' AND is_active=1` 인 레코드는 최대 1개
- 새 프롬프트 활성화 시 기존 활성 프롬프트를 비활성화

---

## 3. 상태 전이도

### 3.1 사용자(User) 상태

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
  (winning=NULL)  (user_rounds 생성)   (winning 저장 + matched_count 계산)
```
