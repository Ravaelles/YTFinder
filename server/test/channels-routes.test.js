import Fastify from "fastify";
import fastifyCors from "@fastify/cors";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { channelRoutes, formatDuration } from "../src/routes/channels.js";
const { prismaMock, scanChannelMock } = vi.hoisted(() => ({
    prismaMock: {
        channel: {
            findMany: vi.fn(),
            findUnique: vi.fn(),
            create: vi.fn()
        },
        video: {
            findMany: vi.fn(),
            update: vi.fn()
        }
    },
    scanChannelMock: vi.fn()
}));
vi.mock("../src/lib/prisma.js", () => ({ prisma: prismaMock }));
vi.mock("../src/lib/youtube.js", () => ({ scanChannel: scanChannelMock }));
function buildApp() {
    const app = Fastify();
    app.register(fastifyCors, { origin: true });
    app.register(channelRoutes, { prefix: "/api" });
    return app;
}
describe("channel routes", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });
    it("formats duration labels", () => {
        expect(formatDuration(65)).toBe("1:05");
        expect(formatDuration(3661)).toBe("1:01:01");
    });
    it("returns channels list", async () => {
        prismaMock.channel.findMany.mockResolvedValue([{ id: 1, handle: "@h" }]);
        const app = buildApp();
        const res = await app.inject({ method: "GET", url: "/api/channels" });
        expect(res.statusCode).toBe(200);
        expect(prismaMock.channel.findMany).toHaveBeenCalledTimes(1);
        expect(res.json()).toEqual([{ id: 1, handle: "@h" }]);
        await app.close();
    });
    it("rejects invalid scan payload", async () => {
        const app = buildApp();
        const res = await app.inject({ method: "POST", url: "/api/channels/scan", payload: {} });
        expect(res.statusCode).toBe(400);
        expect(scanChannelMock).not.toHaveBeenCalled();
        await app.close();
    });
    it("returns existing channel if already scanned", async () => {
        scanChannelMock.mockResolvedValue({ handle: "@Yoga", videos: [] });
        prismaMock.channel.findUnique.mockResolvedValue({ id: 4, handle: "@Yoga" });
        const app = buildApp();
        const res = await app.inject({
            method: "POST",
            url: "/api/channels/scan",
            payload: { url: "https://www.youtube.com/@Yoga/videos" }
        });
        expect(res.statusCode).toBe(200);
        expect(prismaMock.channel.create).not.toHaveBeenCalled();
        expect(res.json()).toEqual({ id: 4, handle: "@Yoga" });
        await app.close();
    });
    it("creates new channel with videos on first scan", async () => {
        scanChannelMock.mockResolvedValue({
            handle: "@Yoga",
            displayName: "Yoga",
            youtubeChannelId: "channel-1",
            sourceUrl: "https://www.youtube.com/@Yoga/videos",
            videos: [
                {
                    youtubeVideoId: "v1",
                    title: "title",
                    durationSec: 100,
                    publishedAt: new Date("2025-01-01T00:00:00.000Z"),
                    viewCount: 10,
                    videoUrl: "https://www.youtube.com/watch?v=v1"
                }
            ]
        });
        prismaMock.channel.findUnique.mockResolvedValue(null);
        prismaMock.channel.create.mockResolvedValue({ id: 10, handle: "@Yoga" });
        const app = buildApp();
        const res = await app.inject({
            method: "POST",
            url: "/api/channels/scan",
            payload: { url: "https://www.youtube.com/@Yoga/videos" }
        });
        expect(res.statusCode).toBe(200);
        expect(prismaMock.channel.create).toHaveBeenCalledTimes(1);
        expect(res.json()).toEqual({ id: 10, handle: "@Yoga" });
        await app.close();
    });
    it("handles videos listing and missing channels", async () => {
        prismaMock.channel.findUnique.mockResolvedValueOnce({ id: 1 }).mockResolvedValueOnce(null);
        prismaMock.video.findMany.mockResolvedValue([
            {
                id: 11,
                durationSec: 65,
                title: "A",
                publishedAt: "2025-01-01T00:00:00.000Z",
                viewCount: 3,
                clickCount: 0,
                isFavorite: false,
                videoUrl: "x"
            }
        ]);
        const app = buildApp();
        const okRes = await app.inject({ method: "GET", url: "/api/channels/1/videos" });
        expect(okRes.statusCode).toBe(200);
        expect(okRes.json()[0].durationLabel).toBe("1:05");
        const missingRes = await app.inject({ method: "GET", url: "/api/channels/2/videos" });
        expect(missingRes.statusCode).toBe(404);
        await app.close();
    });
    it("updates favorite and click counters", async () => {
        prismaMock.video.update.mockResolvedValueOnce({ id: 1, isFavorite: true }).mockResolvedValueOnce({
            id: 1,
            clickCount: 9
        });
        const app = buildApp();
        const favRes = await app.inject({
            method: "PATCH",
            url: "/api/videos/1/favorite",
            payload: { isFavorite: true }
        });
        expect(favRes.statusCode).toBe(200);
        expect(favRes.json().isFavorite).toBe(true);
        const clickRes = await app.inject({ method: "POST", url: "/api/videos/1/click" });
        expect(clickRes.statusCode).toBe(200);
        expect(clickRes.json()).toEqual({ clickCount: 9 });
        await app.close();
    });
    it("supports preflight OPTIONS", async () => {
        const app = buildApp();
        const res = await app.inject({
            method: "OPTIONS",
            url: "/api/channels/scan",
            headers: {
                origin: "http://localhost:5173",
                "access-control-request-method": "POST"
            }
        });
        expect([200, 204]).toContain(res.statusCode);
        expect(res.headers["access-control-allow-origin"]).toBeTruthy();
        await app.close();
    });
});
