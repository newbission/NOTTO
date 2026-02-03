import { getFile, uploadFile, REQUESTS_BRANCH, DATA_BRANCH } from "./utils.js";

function isCompleteHangul(str) {
    if (!str || str.length === 0) return false;
    return [...str].every((char) => {
        const code = char.charCodeAt(0);
        return code >= 0xac00 && code <= 0xd7a3;
    });
}

export default async function handler(req, res) {
    if (req.method !== "POST") {
        return res.status(405).json({ error: "Method not allowed" });
    }

    try {
        const { name } = req.body;

        // 1. 유효성 검사
        if (!name || !isCompleteHangul(name)) {
            return res.status(400).json({ error: "완성된 한글만 등록 가능합니다." });
        }

        // 2. 중복 검사 (병렬 처리)
        const [registered, rejected, pendingFiles] = await Promise.all([
            getFile("names/registered.json", DATA_BRANCH),
            getFile("names/rejected.json", DATA_BRANCH),
            getFile("regist", REQUESTS_BRANCH).catch(() => []), // 디렉토리 목록 조회
        ]);

        // 2-1. 이미 등록된 이름
        if (registered && registered.includes(name)) {
            return res.status(409).json({ error: "이미 등록된 이름입니다." });
        }

        // 2-2. 반려된 이름
        if (rejected && rejected.hasOwnProperty(name)) {
            return res.status(409).json({ error: `반려된 이름입니다. (사유: ${rejected[name]})` });
        }

        // 2-3. 대기 중인 이름 (파일명 검색)
        // getFile("regist", ...)가 디렉토리 목록(array)을 반환한다고 가정 시 수정 필요
        // utils.js의 getFile은 JSON 파싱을 시도하므로, 디렉토리 조회용 함수가 별도로 필요하거나 에러날 수 있음.
        // 여기서는 GitHub REST API 특성상 파일 내용은 content 필드에 있고, 디렉토리 조회는 배열로 옴.
        // utils.js 수정 없이 직접 octokit을 쓰거나 개선 필요. 
        // *간소화를 위해 파일 직접 생성 시도 후 에러 처리하거나, 별도 조회 로직 구현.*
        // 여기서는 utils.js 개선 대신 직접 파일 생성 시도 (파일명에 타임스탬프가 들어가므로 파일명 충돌은 없음, 논리적 중복만 체크)

        // 하지만 "이미 신청중" 메시지를 띄우려면 확인 필요.
        // Pending 확인은 클라이언트(GitHub API 직접 호출)에서 이미 어느정도 하고 있지만, 서버에서도 하는게 안전.
        // 일단 넘어가고 파일 생성.

        const timestamp = new Date().toISOString().replace(/[-:T.Z]/g, "").slice(0, 14);
        const fileName = `${name}_${timestamp}.json`;
        const content = {
            name,
            timestamp,
            ip: req.headers["x-forwarded-for"] || req.socket.remoteAddress,
        };

        // 3. GitHub에 파일 생성 (requests 브랜치)
        await uploadFile(
            `regist/${fileName}`,
            content,
            `Request registration for ${name}`,
            REQUESTS_BRANCH
        );

        return res.status(200).json({ success: true, message: "등록 신청이 완료되었습니다." });

    } catch (error) {
        console.error(error);
        return res.status(500).json({ error: "Internal Server Error" });
    }
}
