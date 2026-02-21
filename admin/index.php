<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOTTO 관리자 도구</title>
</head>

<body style="font-family: sans-serif; padding: 20px;">
    <h1>NOTTO 임시 관리자 페이지</h1>

    <div>
        <label>
            <strong>관리자 토큰(Admin Token):</strong>
            <input type="password" id="adminToken" style="padding: 5px; width: 250px;">
        </label>
    </div>

    <hr style="margin: 20px 0;">

    <div style="margin-bottom: 20px;">
        <h2>1. 대기열 등록 (Pending 처리)</h2>
        <p>대기열에 있는 이름들의 고유번호를 생성하고 Active 상태로 변경합니다.</p>
        <button onclick="processPending()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">대기열 등록</button>
    </div>

    <hr style="margin: 20px 0;">

    <div style="margin-bottom: 20px;">
        <h2>2. 다음 회차 추첨 (Draw)</h2>
        <p>현재 등록된 Active 이름들 중에서 다음 회차 당첨자를 뽑습니다.</p>
        <button onclick="drawWeekly()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">다음 회차 추첨</button>
    </div>

    <hr style="margin: 20px 0;">

    <h2>실행 결과</h2>
    <pre id="resultOutput"
        style="background: #f4f4f4; padding: 15px; border: 1px solid #ddd; min-height: 100px; white-space: pre-wrap; font-family: monospace;"></pre>

    <script>
        async function runApi(url) {
            const token = document.getElementById('adminToken').value.trim();
            const output = document.getElementById('resultOutput');

            if (!token) {
                alert('관리자 토큰(Admin Token)을 입력해주세요.');
                document.getElementById('adminToken').focus();
                return;
            }

            output.textContent = '요청 중... 기다려주세요.';

            try {
                const formData = new URLSearchParams();
                formData.append('token', token);

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formData.toString()
                });

                const data = await response.json();
                output.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                output.textContent = '통신 에러가 발생했습니다: ' + error.message;
            }
        }

        function processPending() {
            if (confirm('대기열에 있는 이름들을 등록하시겠습니까?')) {
                runApi('/api/process-pending.php');
            }
        }

        function drawWeekly() {
            if (confirm('다음 회차 당첨자를 추첨하시겠습니까?')) {
                runApi('/api/draw.php');
            }
        }
    </script>
</body>

</html>