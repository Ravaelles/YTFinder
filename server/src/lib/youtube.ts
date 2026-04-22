const API_BASE = "https://www.googleapis.com/youtube/v3";

type ChannelItem = {
  id: string;
  snippet: {
    title: string;
  };
  contentDetails?: {
    relatedPlaylists?: {
      uploads?: string;
    };
  };
};

type PlaylistVideoItem = {
  snippet: {
    resourceId?: { videoId?: string };
  };
};

type VideoDetailItem = {
  id: string;
  contentDetails: { duration: string };
  statistics: { viewCount?: string };
  snippet: { title: string; publishedAt: string };
};

export type FetchedVideo = {
  youtubeVideoId: string;
  title: string;
  durationSec: number;
  publishedAt: Date;
  viewCount: number;
  videoUrl: string;
};

export type FetchedChannel = {
  handle: string;
  sourceUrl: string;
  displayName: string;
  youtubeChannelId: string;
  videos: FetchedVideo[];
};

function getApiKey() {
  const key = process.env.YOUTUBE_API_KEY;
  if (!key) {
    throw new Error("YOUTUBE_API_KEY is not set");
  }
  return key;
}

export function parseIso8601Duration(duration: string): number {
  const match = duration.match(/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/);
  if (!match) return 0;

  const hours = Number(match[1] || 0);
  const minutes = Number(match[2] || 0);
  const seconds = Number(match[3] || 0);

  return hours * 3600 + minutes * 60 + seconds;
}

export function parseHandle(url: string): string {
  const parsed = new URL(url);
  const segments = parsed.pathname.split("/").filter(Boolean);
  const handle = segments.find((s) => s.startsWith("@"));
  if (!handle) {
    throw new Error("Please provide a channel URL with @handle");
  }
  return handle;
}

async function ytFetch<T>(path: string, params: Record<string, string>): Promise<T> {
  const apiKey = getApiKey();
  const usp = new URLSearchParams({ ...params, key: apiKey });
  const res = await fetch(`${API_BASE}/${path}?${usp.toString()}`);

  if (!res.ok) {
    const text = await res.text();
    throw new Error(`YouTube API error on ${path}: ${res.status} ${text}`);
  }

  return (await res.json()) as T;
}

async function fetchChannelByHandle(handle: string) {
  const payload = await ytFetch<{ items: ChannelItem[] }>("channels", {
    part: "snippet,contentDetails",
    forHandle: handle.replace(/^@/, ""),
    maxResults: "1"
  });

  if (!payload.items.length) {
    throw new Error(`No channel found for handle ${handle}`);
  }

  const channel = payload.items[0];
  const uploadsPlaylistId = channel.contentDetails?.relatedPlaylists?.uploads;
  if (!uploadsPlaylistId) {
    throw new Error(`Uploads playlist not available for handle ${handle}`);
  }

  return {
    channelId: channel.id,
    channelTitle: channel.snippet.title,
    uploadsPlaylistId
  };
}

async function fetchAllVideosByUploadsPlaylist(uploadsPlaylistId: string) {
  const videoIds: string[] = [];
  let pageToken: string | undefined;

  while (true) {
    const payload = await ytFetch<{ items: PlaylistVideoItem[]; nextPageToken?: string }>("playlistItems", {
      part: "snippet",
      playlistId: uploadsPlaylistId,
      maxResults: "50",
      pageToken: pageToken || ""
    });

    const ids = payload.items
      .map((item) => item.snippet.resourceId?.videoId)
      .filter((id): id is string => Boolean(id));
    videoIds.push(...ids);

    if (!payload.nextPageToken) break;
    pageToken = payload.nextPageToken;
  }

  return videoIds;
}

async function fetchVideoDetails(videoIds: string[]) {
  const chunks: string[][] = [];
  for (let i = 0; i < videoIds.length; i += 50) {
    chunks.push(videoIds.slice(i, i + 50));
  }

  const detailed: VideoDetailItem[] = [];
  for (const chunk of chunks) {
    const payload = await ytFetch<{ items: VideoDetailItem[] }>("videos", {
      part: "contentDetails,statistics,snippet",
      id: chunk.join(",")
    });
    detailed.push(...payload.items);
  }

  return detailed;
}

export async function scanChannel(url: string): Promise<FetchedChannel> {
  const handle = parseHandle(url);
  const channel = await fetchChannelByHandle(handle);
  const videoIds = await fetchAllVideosByUploadsPlaylist(channel.uploadsPlaylistId);

  if (!videoIds.length) {
    throw new Error(`No videos found for handle ${handle}`);
  }

  const details = await fetchVideoDetails(videoIds);

  const detailMap = new Map(details.map((d) => [d.id, d]));

  const videos: FetchedVideo[] = videoIds
    .map((id) => {
      const detail = detailMap.get(id);
      if (!detail) return null;

      return {
        youtubeVideoId: id,
        title: detail.snippet.title,
        durationSec: parseIso8601Duration(detail.contentDetails.duration),
        publishedAt: new Date(detail.snippet.publishedAt),
        viewCount: Number(detail.statistics.viewCount || 0),
        videoUrl: `https://www.youtube.com/watch?v=${id}`
      };
    })
    .filter((v): v is FetchedVideo => Boolean(v));

  return {
    handle,
    sourceUrl: url,
    displayName: channel.channelTitle,
    youtubeChannelId: channel.channelId,
    videos
  };
}
