import { FastifyInstance } from "fastify";
import { z } from "zod";
import { prisma } from "../lib/prisma.js";
import { scanChannel } from "../lib/youtube.js";

export function formatDuration(durationSec: number) {
  const h = Math.floor(durationSec / 3600);
  const m = Math.floor((durationSec % 3600) / 60);
  const s = durationSec % 60;
  if (h > 0) return `${h}:${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`;
  return `${m}:${s.toString().padStart(2, "0")}`;
}

export async function channelRoutes(app: FastifyInstance) {
  function toVideoCreateData(videos: Awaited<ReturnType<typeof scanChannel>>["videos"], channelId: number) {
    return videos.map((video) => ({
      channelId,
      youtubeVideoId: video.youtubeVideoId,
      title: video.title,
      durationSec: video.durationSec,
      publishedAt: video.publishedAt,
      viewCount: video.viewCount,
      videoUrl: video.videoUrl
    }));
  }

  app.get("/channels", async () => {
    return prisma.channel.findMany({
      orderBy: { createdAt: "desc" },
      include: {
        _count: { select: { videos: true } }
      }
    });
  });

  app.post("/channels/scan", async (request, reply) => {
    const schema = z.object({ url: z.string().url() });
    const parsed = schema.safeParse(request.body);

    if (!parsed.success) {
      return reply.code(400).send({ error: parsed.error.flatten() });
    }

    const url = parsed.data.url;
    const scan = await scanChannel(url);

    const existing = await prisma.channel.findUnique({ where: { handle: scan.handle } });
    if (existing) {
      return reply.send(existing);
    }

    const channel = await prisma.channel.create({
      data: {
        handle: scan.handle,
        displayName: scan.displayName,
        youtubeChannelId: scan.youtubeChannelId,
        sourceUrl: scan.sourceUrl,
        videos: {
          createMany: {
            data: scan.videos.map((video) => ({
              youtubeVideoId: video.youtubeVideoId,
              title: video.title,
              durationSec: video.durationSec,
              publishedAt: video.publishedAt,
              viewCount: video.viewCount,
              videoUrl: video.videoUrl
            }))
          }
        }
      }
    });

    return channel;
  });

  app.post("/channels/:channelId/refresh", async (request, reply) => {
    const paramsSchema = z.object({ channelId: z.coerce.number().int().positive() });
    const parsed = paramsSchema.safeParse(request.params);

    if (!parsed.success) {
      return reply.code(400).send({ error: "Invalid channel id" });
    }

    const channelId = parsed.data.channelId;
    const channel = await prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) {
      return reply.code(404).send({ error: "Channel not found" });
    }

    const scan = await scanChannel(channel.sourceUrl);
    const existingVideos = await prisma.video.findMany({
      where: { channelId },
      select: { youtubeVideoId: true }
    });
    const existingIds = new Set(existingVideos.map((v) => v.youtubeVideoId));
    const newVideos = scan.videos.filter((video) => !existingIds.has(video.youtubeVideoId));

    if (newVideos.length > 0) {
      await prisma.video.createMany({
        data: toVideoCreateData(newVideos, channelId)
      });
    }

    await prisma.channel.update({
      where: { id: channelId },
      data: {
        displayName: scan.displayName,
        youtubeChannelId: scan.youtubeChannelId,
        sourceUrl: scan.sourceUrl,
        lastScannedAt: new Date()
      }
    });

    return {
      addedCount: newVideos.length
    };
  });

  app.get("/channels/:channelId/videos", async (request, reply) => {
    const paramsSchema = z.object({ channelId: z.coerce.number().int().positive() });
    const parsed = paramsSchema.safeParse(request.params);

    if (!parsed.success) {
      return reply.code(400).send({ error: "Invalid channel id" });
    }

    const channelId = parsed.data.channelId;

    const channel = await prisma.channel.findUnique({ where: { id: channelId } });
    if (!channel) {
      return reply.code(404).send({ error: "Channel not found" });
    }

    const videos = await prisma.video.findMany({
      where: { channelId },
      orderBy: { publishedAt: "desc" }
    });

    return videos.map((v) => ({
      ...v,
      durationLabel: formatDuration(v.durationSec)
    }));
  });

  app.patch("/videos/:videoId/favorite", async (request, reply) => {
    const paramsSchema = z.object({ videoId: z.coerce.number().int().positive() });
    const bodySchema = z.object({ isFavorite: z.boolean() });

    const params = paramsSchema.safeParse(request.params);
    const body = bodySchema.safeParse(request.body);

    if (!params.success || !body.success) {
      return reply.code(400).send({ error: "Invalid payload" });
    }

    const video = await prisma.video.update({
      where: { id: params.data.videoId },
      data: { isFavorite: body.data.isFavorite }
    });

    return video;
  });

  app.post("/videos/:videoId/click", async (request, reply) => {
    const paramsSchema = z.object({ videoId: z.coerce.number().int().positive() });
    const params = paramsSchema.safeParse(request.params);

    if (!params.success) {
      return reply.code(400).send({ error: "Invalid video id" });
    }

    const video = await prisma.video.update({
      where: { id: params.data.videoId },
      data: { clickCount: { increment: 1 } }
    });

    return { clickCount: video.clickCount };
  });
}
