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

# DB 설정 및 시스템 초기화
echo "==== 시스템 초기화 시작 ===="
php /var/www/html/api/init_container.php
if [ $? -ne 0 ]; then
    echo "❌ 초기화 실패: 로그를 확인하세요."
    exit 1
fi
echo "==== 시스템 초기화 완료 ===="

echo ""
echo "🚀 Apache 시작"
exec apache2-foreground
