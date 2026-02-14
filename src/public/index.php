<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <div class="container mx-auto p-4">
        <!-- Search/Add UI -->
        <div class="bg-white p-6 rounded shadow mb-6 sticky top-0 z-10">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">User Management</h1>
            <form id="user-form" class="flex gap-2">
                <input type="text" id="username" name="username" placeholder="이름을 입력하세요"
                    class="flex-1 p-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-6 rounded transition duration-200">
                    검색/등록
                </button>
            </form>
        </div>

        <!-- List Container -->
        <div id="list-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <!-- User cards will be injected here -->
        </div>

        <!-- Intersection Observer Sentinel -->
        <div id="sentinel" class="h-10 mt-4 flex justify-center items-center">
            <div class="loader hidden w-6 h-6 border-4 border-blue-500 border-t-transparent rounded-full animate-spin">
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const listContainer = document.getElementById('list-container');
            const userForm = document.getElementById('user-form');
            const usernameInput = document.getElementById('username');
            const sentinel = document.getElementById('sentinel');
            const loader = sentinel.querySelector('.loader');

            let page = 1;
            let isLoading = false;
            let hasMore = true;

            // --- 1. Search/Add Logic ---
            userForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const name = usernameInput.value.trim();
                if (!name) return;

                // Optimistic UI Update
                const tempId = 'temp-' + Date.now();
                const tempCard = createUserCard({ id: tempId, name: name, status: '등록 대기중...' }, true);
                listContainer.prepend(tempCard);

                usernameInput.value = ''; // Clear input

                try {
                    const formData = new FormData();
                    formData.append('name', name);

                    const response = await fetch('../api/add_user.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) throw new Error('Network response was not ok');

                    const result = await response.json();

                    // Update the temp card with real data or remove pending status
                    // The PHP returns { message: ..., data: { ... } }
                    const realCard = createUserCard(result.data || result, false);
                    document.getElementById(tempId).replaceWith(realCard);

                } catch (error) {
                    console.error('Error adding user:', error);
                    // Handle error (visually indicate failure)
                    const errorCard = document.getElementById(tempId);
                    if (errorCard) {
                        errorCard.classList.add('bg-red-100', 'border-red-500');
                        errorCard.querySelector('.status-text').textContent = '등록 실패';
                        errorCard.querySelector('.status-text').classList.replace('text-yellow-600', 'text-red-600');
                    }
                }
            });

            // --- 2. Infinite Scroll Logic ---
            const loadMoreUsers = async () => {
                if (isLoading || !hasMore) return;
                isLoading = true;
                loader.classList.remove('hidden');

                try {
                    // Assuming get_users.php accepts a query param for pagination, e.g., ?page= or ?offset=
                    const response = await fetch(`../api/get_users.php?page=${page}&limit=10`);

                    if (response.ok) {
                        const users = await response.json();

                        if (users && users.length > 0) {
                            users.forEach(user => {
                                listContainer.appendChild(createUserCard(user));
                            });
                            page++;
                        } else {
                            hasMore = false; // No more data
                            sentinel.innerHTML = '<p class="text-gray-500 text-sm">더 이상 사용자가 없습니다.</p>';
                            observer.unobserve(sentinel);
                        }
                    } else {
                        // If 404 or error, stop trying for now to avoid loops
                        console.warn('get_users.php failed or returned non-200');
                        hasMore = false;
                    }

                } catch (error) {
                    console.error('Error fetching users:', error);
                } finally {
                    isLoading = false;
                    loader.classList.add('hidden');
                }
            };

            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    loadMoreUsers();
                }
            }, { threshold: 0.1 });

            // Initial Observation
            observer.observe(sentinel);

            // --- Helper: Create User Card ---
            function createUserCard(user, isPending = false) {
                const div = document.createElement('div');
                div.id = user.id || `user-${Math.random()}`; // Fallback ID
                div.className = `bg-white p-4 rounded shadow border transition-all duration-300 ${isPending ? 'border-yellow-400 opacity-90' : 'border-gray-200 hover:shadow-md'}`;

                div.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-lg text-gray-800">${user.name || 'No Name'}</h3>
                            <p class="text-xs text-gray-500">ID: ${user.id || 'Checking...'}</p>
                        </div>
                        <span class="status-text text-sm font-semibold ${isPending ? 'text-yellow-600' : 'text-green-600'}">
                            ${isPending ? '등록 대기' : (user.status || 'Active')}
                        </span>
                    </div>
                `;
                return div;
            }
        });
    </script>
</body>

</html>