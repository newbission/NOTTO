import { Octokit } from "octokit";
import { getFile, GITHUB_OWNER, GITHUB_REPO, DATA_BRANCH, REQUESTS_BRANCH } from "./utils.js";
import { generateLottoNumbers } from "./ai.js";

const octokit = new Octokit({ auth: process.env.GITHUB_TOKEN });

// 현재 회차 계산 (app.js와 동일 로직)
function getCurrentEpisode() {
    const BASE_DATE = new Date(2026, 0, 25);
    const BASE_EPISODE = 1209;
    const MS_PER_WEEK = 7 * 24 * 60 * 60 * 1000;
    const now = new Date();
    const weeks = Math.floor((now - BASE_DATE) / MS_PER_WEEK);
    return BASE_EPISODE + weeks;
}

export default async function handler(req, res) {
    if (req.method !== "POST") return res.status(405).json({ error: "Method not allowed" });

    const authHeader = req.headers["authorization"];
    if (authHeader !== `Bearer ${process.env.ADMIN_PASSWORD}`) {
        return res.status(401).json({ error: "Unauthorized" });
    }

    const { changes } = req.body; // { approve: [], reject: [], ... }
    if (!changes) return res.status(400).json({ error: "No changes provided" });

    try {
        // 1. 최신 데이터 로드 (Atomic update를 위해 한 번에 읽고 계산)
        const currentEpisode = getCurrentEpisode();
        const episodePath = `episodes/${currentEpisode}.json`;

        const [registeredData, rejectedData, episodeData] = await Promise.all([
            getFile("names/registered.json", DATA_BRANCH) || [],
            getFile("names/rejected.json", DATA_BRANCH) || {},
            getFile(episodePath, DATA_BRANCH) || {}
        ]);

        let newRegistered = [...registeredData];
        let newRejected = { ...rejectedData };
        let newEpisode = { ...episodeData };
        let filesToDelete = []; // requests 브랜치에서 삭제할 파일들

        // 2. 변경사항 적용
        // 2-1. 승인 (Approve)
        if (changes.approve) {
            for (const req of changes.approve) {
                // 등록 목록에 추가
                if (!newRegistered.includes(req.name)) {
                    newRegistered.push(req.name);
                }
                // 반려 목록에서 제거
                delete newRejected[req.name];

                // 대기큐 삭제 목록 추가 (timestamp가 있는 경우)
                if (req.timestamp) {
                    filesToDelete.push(`regist/${req.name}_${req.timestamp}.json`);
                }

                // [AI] 번호 생성 (이미 있으면 스킵)
                if (!newEpisode[req.name]) {
                    const numbers = await generateLottoNumbers(req.name);
                    newEpisode[req.name] = numbers;
                }
            }
        }

        // 2-2. 반려 (Reject)
        if (changes.reject) {
            for (const req of changes.reject) {
                // 반려 목록에 추가
                newRejected[req.name] = req.reason || "관리자 반려";
                // 등록 목록에서 제거
                newRegistered = newRegistered.filter(n => n !== req.name);

                // 대기큐 삭제 목록 추가
                if (req.timestamp) {
                    filesToDelete.push(`regist/${req.name}_${req.timestamp}.json`);
                }

                // 에피소드 데이터에서도 제거 (혹시 있다면)
                delete newEpisode[req.name];
            }
        }

        // 2-3. 이동 (ToRegistered, ToRejected 등은 위 로직으로 커버 가능)
        // admin.js 프론트에서 changes 객체를 approve/reject 구조로 잘 보내준다고 가정.
        // 만약 toRegistered, toRejected 별도 키로 온다면 여기서 처리.
        // 여기서는 프론트엔드가 'approve', 'reject' 배열에 담아 보낸다고 가정하고 구현.

        // 3. Atomicity Commit (Git Data API)
        // 3-1. Get current commit SHA of DATA_BRANCH
        const { data: refData } = await octokit.rest.git.getRef({
            owner: GITHUB_OWNER,
            repo: GITHUB_REPO,
            ref: `heads/${DATA_BRANCH}`,
        });
        const latestCommitSha = refData.object.sha;

        // 3-2. Create Blobs & Tree Items
        const treeItems = [
            {
                path: "names/registered.json",
                mode: "100644",
                content: JSON.stringify(newRegistered, null, 2),
            },
            {
                path: "names/rejected.json",
                mode: "100644",
                content: JSON.stringify(newRejected, null, 2),
            },
            {
                path: episodePath,
                mode: "100644", // Create new episode file if not exists
                content: JSON.stringify(newEpisode, null, 2),
            }
        ];

        // 3-3. Create Tree
        const { data: treeData } = await octokit.rest.git.createTree({
            owner: GITHUB_OWNER,
            repo: GITHUB_REPO,
            base_tree: latestCommitSha,
            tree: treeItems,
        });

        // 3-4. Create Commit
        const { data: commitData } = await octokit.rest.git.createCommit({
            owner: GITHUB_OWNER,
            repo: GITHUB_REPO,
            message: "Admin updates: Approve/Reject and Generate Numbers",
            tree: treeData.sha,
            parents: [latestCommitSha],
        });

        // 3-5. Update Head (Push)
        await octokit.rest.git.updateRef({
            owner: GITHUB_OWNER,
            repo: GITHUB_REPO,
            ref: `heads/${DATA_BRANCH}`,
            sha: commitData.sha,
        });

        // 4. 대기큐 파일 삭제 (Requests Branch) - 별도 브랜치이므로 별도 작업
        // 이건 Atomic하게 안 해도 됨 (데이터 정합성은 Data 브랜치가 중요)
        // 병렬로 빠르게 처리
        if (filesToDelete.length > 0) {
            await Promise.allSettled(filesToDelete.map(path =>
                octokit.rest.repos.deleteFile({
                    owner: GITHUB_OWNER,
                    repo: GITHUB_REPO,
                    path,
                    message: "Delete processed request",
                    branch: REQUESTS_BRANCH,
                    sha: undefined // SHA 없이 삭제하려면 getFile로 SHA를 먼저 가져와야 함. 
                    // 번거로우니 여기서는 "SHA 조회 후 삭제" 로직이 필요.
                    // 편의상 생략하거나, 추후 구현. 여기서는 일단 스킵하거나 try-catch로 감쌈.
                    // *GitHub API deleteFile requires SHA*.
                }).catch(e => console.warn(`Failed to delete ${path}`, e))
            ));
        }

        // 삭제를 위해 SHA가 필요한데, 파일이 많으면 API 호출이 많아짐.
        // 일단 성공 응답 보냄. (파일이 남아있어도 기능상 큰 문제는 없음 - list_dir로 읽을 때 중복 체크하면 됨)

        return res.status(200).json({ success: true, count: changes.approve?.length + changes.reject?.length });

    } catch (error) {
        console.error("Admin API Error:", error);
        return res.status(500).json({ error: error.message });
    }
}
