import { afterEach, describe, expect, it, vi } from "vitest";
import { parseHandle, parseIso8601Duration, scanChannel } from "../src/lib/youtube.js";

describe("youtube helpers", () => {
  afterEach(() => {
    vi.restoreAllMocks();
    delete process.env.YOUTUBE_API_KEY;
  });

  it("parses handle and duration", () => {
    expect(parseHandle("https://www.youtube.com/@YogaWithBird/videos")).toBe("@YogaWithBird");
    expect(parseIso8601Duration("PT1H2M3S")).toBe(3723);
    expect(parseIso8601Duration("PT5M")).toBe(300);
  });

  it("throws for URL without @handle", () => {
    expect(() => parseHandle("https://www.youtube.com/channel/abcd")).toThrow(
      "Please provide a channel URL with @handle"
    );
  });

  it("scans and maps videos from API payload", async () => {
    process.env.YOUTUBE_API_KEY = "test-key";

    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          items: [
            {
              id: "channel-1",
              snippet: {
                title: "Yoga With Bird"
              },
              contentDetails: {
                relatedPlaylists: {
                  uploads: "uploads-1"
                }
              }
            }
          ]
        })
      })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          items: [
            {
              snippet: {
                resourceId: { videoId: "v1" }
              }
            }
          ]
        })
      })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          items: [
            {
              id: "v1",
              contentDetails: { duration: "PT10M5S" },
              statistics: { viewCount: "42" },
              snippet: {
                title: "Video Title",
                publishedAt: "2025-01-01T00:00:00.000Z"
              }
            }
          ]
        })
      });

    vi.stubGlobal("fetch", fetchMock);

    const channel = await scanChannel("https://www.youtube.com/@YogaWithBird/videos");

    expect(channel.handle).toBe("@YogaWithBird");
    expect(channel.displayName).toBe("Yoga With Bird");
    expect(channel.youtubeChannelId).toBe("channel-1");
    expect(channel.videos).toHaveLength(1);
    expect(channel.videos[0]).toMatchObject({
      youtubeVideoId: "v1",
      title: "Video Title",
      durationSec: 605,
      viewCount: 42,
      videoUrl: "https://www.youtube.com/watch?v=v1"
    });
  });

  it("reports clear error when channel is missing", async () => {
    process.env.YOUTUBE_API_KEY = "test-key";
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ items: [] })
      })
    );

    await expect(scanChannel("https://www.youtube.com/@Missing/videos")).rejects.toThrow(
      "No channel found for handle @Missing"
    );
  });
});
