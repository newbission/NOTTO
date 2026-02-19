<?php

declare(strict_types=1);

/**
 * Input Validation Helpers
 *
 * 이름 등록, 검색 등에서 사용하는 입력 검증
 */

/** 이름 최대 글자수 (UTF-8 기준) */
const NAME_MAX_LENGTH = 20;

/**
 * 이름 유효성 검증
 *
 * @return string|null 에러 메시지 (유효하면 null)
 */
function validateName(string $name): ?string
{
    if ($name === '') {
        return 'NAME_EMPTY';
    }

    // mb_strlen으로 UTF-8 글자 수 체크
    if (mb_strlen($name, 'UTF-8') > NAME_MAX_LENGTH) {
        return 'NAME_TOO_LONG';
    }

    return null;
}

/**
 * 이름 입력값 정리 (trim + 연속 공백 제거)
 */
function sanitizeName(string $name): string
{
    $name = trim($name);
    // 연속 공백을 하나로
    $name = preg_replace('/\s+/u', ' ', $name);
    return $name;
}

/**
 * 페이지네이션 파라미터 파싱
 */
function parsePagination(int $defaultPerPage = 20, int $maxPerPage = 100): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min($maxPerPage, (int) ($_GET['per_page'] ?? $defaultPerPage)));
    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => $offset,
    ];
}

/**
 * 정렬 파라미터 파싱
 *
 * @param string[] $allowedSorts 허용되는 정렬 키 목록
 */
function parseSort(array $allowedSorts, string $default = 'newest'): string
{
    $sort = $_GET['sort'] ?? $default;
    return in_array($sort, $allowedSorts, true) ? $sort : $default;
}

/**
 * 정렬 키를 SQL ORDER BY로 변환
 */
function sortToOrderBy(string $sort): string
{
    return match ($sort) {
        'newest' => 'u.created_at DESC',
        'oldest' => 'u.created_at ASC',
        'name_asc' => 'u.name ASC',
        'name_desc' => 'u.name DESC',
        default => 'u.created_at DESC',
    };
}

/**
 * 로또 번호 유효성 검증 (6개, 1~45, 중복 없음)
 */
function validateLottoNumbers(array $numbers): bool
{
    if (count($numbers) !== 6) {
        return false;
    }

    foreach ($numbers as $n) {
        if (!is_int($n) || $n < 1 || $n > 45) {
            return false;
        }
    }

    // 중복 체크
    return count(array_unique($numbers)) === 6;
}
