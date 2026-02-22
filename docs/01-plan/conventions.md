# NOTTO Convention Document

> **Date**: 2026-02-22
> **Status**: Confirmed

---

## 1. 네이밍 컨벤션

| 대상 | 규칙 | 예시 |
|------|------|------|
| PHP 클래스 | PascalCase | `User`, `GeminiService`, `DrawService` |
| PHP 함수/메서드 | camelCase | `getUserById()`, `generateNumbers()` |
| PHP 변수 | camelCase | `$userName`, `$roundId` |
| PHP 상수 | UPPER_SNAKE_CASE | `MAX_CHUNK_SIZE`, `ADMIN_TOKEN` |
| DB 테이블 | snake_case (복수형) | `names`, `name_rounds`, `prompts` |
| DB 컬럼 | snake_case | `round_number`, `fixed_numbers`, `is_active` |
| 파일 (PHP) | kebab-case | `check-name.php`, `process-pending.php` |
| 파일 (클래스) | PascalCase | `Name.php`, `GeminiService.php`, `RoundHelper.php` |
| CSS 클래스 | kebab-case (BEM) | `hero-section`, `number-ball--matched` |
| JS 함수 | camelCase | `loadMoreUsers()`, `handleSearch()` |
| JS 상수 | UPPER_SNAKE_CASE | `API_BASE_URL`, `PAGE_SIZE` |
| 디렉토리 | kebab-case | `src/`, `api/`, `assets/` |
| 마이그레이션 파일 | `V{NNN}__{description}.sql` | `V001__initial_schema.sql` |

---

## 2. 코드 스타일

### 2.1 PHP

- PHP 8.3+ 기능 활용 (타입 선언, 화살표 함수, match 등)
- 모든 함수에 **파라미터 타입 + 반환 타입** 명시
- DB 쿼리는 반드시 **Prepared Statements** 사용
- 출력 시 반드시 `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` 사용
- `.env` 파일 직접 파싱 (별도 라이브러리 미사용, 간단한 헬퍼 구현)

```php
// Good
function getUserById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Bad
function getUser($id) {
    $result = mysql_query("SELECT * FROM users WHERE id = $id");
    return mysql_fetch_assoc($result);
}
```

### 2.2 JavaScript

- ES6+ 문법 사용 (`const`/`let`, 화살표 함수, 템플릿 리터럴)
- `var` 사용 금지
- DOM 조작 시 `document.getElementById()` 또는 `querySelector()` 사용
- API 호출은 `fetch()` (axios 미사용, 의존성 최소화)

### 2.3 CSS

- 프레임워크 없이 Vanilla CSS
- CSS 변수(Custom Properties)로 디자인 토큰 관리
- BEM(Block-Element-Modifier) 네이밍 컨벤션
- 모바일 퍼스트 반응형 (`min-width` 미디어 쿼리)

```css
/* 디자인 토큰 예시 */
:root {
    --color-primary: #...;
    --color-bg: #...;
    --font-main: 'Pretendard', sans-serif;
    --radius-md: 8px;
}
```

---

## 3. API 응답 포맷

모든 API는 **JSON** 응답.

### 성공 응답
```json
{
    "success": true,
    "data": { ... },
    "meta": {
        "page": 1,
        "per_page": 20,
        "total": 150
    }
}
```

### 에러 응답
```json
{
    "success": false,
    "error": {
        "code": "NAME_ALREADY_EXISTS",
        "message": "이미 등록된 이름입니다."
    }
}
```

### HTTP 상태 코드
| 코드 | 용도 |
|------|------|
| 200 | 성공 |
| 201 | 생성 완료 |
| 400 | 잘못된 요청 (입력 검증 실패) |
| 401 | 인증 실패 (관리자 토큰 오류) |
| 404 | 리소스 없음 |
| 405 | 허용되지 않은 HTTP 메서드 |
| 500 | 서버 오류 |

---

## 4. 에러 코드 사전

### 4.1 사용자 입력 관련

| 코드 | HTTP | 설명 |
|------|------|------|
| `NAME_EMPTY` | 400 | 이름이 비어있음 |
| `NAME_TOO_LONG` | 400 | 이름이 20자 초과 |
| `NAME_ALREADY_EXISTS` | 400 | 이미 등록된 이름 |
| `NAME_NOT_FOUND` | 404 | 이름을 찾을 수 없음 |
| `INVALID_DATE` | 400 | 날짜 형식 오류 (YYYY-MM-DD 필요) |
| `INVALID_TOKEN` | 401 | 관리자 토큰 불일치 |

