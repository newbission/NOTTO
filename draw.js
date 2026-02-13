const { GoogleGenerativeAI, SchemaType } = require("@google/generative-ai");

// Configuration
const API_URL = process.env.API_URL || "https://notto.dothome.co.kr"; // Default or Env
const GEMINI_API_KEY = process.env.GEMINI_API_KEY;
const IS_MOCK = process.env.MOCK === 'true';

// Mock Data Generators
const mockGetUsers = () => {
    return Array.from({ length: 250 }, (_, i) => ({
        id: i + 1,
        name: `User${i + 1}`,
        status: 'pending'
    }));
};

const mockUpdateNumbers = async (data) => {
    console.log(`[MOCK] Sending update for ${data.length} users.`);
    // console.log(JSON.stringify(data, null, 2));
    return { success: true, count: data.length };
};

// Real API Interactions
const fetchPendingUsers = async () => {
    if (IS_MOCK) return mockGetUsers();

    console.log(`Fetching users from ${API_URL}/get_users.php?status=pending...`);
    try {
        const response = await fetch(`${API_URL}/get_users.php?status=pending`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const data = await response.json();
        // Ensure data is array
        return Array.isArray(data) ? data : (data.users || []);
    } catch (e) {
        console.error("Failed to fetch users:", e);
        process.exit(1);
    }
};

const sendResults = async (results) => {
    if (IS_MOCK) return mockUpdateNumbers(results);

    console.log(`Sending results to ${API_URL}/update_numbers.php...`);
    try {
        const response = await fetch(`${API_URL}/update_numbers.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(results)
        });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const text = await response.text();
        console.log("Update response:", text);
    } catch (e) {
        console.error("Failed to update numbers:", e);
    }
};

// Utilities
const chunkArray = (array, size) => {
    const chunked = [];
    for (let i = 0; i < array.length; i += size) {
        chunked.push(array.slice(i, i + size));
    }
    return chunked;
};

const generateNumbersForChunk = async (genAI, chunk) => {
    const names = chunk.map(u => u.name);

    const prompt = `
    Generate 6 unique lucky lottery numbers (integers 1-45) for each of the following users.
    Output ONLY a JSON array of objects, strictly following this schema:
    [{"name": "UserName", "numbers": [1, 2, 3, 4, 5, 6]}]
    
    Users: ${JSON.stringify(names)}
    `;

    try {
        // Use Gemini 1.5 Flash for speed/cost, or Pro for quality.
        const model = genAI.getGenerativeModel({
            model: "gemini-1.5-flash",
            generationConfig: {
                responseMimeType: "application/json",
                responseSchema: {
                    type: SchemaType.ARRAY,
                    items: {
                        type: SchemaType.OBJECT,
                        properties: {
                            name: { type: SchemaType.STRING },
                            numbers: {
                                type: SchemaType.ARRAY,
                                items: { type: SchemaType.INTEGER }
                            }
                        },
                        required: ["name", "numbers"]
                    }
                }
            }
        });

        const result = await model.generateContent(prompt);
        const responseText = result.response.text();
        const parsed = JSON.parse(responseText);

        // Map back to include user IDs if needed, though name matching is used here.
        // Better to include ID in prompt? User names might not be unique.
        // For this task, assuming names are unique or mapping back by index if order preserved.
        // GenAI might shuffle, so name matching is safer.
        return parsed.map(item => {
            const originalUser = chunk.find(u => u.name === item.name);
            return {
                id: originalUser ? originalUser.id : null,
                name: item.name,
                numbers: item.numbers
            };
        }).filter(item => item.id !== null);

    } catch (error) {
        console.error("Error generating numbers with Gemini:", error);
        // Fallback or retry logic could go here
        return [];
    }
};

async function main() {
    if (!IS_MOCK && !GEMINI_API_KEY) {
        console.error("GEMINI_API_KEY is missing!");
        process.exit(1);
    }

    const genAI = new GoogleGenerativeAI(GEMINI_API_KEY || "mock-key");

    // 1. Fetch Users
    const users = await fetchPendingUsers();
    console.log(`Fetched ${users.length} pending users.`);

    if (users.length === 0) {
        console.log("No users to process.");
        return;
    }

    // 2. Chunk
    const chunks = chunkArray(users, 100);
    console.log(`Split into ${chunks.length} chunks.`);

    let allResults = [];

    // 3. Process Chunks
    for (let i = 0; i < chunks.length; i++) {
        console.log(`Processing chunk ${i + 1}/${chunks.length}...`);

        if (IS_MOCK) {
            // Mock Gemini response
            const mockResults = chunks[i].map(u => ({
                id: u.id,
                name: u.name,
                numbers: [1, 2, 3, 4, 5, 6].map(n => n + Math.floor(Math.random() * 30))
            }));
            allResults = allResults.concat(mockResults);
        } else {
            const chunkResults = await generateNumbersForChunk(genAI, chunks[i]);
            allResults = allResults.concat(chunkResults);
            // Rate limit protection if needed
            // await new Promise(r => setTimeout(r, 1000));
        }
    }

    // 4. Send Results
    console.log(`Generated numbers for ${allResults.length} users.`);
    await sendResults(allResults);
    console.log("Done.");
}

main();
