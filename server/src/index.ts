import "dotenv/config";
import Fastify from "fastify";
import fastifyCors from "@fastify/cors";
import { channelRoutes } from "./routes/channels.js";

export function createApp() {
  const app = Fastify({ logger: true });
  app.register(fastifyCors, {
    origin: true,
    methods: ["GET", "POST", "PATCH", "PUT", "DELETE", "OPTIONS"],
    allowedHeaders: ["Content-Type", "Authorization"],
    preflightContinue: false,
    optionsSuccessStatus: 204
  });
  app.get("/api/health", async () => ({
    ok: true,
    scanStrategy: "channels.forHandle -> playlistItems.uploads -> videos.details"
  }));
  app.register(channelRoutes, { prefix: "/api" });
  return app;
}

const app = createApp();
const port = Number(process.env.PORT || 3001);
app.listen({ host: "0.0.0.0", port }).catch((err) => {
  app.log.error(err);
  process.exit(1);
});
