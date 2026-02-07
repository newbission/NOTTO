import { getFile, getFileWithSha, uploadFile, DATA_BRANCH } from "../utils.js";
import { generateLottoNumbers } from "../ai.js";

function getCurrentEpisode() {
    const BASE_DATE = new Date(2026, 0, 25);
    const BASE_EPISODE = 1209;
    const MS_PER_WEEK = 7 * 24 * 60 * 60 * 1000;
    const now = new Date();
    const kstNow = new Date(now.getTime() + 9 * 60 * 60 * 1000); // UTC to KST
    const weeks = Math.floor((kstNow - BASE_DATE) / MS_PER_WEEK);
    return BASE_EPISODE + weeks;
}

export default async function handler(req, res) {
    // CRON_SECRET을 통한 간단한 인증 (Vercel Cron 설정 시 헤더로 전달됨)
    // if (req.headers.authorization !== `Bearer ${process.env.CRON_SECRET}`) {
    //   return res.status(401).json({ error: 'Unauthorized' });
    // }
    // 일단은 공개 엔드포인트로 두되, GitHub Action에서 호출하도록 함.

    try {
        const currentEpisode = getCurrentEpisode();
        const episodePath = `episodes/${currentEpisode}.json`;

        const [registered, episodeDataRes] = await Promise.all([
            getFile("names/registered.json", DATA_BRANCH),
            getFileWithSha(episodePath, DATA_BRANCH).catch(() => null), // 파일 없으면 null
        ]);

        const episodeDataFile = episodeDataRes?.content;
        const currentSha = episodeDataRes?.sha;

        const episodeData = episodeDataFile || {};
        let updated = false;

        // 등록된 모든 이름에 대해 번호 확인
        if (registered) {
            // 순차 처리 말고 병렬 처리 (Gemini API Rate Limit 고려해야 함)
            // 무료 티어는 분당 요청 제한이 있으므로 청크(Chunk)로 나누거나 순차 처리 권장.
            // 여기서는 5개씩 병렬 처리.

            const CHUNK_SIZE = 5;
            for (let i = 0; i < registered.length; i += CHUNK_SIZE) {
                const chunk = registered.slice(i, i + CHUNK_SIZE);
                await Promise.all(chunk.map(async (name) => {
                    if (!episodeData[name]) {
                        console.log(`Generating numbers for ${name}...`);
                        episodeData[name] = await generateLottoNumbers(name);
                        updated = true;
                    }
                }));
                // Rate Limit 방지를 위한 지연 (필요 시)
                // await new Promise(r => setTimeout(r, 1000));
            }
        }

        if (updated) {
            await uploadFile(
                episodePath,
                episodeData,
                `Update episode ${currentEpisode} data`,
                DATA_BRANCH,
                currentSha
            );
            return res.status(200).json({ success: true, message: `Episode ${currentEpisode} updated` });
        } else {
            return res.status(200).json({ success: true, message: "No updates needed" });
        }

    } catch (error) {
        console.error("Weekly Cron Error:", error);
        return res.status(500).json({ error: error.message });
    }
}
