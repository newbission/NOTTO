# NOTTO — AI가 점지해주는 이번 주 행운의 번호

이름을 등록하면 매주 Google Gemini AI가 당신만을 위한 행운의 로또 번호를 추천해줍니다.

## 주요 기능

- **이름 등록**: 이름만 입력하면 등록 완료 (1~20자, UTF-8 전체)
- **매주 번호 생성**: 매주 일요일 AI가 등록된 모든 이름에 대해 번호 생성
- **고유번호**: 이름 최초 등록 시 AI가 부여하는 평생 고정 번호
- **당첨 비교**: 실제 로또 당첨번호와 내 번호 비교
- **검색**: 이름 부분 검색 + 인피니티 스크롤

## 기술 스택

| 구분 | 기술 |
|------|------|
| Backend | PHP 8.3 |
| Database | MySQL 8.0 / MariaDB 11.4 |
| Frontend | Vanilla HTML / CSS / JS |
| AI | Google Gemini API (Free Tier) |
| Hosting | InfinityFree |

## 설치

1. DB 생성 후 `database/schema.sql` 실행
2. `.env.example`을 `.env`로 복사 후 실제 값 입력
3. InfinityFree에 업로드:
   - `public/` → `htdocs/`
   - `src/` → `htdocs/../src/`
   - `api/` → `htdocs/api/`
   - `.env` → `htdocs/../.env`

## API

| Method | Endpoint | 설명 |
|--------|----------|------|
| POST | `/api/register.php` | 이름 등록 |
| GET | `/api/check-name.php` | 중복 체크 |
| GET | `/api/search.php` | 부분 검색 |
| GET | `/api/users.php` | 전체 목록 |
| GET | `/api/fixed.php` | 고유번호 조회 |
| POST | `/api/draw.php` | 매주 번호 생성 🔒 |
| POST | `/api/process-pending.php` | 대기열 처리 🔒 |
| GET | `/api/winning.php` | 당첨번호 입력 🔒 |
| GET | `/api/prompts.php` | 프롬프트 관리 🔒 |

## 문서

- [Plan](docs/01-plan/features/notto.plan.md)
- [Schema](docs/01-plan/schema.md)
- [Convention](docs/01-plan/conventions.md)
- [Design](docs/02-design/features/notto.design.md)

## License

MIT
