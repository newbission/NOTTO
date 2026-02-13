# NOTTO - AI 기반 로또 번호 추천 서비스

이름을 등록하면 매주 Google Gemini AI가 당신만을 위한 행운의 로또 번호를 추천해줍니다.

> ⚠️ **프로젝트 상태**: 현재 v2.0 아키텍처로 전면 재구성되었습니다. (PHP + MySQL + Node.js + AI)

## 주요 기능

*   **사용자 등록**: 닉네임만으로 간편하게 등록 (`add_user.php`)
*   **실시간 조회**: 무한 스크롤 UI를 통한 전체 사용자 및 추천 번호 조회 (`get_users.php`)
*   **AI 추첨 자동화**: 매주 일요일 자정(KST), Node.js 스크립트가 실행되어 모든 사용자의 로또 번호를 생성 (`draw.js`)
*   **생성형 AI 활용**: Google Gemini Pro 모델을 사용하여 각 사용자의 이름에 기반한 '행운의 번호' 생성
*   **반응형 UI**: Tailwind CSS 기반의 모던하고 직관적인 인터페이스

## 기술 스택

### Frontend
*   Vanilla JavaScript (No Framework)
*   Tailwind CSS (CDN)
*   Intersection Observer API (무한 스크롤)

### Backend
*   **Language**: PHP 8.x
*   **Database**: MySQL 8.0
*   **Web Server**: Apache

### Automation & AI
*   **Runtime**: Node.js (Google Generative AI SDK)
*   **CI/CD**: GitHub Actions (Weekly Cron Job)
*   **AI Model**: Google Gemini Pro

## 설치 및 실행 (Local Development)

이 프로젝트는 Docker Compose를 통해 로컬 환경에서 쉽게 실행할 수 있습니다.

### 사전 요구사항
*   Docker & Docker Compose
*   Google Gemini API Key

### 실행 방법

1. **레포지토리 클론**
   ```bash
   git clone https://github.com/YOUR_USERNAME/notto.git
   cd notto
   ```

2. **환경 변수 설정**
   `.env` 파일을 생성하고 다음 내용을 추가하세요 (필요한 경우).
   기본 데이터베이스 설정은 `docker-compose.yml`에 정의되어 있습니다.

3. **서비스 실행**
   ```bash
   docker-compose up -d
   ```
   *   Web 서버는 `http://localhost:8080`에서 접근 가능합니다.
   *   MySQL 데이터베이스는 자동으로 초기화됩니다 (`database.sql`).

## API 엔드포인트

| 파일명 | 설명 | Method |
| --- | --- | --- |
| `add_user.php` | 새로운 사용자 등록 | POST |
| `get_users.php` | 사용자 목록 조회 (Pagination) | GET |
| `update_numbers.php` | (내부용) 생성된 로또 번호 업데이트 | POST |

## 자동화 (Automation)

`draw.js` 스크립트는 등록된 모든 사용자에 대해 다음 과정을 수행합니다:
1.  `get_users.php`를 통해 사용자 목록 조회
2.  Google Gemini API에 사용자 이름을 프롬프트로 전송하여 번호 생성
3.  `update_numbers.php`를 통해 생성된 번호 저장

이 작업은 `.github/workflows/draw.yml`에 의해 매주 일요일 00:00 KST에 자동으로 실행됩니다.

## 라이선스

MIT License

## 변경 이력 (Changelog)

### v2.0.0 (2026-02-13)
*   **Architecture Overhaul**: PHP 8.x + MySQL 8.0 + Node.js 기반으로 전체 재작성
*   **New Features**:
    *   닉네임 기반 사용자 등록 및 상태 관리
    *   무한 스크롤(Infinite Scroll) 사용자 목록 조회
    *   Docker Compose 개발 환경 지원
*   **Automation**: GitHub Actions 및 Node.js를 이용한 매주 자동 로또 번호 추첨 (Google Gemini Pro)
*   **UI/UX**: HTML/JS 레거시 코드 제거, Tailwind CSS 기반의 모던 UI 적용

### v1.0.0 (Legacy)
*   초기 버전: 순수 HTML/JS 기반 구현 (Deprecated)
*   이전 코드는 `v1.0-legacy` 태그에서 확인 가능
