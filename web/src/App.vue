<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from "vue";
import { VIDEO_HIGHLIGHT_RULES } from "./videoHighlightRules";

type Channel = {
  id: number;
  handle: string;
  displayName: string;
  sourceUrl: string;
  lastScannedAt: string;
  _count?: { videos: number };
};

type Video = {
  id: number;
  title: string;
  durationLabel: string;
  publishedAt: string;
  viewCount: number;
  isFavorite: boolean;
  clickCount: number;
  videoUrl: string;
};

type SortField = "title" | "durationSec" | "publishedAt" | "viewCount" | "clickCount";
type SortDirection = "asc" | "desc";

const apiBase = "http://localhost:3001/api";

const channelUrl = ref("");
const channels = ref<Channel[]>([]);
const videos = ref<Video[]>([]);
const selectedChannel = ref<Channel | null>(null);
const loading = ref(false);
const loadingVideos = ref(false);
const refreshing = ref(false);
const error = ref("");
const sortField = ref<SortField>("durationSec");
const sortDirection = ref<SortDirection>("asc");
const videoRowRefs = ref<Record<number, HTMLTableRowElement>>({});

const sortedVideos = computed(() => {
  const sorted = videos.value.filter((video) => parseDurationLabel(video.durationLabel) >= 61);
  sorted.sort((a, b) => {
    let left: string | number;
    let right: string | number;

    switch (sortField.value) {
      case "title":
        left = a.title.toLowerCase();
        right = b.title.toLowerCase();
        break;
      case "durationSec":
        left = parseDurationLabel(a.durationLabel);
        right = parseDurationLabel(b.durationLabel);
        break;
      case "publishedAt":
        left = new Date(a.publishedAt).getTime();
        right = new Date(b.publishedAt).getTime();
        break;
      case "viewCount":
        left = a.viewCount;
        right = b.viewCount;
        break;
      case "clickCount":
        left = a.clickCount;
        right = b.clickCount;
        break;
    }

    if (left < right) return sortDirection.value === "asc" ? -1 : 1;
    if (left > right) return sortDirection.value === "asc" ? 1 : -1;
    return 0;
  });
  return sorted;
});

async function fetchChannels() {
  const res = await fetch(`${apiBase}/channels`);
  channels.value = await res.json();
  if (!selectedChannel.value && channels.value.length > 0) {
    await openChannel(channels.value[0]);
  }
}

async function scanChannel() {
  error.value = "";
  loading.value = true;
  try {
    const res = await fetch(`${apiBase}/channels/scan`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ url: channelUrl.value.trim() })
    });

    if (!res.ok) {
      const payload = await res.json();
      throw new Error(payload.error || "Failed to scan channel");
    }

    channelUrl.value = "";
    await fetchChannels();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to scan channel";
  } finally {
    loading.value = false;
  }
}

async function openChannel(channel: Channel) {
  selectedChannel.value = channel;
  loadingVideos.value = true;
  const res = await fetch(`${apiBase}/channels/${channel.id}/videos`);
  videos.value = await res.json();
  loadingVideos.value = false;
}

async function refreshChannel() {
  if (!selectedChannel.value) return;
  error.value = "";
  refreshing.value = true;
  try {
    const res = await fetch(`${apiBase}/channels/${selectedChannel.value.id}/refresh`, {
      method: "POST"
    });
    if (!res.ok) {
      const payload = await res.json();
      throw new Error(payload.error || "Failed to refresh channel");
    }
    await fetchChannels();
    await openChannel(selectedChannel.value);
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to refresh channel";
  } finally {
    refreshing.value = false;
  }
}

async function toggleFavorite(video: Video) {
  const next = !video.isFavorite;
  const res = await fetch(`${apiBase}/videos/${video.id}/favorite`, {
    method: "PATCH",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ isFavorite: next })
  });

  if (res.ok) {
    video.isFavorite = next;
  }
}

async function openVideo(video: Video) {
  window.open(video.videoUrl, "_blank", "noopener,noreferrer");
  const res = await fetch(`${apiBase}/videos/${video.id}/click`, { method: "POST" });
  if (res.ok) {
    video.clickCount += 1;
  }
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString();
}

function parseDurationLabel(durationLabel: string) {
  const parts = durationLabel.split(":").map((part) => Number(part));
  if (parts.length === 2) {
    return parts[0] * 60 + parts[1];
  }
  if (parts.length === 3) {
    return parts[0] * 3600 + parts[1] * 60 + parts[2];
  }
  return 0;
}

function hexToRgba(hex: string, alpha: number) {
  const clean = hex.replace("#", "");
  const normalized = clean.length === 3 ? clean.split("").map((c) => `${c}${c}`).join("") : clean;
  const value = Number.parseInt(normalized, 16);
  const r = (value >> 16) & 255;
  const g = (value >> 8) & 255;
  const b = value & 255;
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function videoCellStyle(video: Video) {
  const title = video.title.toLowerCase();
  const match = VIDEO_HIGHLIGHT_RULES.find((rule) => rule.keywords.some((keyword) => title.includes(keyword)));
  if (!match) {
    return {};
  }
  return {
    backgroundColor: hexToRgba(match.color, 0.18)
  };
}

function setSort(field: SortField) {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === "asc" ? "desc" : "asc";
    return;
  }
  sortField.value = field;
  sortDirection.value = field === "title" ? "asc" : "desc";
}