### 4.2 회차/추첨 관련

| 코드 | HTTP | 설명 |
|------|------|------|
| `ROUND_NOT_FOUND` | 404 | 존재하지 않는 회차 |
| `ROUND_ALREADY_EXISTS` | 400 | 이미 존재하는 회차 번호 |
| `DRAW_ALREADY_DONE` | 400 | 해당 회차 번호가 이미 생성됨 |
| `NO_CURRENT_ROUND` | 500 | DB에 기존 회차 없음 (마이그레이션 필요) |
| `NO_ACTIVE_PROMPT` | 400 | 활성 프롬프트가 없음 |
| `GEMINI_API_ERROR` | 500 | Gemini API 호출 실패 |

### 4.3 시스템/서버 관련

| 코드 | HTTP | 설명 |
|------|------|------|
| `METHOD_NOT_ALLOWED` | 405 | 허용되지 않은 HTTP 메서드 |
| `DB_ERROR` | 500 | 데이터베이스 쿼리 오류 |
| `DB_CONNECTION_ERROR` | 500 | DB 연결 실패 |
| `INTERNAL_ERROR` | 500 | Uncaught Exception |
| `PHP_ERROR` | 500 | PHP 에러 (Warning/Notice 등) |
| `FATAL_ERROR` | 500 | PHP Fatal Error |

### 4.4 로그 API 관련

| 코드 | HTTP | 설명 |
|------|------|------|
| `LOGS_DIR_NOT_FOUND` | 500 | 로그 디렉토리 없음 |
| `DATE_NOT_FOUND` | 404 | 해당 날짜의 로그 없음 |
| `FILE_NOT_FOUND` | 404 | 로그 파일 없음 |
| `DELETE_FAILED` | 500 | 삭제 실패 |
| `READ_FAILED` | 500 | 파일 읽기 실패 |

---

## 5. 로깅 컨벤션

> 구현: `src/helpers/logger.php`

### 5.1 로그 경로

```
logs/{YYYY-MM-DD}/{channel}.log
```

예: `logs/2026-02-22/app.log`, `logs/2026-02-22/error.log`

### 5.2 채널

| 채널 | 용도 |
|------|------|
| `app` | 일반 애플리케이션 로그 (기본값) |
| `api` | API 요청/응답 로그 |
| `model` | 모델 계층 로그 |
| `db` | 데이터베이스 관련 로그 |
| `error` | 에러/예외 로그 |
| `security` | 인증/보안 관련 로그 |
| `migrator` | DB 마이그레이션 로그 |
| `round` | 회차/추첨 관련 로그 |

### 5.3 로그 레벨

| 레벨 | 용도 |
|------|------|
| `INFO` | 정상 흐름 기록 |
| `WARN` | 주의가 필요한 상황 |
| `ERROR` | 오류 발생 |
| `DEBUG` | 디버그 정보 (`APP_DEBUG=true` 환경에서만 기록) |

### 5.4 사용법

```php
require_once __DIR__ . '/../helpers/logger.php';

logInfo('사용자 등록 완료', ['name' => $name], 'app');
logWarn('중복 이름 시도', ['name' => $name], 'security');
logError('DB 쿼리 실패', ['query' => $sql, 'error' => $e->getMessage()], 'db');
logDebug('요청 데이터', ['body' => $input], 'api');
```

### 5.5 로그 포맷

```
[{YYYY-MM-DD HH:mm:ss}] [{LEVEL}] {message} | {context_json}
```

예: `[2026-02-22 14:30:00] [INFO] 사용자 등록 완료 | {"name":"홍길동"}`

---

## 6. 마이그레이션 컨벤션

> 구현: `src/helpers/migrator.php`

### 6.1 파일 구조

```
database/migrations/
  V001__initial_schema.sql
  V002__initial_round_data.sql
  V003__next_change.sql
  ...
```

### 6.2 네이밍 규칙

- 형식: `V{NNN}__{description}.sql`
- 버전 번호: 3자리 제로패딩 (`V001`, `V002`, ...)
- 설명: snake_case로 변경 내용 요약
- 구분자: 밑줄 두 개 (`__`)

