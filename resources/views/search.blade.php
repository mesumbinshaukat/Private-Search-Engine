<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Search Engine | Category Specific Results</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg-dark: #0f172a;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-muted: #94a3b8;
            --text-main: #f1f5f9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            overflow-x: hidden;
            background: radial-gradient(circle at 50% 0%, #1e293b 0%, #0f172a 100%);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .hero {
            text-align: center;
            margin-top: 10vh;
            margin-bottom: 3rem;
        }

        .logo {
            font-size: 3.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            letter-spacing: -0.05em;
            animation: fadeInDown 0.8s ease-out;
        }

        .hero p {
            color: var(--text-muted);
            font-size: 1.1rem;
            font-weight: 300;
            animation: fadeInUp 0.8s ease-out 0.2s backwards;
        }

        .search-wrapper {
            width: 100%;
            position: relative;
            animation: fadeInUp 0.8s ease-out 0.4s backwards;
        }

        .search-bar-container {
            position: relative;
            width: 100%;
            border-radius: 20px;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            padding: 0.5rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .search-bar-container:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 20px var(--primary-glow);
            transform: scale(1.01);
        }

        .search-icon {
            padding: 0 1rem;
            color: var(--text-muted);
        }

        input#search-input {
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 1.25rem;
            padding: 1rem 0.5rem;
            flex-grow: 1;
            font-weight: 300;
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .category-tabs {
            margin-top: 1.5rem;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
            animation: fadeInUp 0.8s ease-out 0.6s backwards;
        }

        .tab {
            padding: 0.6rem 1.2rem;
            border-radius: 100px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-muted);
        }

        .tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .tab:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
        }

        /* Results Area */
        #results-container {
            width: 100%;
            margin-top: 3rem;
            display: none;
        }

        .result-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            animation: slideIn 0.5s ease-out;
        }

        .result-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateX(10px);
            background: rgba(30, 41, 59, 0.8);
        }

        .result-card h3 {
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .result-card h3 a {
            color: #818cf8;
            text-decoration: none;
        }

        .result-card .url {
            font-size: 0.85rem;
            color: #10b981;
            margin-bottom: 0.75rem;
            display: block;
        }

        .result-card .description {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .meta-info {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .badge {
            background: rgba(99, 102, 241, 0.1);
            color: #818cf8;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Loading */
        .loading-spinner {
            display: none;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Mode Shift */
        .search-active .hero {
            margin-top: 2vh;
            transform: scale(0.8);
            margin-bottom: 1rem;
        }

        .search-active #results-container {
            display: block;
        }

        .stats-bar {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            display: flex;
            gap: 1.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            background: var(--glass-bg);
            padding: 0.75rem 1.5rem;
            border-radius: 100px;
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(8px);
        }

        .dot {
            height: 8px;
            width: 8px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            box-shadow: 0 0 10px #10b981;
        }
    </style>
</head>
<body>
    <div class="container" id="app-container">
        <div class="hero">
            <h1 class="logo">Search.io</h1>
            <p>Private, Category-Specific Web Exploration</p>
        </div>

        <div class="search-wrapper">
            <div class="search-bar-container">
                <div class="search-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </div>
                <input type="text" id="search-input" placeholder="Explore journals, news, and insights..." autocomplete="off">
                <button class="search-btn" id="search-button">
                    <span>Search</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
            </div>

            <div class="category-tabs" id="category-tabs">
                <div class="tab active" data-category="all">All Categories</div>
                <div class="tab" data-category="technology">Technology</div>
                <div class="tab" data-category="business">Business</div>
                <div class="tab" data-category="ai">AI</div>
                <div class="tab" data-category="sports">Sports</div>
                <div class="tab" data-category="politics">Politics</div>
            </div>
        </div>

        <div class="loading-spinner" id="spinner"></div>

        <div id="results-container">
            <!-- Results will be injected here -->
        </div>
    </div>

    <div class="stats-bar">
        <span><span class="dot"></span> System Live</span>
        <span id="total-records">Records: --</span>
        <span id="last-update">Last Sync: --</span>
        <button id="refresh-crawler-btn" style="display: none; background: rgba(255,255,255,0.1); border: 1px solid var(--glass-border); color: var(--text-muted); padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.7rem; cursor: pointer; transition: all 0.2s ease;">
            ↻ Refresh Crawler
        </button>
    </div>

    <div id="login-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--glass-bg); border: 1px solid var(--glass-border); padding: 2.5rem; border-radius: 20px; width: 100%; max-width: 400px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
            <h2 style="margin-bottom: 0.5rem; font-size: 1.5rem; font-weight: 700;">Secure Access</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem;">Please authenticate to explore the search engine.</p>
            <form id="login-form">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem;">Email Address</label>
                    <input type="email" id="login-email" required style="width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); border-radius: 10px; padding: 0.75rem; color: white; outline: none;">
                </div>
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.5rem;">Password</label>
                    <input type="password" id="login-password" required style="width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); border-radius: 10px; padding: 0.75rem; color: white; outline: none;">
                </div>
                <button type="submit" style="width: 100%; background: var(--primary); color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">
                    Authorize Access
                </button>
                <p id="login-error" style="color: #ef4444; font-size: 0.8rem; margin-top: 1rem; text-align: center; display: none;"></p>
            </form>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('search-input');
        const searchButton = document.getElementById('search-button');
        const resultsContainer = document.getElementById('results-container');
        const appContainer = document.getElementById('app-container');
        const spinner = document.getElementById('spinner');
        const categoryTabs = document.querySelectorAll('.tab');
        const loginModal = document.getElementById('login-modal');
        const loginForm = document.getElementById('login-form');
        const loginError = document.getElementById('login-error');
        const refreshCrawlerBtn = document.getElementById('refresh-crawler-btn');
        
        let currentCategory = 'all';

        // Auth Check
        function checkAuth() {
            const token = localStorage.getItem('auth_token');
            if (!token) {
                loginModal.style.display = 'flex';
                return false;
            }
            return true;
        }

        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            loginError.style.display = 'none';

            try {
                const response = await fetch('/api/v1/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: document.getElementById('login-email').value,
                        password: document.getElementById('login-password').value
                    })
                });

                const data = await response.json();
                if (response.ok) {
                    localStorage.setItem('auth_token', data.access_token);
                    loginModal.style.display = 'none';
                    fetchStats();
                } else {
                    loginError.textContent = data.message || 'Authentication failed';
                    loginError.style.display = 'block';
                }
            } catch (err) {
                loginError.textContent = 'Connection error. Try again.';
                loginError.style.display = 'block';
            }
        });

        // Update stats
        async function fetchStats() {
            if (!localStorage.getItem('auth_token')) return;

            try {
                const response = await fetch('/api/v1/stats', {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('auth_token')}` }
                });
                const result = await response.json();
                if (result.status === 'success') {
                    document.getElementById('total-records').textContent = `Records: ${result.data.index.total_records}`;
                    const date = new Date(result.data.index.last_generated);
                    document.getElementById('last-update').textContent = `Last Sync: ${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}`;
                }
            } catch (e) {}
        }

        if (checkAuth()) {
            fetchStats();
            refreshCrawlerBtn.style.display = 'inline-block';
        }

        refreshCrawlerBtn.addEventListener('click', async () => {
            if (!confirm('This will trigger a full crawl/index cycle in the background. Proceed?')) return;
            
            const originalText = refreshCrawlerBtn.textContent;
            refreshCrawlerBtn.textContent = 'Triggering...';
            refreshCrawlerBtn.disabled = true;

            try {
                const response = await fetch('/api/v1/trigger-refresh', {
                    method: 'POST',
                    headers: { 
                        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await response.json();
                if (response.ok) {
                    alert('✓ Refresh process started. It will run in background chunks.');
                } else {
                    alert('! Failed: ' + (data.message || 'Unknown error'));
                }
            } catch (err) {
                alert('! Connection error triggering refresh.');
            } finally {
                refreshCrawlerBtn.textContent = originalText;
                refreshCrawlerBtn.disabled = false;
            }
        });

        categoryTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                categoryTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                currentCategory = tab.dataset.category;
                if (searchInput.value.trim()) {
                    performSearch();
                }
            });
        });

        function renderResults(results) {
            resultsContainer.innerHTML = results.map(item => {
                let scoreColor = '#10b981'; // Green (8-10)
                if (item.match_score < 4) scoreColor = '#ef4444'; // Red (1-3)
                else if (item.match_score < 8) scoreColor = '#f59e0b'; // Yellow (4-7)

                const description = item.highlighted_description || item.description || 'No description available.';

                return `
                <div class="result-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <span class="url">${item.url}</span>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            ${item.confidence ? `<span style="font-size: 0.75rem; color: var(--text-muted);">Conf: ${(item.confidence * 100).toFixed(0)}%</span>` : ''}
                            <span class="badge" style="background: ${scoreColor}20; color: ${scoreColor}; border: 1px solid ${scoreColor}40;">
                                Match: ${item.match_score || '0'}/10
                            </span>
                        </div>
                    </div>
                    <h3><a href="${item.url}" target="_blank">${item.title}</a></h3>
                    <p class="description">${description}</p>
                    <div class="meta-info">
                        <span class="badge">${item.category}</span>
                        <span>Published ${item.published_at ? new Date(item.published_at).toLocaleDateString() : 'N/A'}</span>
                        <span style="margin-left: auto; font-size: 0.75rem; color: var(--text-muted);">Rel: ${item.relevance_score?.toFixed(2) || '0'}</span>
                    </div>
                </div>
                `;
            }).join('');
        }

        async function performSearch() {
            if (!checkAuth()) return;

            const query = searchInput.value.trim();
            if (!query) return;

            appContainer.classList.add('search-active');
            resultsContainer.innerHTML = '';
            spinner.style.display = 'block';

            try {
                const response = await fetch(`/api/v1/search?q=${encodeURIComponent(query)}&category=${currentCategory}`, {
                    headers: { 'Authorization': `Bearer ${localStorage.getItem('auth_token')}` }
                });
                const data = await response.json();

                spinner.style.display = 'none';

                if (response.status === 401) {
                    localStorage.removeItem('auth_token');
                    loginModal.style.display = 'flex';
                    return;
                }

                if (data.status === 'success' && data.data.results.length > 0) {
                    renderResults(data.data.results);
                } else {
                    let suggestionHtml = '';
                    if (data.error && data.error.query_suggestions && data.error.query_suggestions.length > 0) {
                        suggestionHtml = `
                            <div style="margin-top: 1rem; font-size: 0.875rem;">
                                Did you mean: ${data.error.query_suggestions.map(s => `<a href="#" onclick="searchInput.value='${s}'; performSearch(); return false;" style="color: var(--primary); text-decoration: underline; margin-right: 0.5rem;">${s}</a>`).join('')}
                            </div>
                        `;
                    }

                    resultsContainer.innerHTML = `
                        <div style="text-align: center; color: var(--text-muted); margin-top: 4rem;">
                            <p>No results found for "${query}" in ${currentCategory === 'all' ? 'all categories' : currentCategory}</p>
                            ${suggestionHtml}
                        </div>
                    `;
                }
            } catch (err) {
                spinner.style.display = 'none';
                resultsContainer.innerHTML = '<p style="color: #ef4444; text-align: center;">Connectivity error. Please try again.</p>';
            }
        }

        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') performSearch();
        });
    </script>
</body>
</html>
