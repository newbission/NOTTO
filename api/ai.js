import { GoogleGenerativeAI } from "@google/generative-ai";

const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);

export async function generateLottoNumbers(name) {
    try {
        const model = genAI.getGenerativeModel({ model: "gemini-1.5-flash" });

        const prompt = `
      당신은 운명의 숫자를 점지해주는 AI 도사입니다.
      '${name}'라는 사람의 이름에서 느껴지는 기운과 에너지를 분석해서,
      로또 번호(1~45 사이의 숫자) 6개를 추천해주세요.
      
      조건:
      1. 1부터 45까지의 숫자 중 서로 다른 6개를 고르세요.
      2. 출력 형식은 오직 JSON 배렬 형태여야 합니다. 예: [1, 7, 23, 33, 41, 44]
      3. 다른 미사여구는 절대 붙이지 마세요.
    `;

        const result = await model.generateContent(prompt);
        const response = await result.response;
        const text = response.text();

        // JSON 파싱 (혹시 모를 마크다운 제거)
        const jsonStr = text.replace(/```json/g, "").replace(/```/g, "").trim();
        const numbers = JSON.parse(jsonStr);

        // 검증
        if (!Array.isArray(numbers) || numbers.length !== 6) {
            throw new Error("Invalid format received from AI");
        }

        // 범위 검증 및 정렬
        const validNumbers = numbers
            .map(n => Number(n))
            .filter(n => n >= 1 && n <= 45); // 범위 필터링

        // 중복 제거 및 부족하면 채우기
        const uniqueNumbers = [...new Set(validNumbers)];

        while (uniqueNumbers.length < 6) {
            const r = Math.floor(Math.random() * 45) + 1;
            if (!uniqueNumbers.includes(r)) uniqueNumbers.push(r);
        }

        // 최종 정렬
        return uniqueNumbers.sort((a, b) => a - b).slice(0, 6);

    } catch (error) {
        console.error("AI Generation Error:", error);
        // AI 실패 시 랜덤 반환 (Fallback)
        const fallback = [];
        while (fallback.length < 6) {
            const r = Math.floor(Math.random() * 45) + 1;
            if (!fallback.includes(r)) fallback.push(r);
        }
        return fallback.sort((a, b) => a - b);
    }
}
