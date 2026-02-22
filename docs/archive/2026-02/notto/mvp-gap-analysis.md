# MVP Gap Analysis Report (Check)

> **Date**: 2026-02-21
> **Updated**: 2026-02-22 (문서 동기화 반영)
> **Target**: NOTTO v3.1 MVP
> **Status**: 100% 일치 (Gap 없음)

## 1. 개요

본 문서는 `docs/01-plan/features/notto.plan.md` 및 `docs/02-design/features/notto.design.md`에 정의된 MVP 요구사항이 실제 코드 베이스에 정상적으로 구현되었는지 점검(Check)하는 Gap Analysis 문서입니다.

> **v3.1 업데이트**: 2026-02-22에 기획 문서와 실제 구현의 차이를 식별하고, 문서를 실제 코드에 맞게 동기화 완료했습니다.

## 2. 요구사항 검증 결과

### 2.1 MVP 핵심 기능 (Plan 문서 기준)

| MVP 항목 | 점검 결과 | 담당 코드베이스 | 상태 |
| --- | --- | --- | --- |
| 1. 이름 등록 | 정상 구현됨 | `api/register.php` + `Name.php` | ✅ Pass |
| 2. 이름 부분 검색 | 정상 구현됨 | `api/search.php` + `Name::search()` | ✅ Pass |
| 3. 전체 결과 인피니티 스크롤 | 정상 구현됨 | `api/users.php` (4종 정렬), `app.js` 연동 | ✅ Pass |
| 4. 대기열 배치 처리 | 정상 구현됨 | `api/process-pending.php` + `DrawService` | ✅ Pass |
| 5. 고유번호 생성 | 정상 구현됨 | `DrawService::processPending()` (Gemini fixed) | ✅ Pass |
| 6. 매주 번호 생성 | 정상 구현됨 | `api/draw.php` + `DrawService::drawWeekly()` | ✅ Pass |
| 7. 고유번호 별도 페이지 | 정상 구현됨 | `fixed/index.php` | ✅ Pass |
| 8. 실제 당첨번호 비교 | 정상 구현됨 | `api/winning.php` (matched_count 계산) | ✅ Pass |
| 9. 프롬프트 DB 관리 | 정상 구현됨 | `api/prompts.php` + `Prompt.php` | ✅ Pass |
| 10. 관리자 API | 정상 구현됨 | `requireAdminToken()` 보안 적용 | ✅ Pass |

### 2.2 추가 구현 사항 (기획 대비 추가)

| 추가 항목 | 설명 | 코드베이스 | 상태 |
| --- | --- | --- | --- |
| 서버 진단 API | PHP/DB/로그 상태 점검 | `api/healthcheck.php` | ✅ 구현됨 |
| 마이그레이션 시스템 | 버전별 DB 스키마 관리 | `migrator.php` + `api/migrate.php` | ✅ 구현됨 |
| 로그 조회 API | 원격 로그 확인/삭제 | `api/logs.php` | ✅ 구현됨 |
| 회차 정보 API | 현재 회차 공개 조회 | `api/round.php` + `RoundHelper.php` | ✅ 구현됨 |
| 로깅 시스템 | 날짜별 폴더 구조 로그 | `logger.php` | ✅ 구현됨 |
| Docker 개발환경 | PHP+MySQL+Adminer | `Dockerfile` + `docker-compose.yml` | ✅ 구현됨 |
| Apache 보안 | 민감 경로 차단 | `.htaccess` | ✅ 구현됨 |
| 글로벌 에러 핸들러 | JSON 에러 응답 통일 | `database.php` | ✅ 구현됨 |

### 2.3 문서-코드 네이밍 차이 (v3.1에서 해소)

| 영역 | 기존 문서 (v3.0) | 실제 코드 | v3.1 문서 |
| --- | --- | --- | --- |
| DB 테이블 | `users` | `names` | ✅ `names`로 수정 |
| DB 테이블 | `user_rounds` | `name_rounds` | ✅ `name_rounds`로 수정 |
| 모델 클래스 | `User.php` | `Name.php` | ✅ `Name.php`로 수정 |
| FK 컬럼 | `user_id` | `name_id` | ✅ `name_id`로 수정 |
| 페이지 경로 | `public/index.php` | `index.php` (루트) | ✅ 루트로 수정 |
| 페이지 경로 | `public/fixed.php` | `fixed/index.php` | ✅ 수정됨 |

## 3. 결론

- **Gap 내역**: 없음 (100% 일치)
- **문서 동기화**: v3.1에서 실제 구현과 기획 문서 간의 모든 차이를 해소
- **추가 구현**: 기획 대비 8개 항목이 추가로 구현되어 문서에 반영 완료