function sortIndicator(field: SortField) {
  if (sortField.value !== field) return "";
  return sortDirection.value === "asc" ? "▲" : "▼";
}

function sortShortest() {
  sortField.value = "durationSec";
  sortDirection.value = "asc";
}

function sortNewest() {
  sortField.value = "publishedAt";
  sortDirection.value = "desc";
}

function setVideoRowRef(videoId: number, el: HTMLTableRowElement | null) {
  if (!el) {
    delete videoRowRefs.value[videoId];
    return;
  }
  videoRowRefs.value[videoId] = el;
}

async function jumpToDuration(thresholdMinutes: number) {
  const thresholdSeconds = thresholdMinutes * 60;
  const target = sortedVideos.value.find((video) => parseDurationLabel(video.durationLabel) > thresholdSeconds);
  if (!target) return;

  await nextTick();
  const row = videoRowRefs.value[target.id];
  if (row) {
    row.scrollIntoView({ behavior: "smooth", block: "center" });
  }
}

onMounted(fetchChannels);
</script>

<template>
  <main class="layout">
    <section class="sidebar">
      <h1>YTFinder</h1>
      <p class="hint">Scan once and browse channels locally.</p>

      <form class="scan-form" @submit.prevent="scanChannel">
        <input
          v-model="channelUrl"
          type="url"
          required
          placeholder="https://www.youtube.com/@YogaWithBird/videos"
        />
        <button :disabled="loading">{{ loading ? "Scanning..." : "Scan Channel" }}</button>
      </form>

      <p v-if="error" class="error">{{ error }}</p>

      <h2>Scanned Channels</h2>
      <ul class="channel-list">
        <li v-for="channel in channels" :key="channel.id">
          <button class="channel-btn" @click="openChannel(channel)">
            <span>{{ channel.displayName }}</span>
            <small>{{ channel._count?.videos || 0 }} videos</small>
          </button>
        </li>
      </ul>
    </section>

    <section class="content">
      <template v-if="selectedChannel">
        <header class="content-header">
          <div>
            <h2>{{ selectedChannel.displayName }}</h2>
          </div>
        </header>
        <div class="header-actions actions-sticky">
          <button class="plain-btn" :disabled="refreshing" @click="refreshChannel">
            {{ refreshing ? "Refreshing..." : "Refresh" }}
          </button>
          <button class="plain-btn" @click="sortShortest">Shortest</button>
          <button class="plain-btn" @click="jumpToDuration(5)">~5min</button>
          <button class="plain-btn" @click="jumpToDuration(10)">~10min</button>
          <button class="plain-btn" @click="jumpToDuration(15)">~15min</button>
          <button class="plain-btn" @click="sortNewest">Newest</button>
          <a :href="selectedChannel.sourceUrl" target="_blank" rel="noreferrer">Open channel</a>
        </div>

        <p v-if="loadingVideos">Loading videos...</p>

        <table v-else>
          <thead>
            <tr>
              <th>
                <button class="sort-btn" @click="setSort('title')">
                  Title {{ sortIndicator("title") }}
                </button>
              </th>
              <th>
                <button class="sort-btn" @click="setSort('durationSec')">
                  Length {{ sortIndicator("durationSec") }}
                </button>
              </th>
              <th>
                <button class="sort-btn" @click="setSort('publishedAt')">
                  Date {{ sortIndicator("publishedAt") }}
                </button>
              </th>
              <th>
                <button class="sort-btn" @click="setSort('viewCount')">
                  Views {{ sortIndicator("viewCount") }}
                </button>
              </th>
              <th>
                <button class="sort-btn" @click="setSort('clickCount')">
                  Clicks {{ sortIndicator("clickCount") }}
                </button>
              </th>
              <th>Favorite</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="video in sortedVideos" :key="video.id" :ref="(el) => setVideoRowRef(video.id, el as HTMLTableRowElement | null)">
              <td :style="videoCellStyle(video)">
                <button class="video-link" @click="openVideo(video)">
                  {{ video.title }}
                </button>
              </td>
              <td :style="videoCellStyle(video)">{{ video.durationLabel }}</td>
              <td :style="videoCellStyle(video)">{{ formatDate(video.publishedAt) }}</td>
              <td :style="videoCellStyle(video)">{{ video.viewCount.toLocaleString() }}</td>
              <td :style="videoCellStyle(video)">{{ video.clickCount }}</td>
              <td :style="videoCellStyle(video)">
                <button class="plain-btn" @click="toggleFavorite(video)">
                  {{ video.isFavorite ? "★" : "☆" }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </template>
      <p v-else>Select a channel to view videos.</p>
    </section>
  </main>
</template>