### 6.3 상태 추적

- `schema_versions` 테이블에 적용된 버전과 적용 시각 기록
- 이미 적용된 버전은 자동으로 스킵
- 실패 시 후속 마이그레이션 중단 (순서 보장)

### 6.4 실행 방법

- **Docker 환경**: `docker-entrypoint.sh`에서 컨테이너 시작 시 자동 실행
- **수동 실행**: `php src/helpers/migrator.php`
- **상태 확인**: `php src/helpers/migrator.php --status`

---

## 7. Docker 개발 환경

### 7.1 서비스 구성

| 서비스 | 이미지 | 포트 | 용도 |
|--------|--------|------|------|
| `app` | `php:8.3-apache` (커스텀 빌드) | `8080:80` | PHP 애플리케이션 |
| `db` | `mysql:8.0` | `3307:3306` | MySQL 데이터베이스 |
| `adminer` | `adminer` | `8081:8080` | DB 관리 UI |

### 7.2 개발 워크플로우

```bash
# 최초 실행
docker compose up -d --build

# 일반 실행
docker compose up -d

# 로그 확인
docker compose logs -f app

# 종료
docker compose down

# DB 초기화 (볼륨 삭제)
docker compose down -v
```

### 7.3 환경 변수

- `.env.local` 파일 사용 (`env_file`로 주입)
- `.env.local`은 Git에 포함하지 않음
- Docker 컨테이너 시작 시 `docker-entrypoint.sh`가 DB 초기화/마이그레이션 자동 수행

### 7.4 엔트리포인트 동작

1. MySQL 연결 대기 (최대 30회 재시도)
2. `names` 테이블 존재 여부로 초기 설치 판단
   - 최초 설치: `database/schema.sql` 실행
   - 기존 DB: `migrator.php`로 미적용 마이그레이션 실행
3. Apache 시작

---

## 8. Git 워크플로우

### 브랜치 전략
- `main`: 배포 가능 상태
- `develop`: 개발 중 (1인 프로젝트이므로 main 직접 작업도 허용)
- `feature/*`: 기능 단위 브랜치 (필요 시)

### 커밋 컨벤션
- **한국어** 작성
- Conventional Commits 준수

| 접두사 | 용도 | 예시 |
|--------|------|------|
| `feat:` | 새 기능 | `feat: 이름 등록 API 구현` |
| `fix:` | 버그 수정 | `fix: 검색 결과 정렬 오류 수정` |
| `refactor:` | 리팩토링 | `refactor: DB 쿼리 헬퍼 분리` |
| `style:` | 스타일 | `style: 메인 페이지 레이아웃 조정` |
| `docs:` | 문서 | `docs: API 스펙 문서 추가` |
| `chore:` | 기타 | `chore: .gitignore 업데이트` |

### 커밋 규칙
- 작고 사소한 단위마다 즉시 커밋 (비대 방지)
- `git add <파일>` 개별 스테이징 (`git add .` 금지)
- `git push`는 명시적 지시 시에만

---

## 9. 보안 규칙

| 영역 | 규칙 |
|------|------|
| SQL | Prepared Statements 필수 (`PDO::prepare()`) |
| XSS | 출력 시 `htmlspecialchars()` 필수 |
| 관리 API | `ADMIN_TOKEN` 파라미터 검증 |
| 환경 변수 | `.env`는 Git에 포함하지 않음 (`.env.example`만 커밋) |
| 에러 표시 | 프로덕션에서 `display_errors = Off` |

---

## 10. 디렉토리 규칙

```
public/                — 웹 루트, 사용자가 직접 접근하는 파일만
src/                   — 웹 루트 외부, require로만 접근
api/                   — API 엔드포인트, 직접 접근 가능 (JSON 반환)
fixed/                 — 고유번호 조회 페이지
database/              — DDL, 마이그레이션 SQL
  database/migrations/ — 버전별 마이그레이션 SQL (V{NNN}__{desc}.sql)
logs/                  — 애플리케이션 로그 (날짜별 폴더, .gitignore 대상)
docs/                  — PDCA 문서
```

- `src/`는 절대 URL로 직접 접근 불가하게 배치
- `logs/`는 자동 생성되며 Git에 포함하지 않음
- InfinityFree 배포 시 `public/` -> `htdocs/`, `src/` -> `htdocs/../src/`
