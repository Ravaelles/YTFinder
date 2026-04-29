<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YTFinder</title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, system-ui, -apple-system, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #0f172a;
            color: #e2e8f0;
        }

        .layout {
            display: grid;
            grid-template-columns: 450px 1fr;
            height: 100vh;
        }

        .sidebar {
            background: linear-gradient(160deg, #0f172a 0%, #1e293b 55%, #334155 100%);
            color: #fff;
            padding: 24px;
            box-shadow: 8px 0 24px rgba(0, 0, 0, 0.3);
            overflow-y: auto;
            border-right: 1px solid #1e293b;
        }

        .hint {
            color: #94a3b8;
        }

        .scan-form {
            display: grid;
            gap: 8px;
            margin: 16px 0;
        }

        input,
        button {
            border: none;
            border-radius: 8px;
            padding: 10px 12px;
            font: inherit;
        }

        input {
            background: #1e293b;
            border: 1px solid #334155;
            color: #f1f5f9;
        }

        button {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #fff;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        button:disabled {
            opacity: 0.7;
            cursor: wait;
        }

        .channel-list {
            list-style: none;
            padding: 0;
            margin: 12px 0 0;
            display: grid;
            gap: 8px;
        }

        .channel-btn {
            width: 100%;
            text-align: left;
            background: #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease, transform 0.2s ease;
            color: #f1f5f9;
        }

        .channel-btn:hover {
            background: #334155;
            transform: translateY(-1px);
        }

        .content {
            padding: 12px 24px 24px;
            overflow-y: auto;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .actions-sticky {
            position: sticky;
            top: 0;
            z-index: 5;
            margin-bottom: 8px;
            padding: 8px 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(8px);
        }

        a {
            color: #60a5fa;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
        }

        th,
        td {
            text-align: left;
            background-color: #000000;
            padding: 12px;
            border-bottom: 1px solid #334155;
        }

        th {
            background: #334155;
            color: #f1f5f9;
        }

        /**
        tr:nth-child(even) td {
            background: #1e293b;
        }

        tr:nth-child(odd) td {
            background: #1a2335;
        }
        **/

        tr:hover td {
            background: #2d3748;
        }

        .sort-btn {
            background: transparent;
            box-shadow: none;
            color: inherit;
            border: none;
            padding: 0;
            font-weight: 600;
        }

        .sort-btn:hover {
            color: #60a5fa;
        }

        .plain-btn {
            background: #334155;
            color: #f1f5f9;
            border: 1px solid #475569;
            box-shadow: none;
        }

        .video-link {
            background: transparent;
            border: none;
            box-shadow: none;
            color: #60a5fa;
            padding: 0;
            font-weight: 600;
            text-align: left;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .video-link:hover {
            color: #93c5fd;
        }

        .error {
            color: #f87171;
        }
    </style>
</head>
<body x-data="ytFinder()">
    <main class="layout">
        <section class="sidebar">
            <h1>YTFinder</h1>
            <p class="hint">Scan once and browse channels locally.</p>

            <form class="scan-form" @submit.prevent="scanChannel">
                <input
                    x-model="channelUrl"
                    type="url"
                    required
                    placeholder="https://www.youtube.com/@YogaWithBird/videos"
                />
                <button :disabled="loading" x-text="loading ? 'Scanning...' : 'Scan Channel'"></button>
            </form>

            <template x-if="error"><p class="error" x-text="error"></p></template>

            <h2>Scanned Channels</h2>
            <ul class="channel-list">
                <template x-for="channel in channels" :key="channel.id">
                    <li>
                        <button class="channel-btn" @click="openChannel(channel)">
                            <span x-text="channel.display_name"></span>
                            <small x-text="(channel.videos_count || 0) + ' videos'"></small>
                        </button>
                    </li>
                </template>
            </ul>
        </section>

        <section class="content">
            <template x-if="selectedChannel">
                <div>
                    <header class="content-header">
                        <div>
                            <h2 x-text="selectedChannel.display_name"></h2>
                        </div>
                    </header>
                    <div class="header-actions actions-sticky">
                        <button class="plain-btn" :disabled="refreshing" @click="refreshChannel" x-text="refreshing ? 'Refreshing...' : 'Refresh'"></button>
                        <button class="plain-btn" @click="sortShortest()">Shortest</button>
                        <button class="plain-btn" @click="jumpToDuration(5)">~5min</button>
                        <button class="plain-btn" @click="jumpToDuration(10)">~10min</button>
                        <button class="plain-btn" @click="jumpToDuration(15)">~15min</button>
                        <button class="plain-btn" @click="sortNewest()">Newest</button>
                        <a :href="selectedChannel.source_url" target="_blank" rel="noreferrer">Open channel</a>
                    </div>

                    <p x-show="loadingVideos">Loading videos...</p>

                    <table x-show="!loadingVideos">
                        <thead>
                            <tr>
                                <th>
                                    <button class="sort-btn" @click="setSort('title')">
                                        Title <span x-text="sortIndicator('title')"></span>
                                    </button>
                                </th>
                                <th>
                                    <button class="sort-btn" @click="setSort('duration_sec')">
                                        Length <span x-text="sortIndicator('duration_sec')"></span>
                                    </button>
                                </th>
                                <th>
                                    <button class="sort-btn" @click="setSort('published_at')">
                                        Date <span x-text="sortIndicator('published_at')"></span>
                                    </button>
                                </th>
                                <th>
                                    <button class="sort-btn" @click="setSort('view_count')">
                                        Views <span x-text="sortIndicator('view_count')"></span>
                                    </button>
                                </th>
                                <th>
                                    <button class="sort-btn" @click="setSort('click_count')">
                                        Clicks <span x-text="sortIndicator('click_count')"></span>
                                    </button>
                                </th>
                                <th>Favorite</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="video in sortedVideos" :key="video.id">
                                <tr :id="'video-' + video.id">
                                    <td :style="videoCellStyle(video)">
                                        <button class="video-link" @click="openVideo(video)" x-text="video.title"></button>
                                    </td>
                                    <td :style="videoCellStyle(video)" x-text="video.durationLabel"></td>
                                    <td :style="videoCellStyle(video)" x-text="formatDate(video.published_at)"></td>
                                    <td :style="videoCellStyle(video)" x-text="video.view_count.toLocaleString()"></td>
                                    <td :style="videoCellStyle(video)" x-text="video.click_count"></td>
                                    <td :style="videoCellStyle(video)">
                                        <button class="plain-btn" @click="toggleFavorite(video)" x-text="video.is_favorite ? '★' : '☆'"></button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <template x-if="!selectedChannel">
                <p>Select a channel to view videos.</p>
            </template>
        </section>
    </main>

    <script>
        function ytFinder() {
            return {
                channelUrl: '',
                channels: [],
                videos: [],
                selectedChannel: null,
                loading: false,
                loadingVideos: false,
                refreshing: false,
                error: '',
                sortField: 'duration_sec',
                sortDirection: 'asc',
                highlightRules: [
                    // Dark mode
                    // { keywords: ["stress", "anxiety", "relax"], color: "#fb923c" },
                    // { keywords: ["morning"], color: "#38bdf8" },
                    // { keywords: ["evening", "bed"], color: "#818cf8" },
                    // { keywords: ["beginner"], color: "#4ade80" },
                    // { keywords: ["everyday"], color: "#facc15" },
                    // { keywords: ["stretch", "advanced"], color: "#f472b6" }
                    // Light mode - regular
                    { keywords: ["stress", "anxiety", "relax"], color: "#FF7A00" },
                    { keywords: ["gentle"], color: "#00C853" },
                    { keywords: ["morning"], color: "#00A3FF" },
                    { keywords: ["evening", "bed"], color: "#001E9A" },
                    { keywords: ["everyday"], color: "#FFD400" },
                    { keywords: ["stretch", "advanced"], color: "#FF2D96" }
                ],

                async init() {
                    await this.fetchChannels();
                },

                async fetchChannels() {
                    const res = await fetch('/api/channels');
                    this.channels = await res.json();
                    if (!this.selectedChannel && this.channels.length > 0) {
                        await this.openChannel(this.channels[0]);
                    }
                },

                async scanChannel() {
                    this.error = '';
                    this.loading = true;
                    try {
                        const res = await fetch('/api/channels/scan', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ url: this.channelUrl.trim() })
                        });

                        if (!res.ok) {
                            const payload = await res.json();
                            throw new Error(payload.error || 'Failed to scan channel');
                        }

                        this.channelUrl = '';
                        await this.fetchChannels();
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async openChannel(channel) {
                    this.selectedChannel = channel;
                    this.loadingVideos = true;
                    const res = await fetch(`/api/channels/${channel.id}/videos`);
                    this.videos = await res.json();
                    this.loadingVideos = false;
                },

                async refreshChannel() {
                    if (!this.selectedChannel) return;
                    this.error = '';
                    this.refreshing = true;
                    try {
                        const res = await fetch(`/api/channels/${this.selectedChannel.id}/refresh`, {
                            method: 'POST'
                        });
                        if (!res.ok) {
                            const payload = await res.json();
                            throw new Error(payload.error || 'Failed to refresh channel');
                        }
                        await this.fetchChannels();
                        await this.openChannel(this.selectedChannel);
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.refreshing = false;
                    }
                },

                async toggleFavorite(video) {
                    const next = !video.is_favorite;
                    const res = await fetch(`/api/videos/${video.id}/favorite`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ isFavorite: next })
                    });

                    if (res.ok) {
                        video.is_favorite = next;
                    }
                },

                async openVideo(video) {
                    window.open(video.video_url, '_blank', 'noopener,noreferrer');
                    const res = await fetch(`/api/videos/${video.id}/click`, { method: 'POST' });
                    if (res.ok) {
                        video.click_count += 1;
                    }
                },

                formatDate(dateString) {
                    return new Date(dateString).toLocaleDateString();
                },

                get sortedVideos() {
                    let sorted = this.videos.filter(video => video.duration_sec >= 61);
                    sorted.sort((a, b) => {
                        let left, right;
                        switch (this.sortField) {
                            case 'title':
                                left = a.title.toLowerCase();
                                right = b.title.toLowerCase();
                                break;
                            case 'duration_sec':
                                left = a.duration_sec;
                                right = b.duration_sec;
                                break;
                            case 'published_at':
                                left = new Date(a.published_at).getTime();
                                right = new Date(b.published_at).getTime();
                                break;
                            case 'view_count':
                                left = a.view_count;
                                right = b.view_count;
                                break;
                            case 'click_count':
                                left = a.click_count;
                                right = b.click_count;
                                break;
                        }

                        if (left < right) return this.sortDirection === 'asc' ? -1 : 1;
                        if (left > right) return this.sortDirection === 'asc' ? 1 : -1;
                        return 0;
                    });
                    return sorted;
                },

                setSort(field) {
                    if (this.sortField === field) {
                        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                        return;
                    }
                    this.sortField = field;
                    this.sortDirection = field === 'title' ? 'asc' : 'desc';
                },

                sortIndicator(field) {
                    if (this.sortField !== field) return '';
                    return this.sortDirection === 'asc' ? '▲' : '▼';
                },

                sortShortest() {
                    this.sortField = 'duration_sec';
                    this.sortDirection = 'asc';
                },

                sortNewest() {
                    this.sortField = 'published_at';
                    this.sortDirection = 'desc';
                },

                jumpToDuration(thresholdMinutes) {
                    const thresholdSeconds = thresholdMinutes * 60;
                    const target = this.sortedVideos.find(video => video.duration_sec > thresholdSeconds);
                    if (!target) return;

                    const el = document.getElementById('video-' + target.id);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                },

                hexToRgba(hex, alpha) {
                    const clean = hex.replace('#', '');
                    const normalized = clean.length === 3 ? clean.split('').map(c => `${c}${c}`).join('') : clean;
                    const value = parseInt(normalized, 16);
                    const r = (value >> 16) & 255;
                    const g = (value >> 8) & 255;
                    const b = value & 255;
                    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                },

                videoCellStyle(video) {
                    const title = video.title.toLowerCase();
                    const match = this.highlightRules.find(rule => rule.keywords.some(keyword => title.includes(keyword)));
                    if (!match) return {};
                    return {
                        backgroundColor: this.hexToRgba(match.color, 0.5)
                    };
                }
            }
        }
    </script>
</body>
</html>
