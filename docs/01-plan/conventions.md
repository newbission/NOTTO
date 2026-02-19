# NOTTO Convention Document

> **Date**: 2026-02-20
> **Status**: Confirmed

---

## 1. 네이밍 컨벤션

| 대상 | 규칙 | 예시 |
|------|------|------|
| PHP 클래스 | PascalCase | `User`, `GeminiService`, `DrawService` |
| PHP 함수/메서드 | camelCase | `getUserById()`, `generateNumbers()` |
| PHP 변수 | camelCase | `$userName`, `$roundId` |
| PHP 상수 | UPPER_SNAKE_CASE | `MAX_CHUNK_SIZE`, `ADMIN_TOKEN` |
| DB 테이블 | snake_case (복수형) | `users`, `user_rounds`, `prompts` |
| DB 컬럼 | snake_case | `round_number`, `fixed_numbers`, `is_active` |
| 파일 (PHP) | kebab-case | `check-name.php`, `process-pending.php` |
| 파일 (클래스) | PascalCase | `User.php`, `GeminiService.php` |
| CSS 클래스 | kebab-case (BEM) | `hero-section`, `number-ball--matched` |
| JS 함수 | camelCase | `loadMoreUsers()`, `handleSearch()` |
| JS 상수 | UPPER_SNAKE_CASE | `API_BASE_URL`, `PAGE_SIZE` |
| 디렉토리 | kebab-case | `src/`, `api/`, `assets/` |

---

## 2. 코드 스타일

### 2.1 PHP

- PHP 8.3+ 기능 활용 (타입 선언, 화살표 함수, match 등)
- 모든 함수에 **파라미터 타입 + 반환 타입** 명시
- DB 쿼리는 반드시 **Prepared Statements** 사용
- 출력 시 반드시 `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` 사용
- `.env` 파일 직접 파싱 (별도 라이브러리 미사용, 간단한 헬퍼 구현)

```php
// ✅ Good
function getUserById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ❌ Bad
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
| 500 | 서버 오류 |

---

## 4. 에러 코드 사전

| 코드 | 설명 |
|------|------|
| `NAME_EMPTY` | 이름이 비어있음 |
| `NAME_TOO_LONG` | 이름이 20자 초과 |
| `NAME_ALREADY_EXISTS` | 이미 등록된 이름 |
| `NAME_NOT_FOUND` | 이름을 찾을 수 없음 |
| `INVALID_TOKEN` | 관리자 토큰 불일치 |
| `ROUND_NOT_FOUND` | 존재하지 않는 회차 |
| `ROUND_ALREADY_EXISTS` | 이미 존재하는 회차 번호 |
| `DRAW_ALREADY_DONE` | 해당 회차 번호가 이미 생성됨 |
| `GEMINI_API_ERROR` | Gemini API 호출 실패 |
| `NO_ACTIVE_PROMPT` | 활성 프롬프트가 없음 |
| `DB_ERROR` | 데이터베이스 오류 |

---

## 5. Git 워크플로우

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

## 6. 보안 규칙

| 영역 | 규칙 |
|------|------|
| SQL | Prepared Statements 필수 (`PDO::prepare()`) |
| XSS | 출력 시 `htmlspecialchars()` 필수 |
| 관리 API | `ADMIN_TOKEN` 파라미터 검증 |
| 환경 변수 | `.env`는 Git에 포함하지 않음 (`.env.example`만 커밋) |
| 에러 표시 | 프로덕션에서 `display_errors = Off` |

---

## 7. 디렉토리 규칙

```
public/     — 웹 루트, 사용자가 직접 접근하는 파일만
src/        — 웹 루트 외부, require로만 접근
api/        — API 엔드포인트, 직접 접근 가능 (JSON 반환)
database/   — DDL, 마이그레이션 SQL
docs/       — PDCA 문서
```

- `src/`는 절대 URL로 직접 접근 불가하게 배치
- InfinityFree 배포 시 `public/` → `htdocs/`, `src/` → `htdocs/../src/`
