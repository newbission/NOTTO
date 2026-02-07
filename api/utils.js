import { Octokit } from "octokit";
import dotenv from "dotenv";

dotenv.config();

export const GITHUB_OWNER = "newbission";
export const GITHUB_REPO = "NOTTO";
export const DATA_BRANCH = "data";
export const REQUESTS_BRANCH = "requests";

// Vercel 환경에서는 process.env.GITHUB_TOKEN 사용
// 로컬 테스트 시 .env 파일 필요
const octokit = new Octokit({
  auth: process.env.GITHUB_TOKEN,
});

/**
 * GitHub에서 파일 내용과 SHA 읽기
 * @param {string} path 파일 경로
 * @param {string} branch 브랜치 이름
 * @returns {Promise<{content: any, sha: string}|null>} 데이터와 SHA 또는 null
 */
export async function getFileWithSha(path, branch = DATA_BRANCH) {
  try {
    const { data } = await octokit.rest.repos.getContent({
      owner: GITHUB_OWNER,
      repo: GITHUB_REPO,
      path,
      ref: branch,
    });

    if (Array.isArray(data)) return null; // 디렉토리인 경우
    if (!data.content) return null;

    const content = Buffer.from(data.content, "base64").toString("utf-8");
    return {
      content: JSON.parse(content),
      sha: data.sha,
    };
  } catch (error) {
    if (error.status === 404) return null;
    throw error;
  }
}

/**
 * GitHub에서 파일 내용 읽기
 * @param {string} path 파일 경로
 * @param {string} branch 브랜치 이름
 * @returns {Promise<any|null>} JSON 파싱된 데이터 또는 null
 */
export async function getFile(path, branch = DATA_BRANCH) {
  const result = await getFileWithSha(path, branch);
  return result ? result.content : null;
}

/**
 * GitHub에 파일 업로드 (생성 또는 수정)
 * @param {string} path 파일 경로
 * @param {any} content 저장할 데이터 (JSON)
 * @param {string} message 커밋 메시지
 * @param {string} branch 브랜치 이름
 * @param {string} [sha] 기존 파일 SHA (수정 시 필요)
 */
export async function uploadFile(path, content, message, branch = DATA_BRANCH, sha = undefined) {
  const contentStr = JSON.stringify(content, null, 2);
  const contentBase64 = Buffer.from(contentStr).toString("base64");

  await octokit.rest.repos.createOrUpdateFileContents({
    owner: GITHUB_OWNER,
    repo: GITHUB_REPO,
    path,
    message,
    content: contentBase64,
    branch,
    sha,
  });
}

export { octokit };
