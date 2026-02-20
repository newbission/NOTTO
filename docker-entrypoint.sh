#!/bin/bash
set -e

echo "=== NOTTO Docker Entrypoint ==="

# DB 연결 대기
echo "⏳ MySQL 연결 대기 중..."
MAX_RETRIES=30
RETRY=0
until php -r "
    require '/var/www/html/src/config/database.php';
    try { getDatabase(); echo 'OK'; } catch (Exception \$e) { exit(1); }
" 2>/dev/null; do
    RETRY=$((RETRY + 1))
    if [ $RETRY -ge $MAX_RETRIES ]; then
        echo "❌ MySQL 연결 실패 (${MAX_RETRIES}회 시도)"
        exit 1
    fi
    echo "  재시도 ${RETRY}/${MAX_RETRIES}..."
    sleep 2
done
echo "✅ MySQL 연결 성공"

# DB 상태 확인: names 테이블 존재 여부로 초기 설치 판단
TABLE_EXISTS=$(php -r "
    require '/var/www/html/src/config/database.php';
    \$pdo = getDatabase();
    \$result = \$pdo->query(\"SHOW TABLES LIKE 'names'\");
    echo \$result->rowCount() > 0 ? '1' : '0';
" 2>/dev/null)

if [ "$TABLE_EXISTS" = "0" ]; then
    # 최초 설치: schema.sql 실행
    echo "🆕 최초 DB 설치 — schema.sql 실행"
    php -r "
        require '/var/www/html/src/config/database.php';
        \$pdo = getDatabase();
        \$sql = file_get_contents('/var/www/html/database/schema.sql');
        \$pdo->exec(\$sql);
        echo '✅ schema.sql 적용 완료' . PHP_EOL;
    "
else
    # 기존 DB: 미적용 마이그레이션 실행
    echo "📦 기존 DB 감지 — 마이그레이션 확인"
    php /var/www/html/src/helpers/migrator.php
fi

echo ""
echo "🚀 Apache 시작"
exec apache2-foreground
