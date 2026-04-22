# YTFinder

Node.js + Vue.js dashboard that scans a YouTube channel once, stores video metadata in SQLite, and lets you browse it with favorites + click tracking.

## What this solves

- Input a URL like `https://www.youtube.com/@YogaWithBird/videos`
- Scan all channel videos through YouTube Data API v3 (paginated)
- Store title, duration, publish date, views in SQLite
- Persist `isFavorite` and `clickCount`
- Show previously scanned channels immediately on dashboard load
- Click a channel to view its videos

## Tech stack

- Backend: Fastify + Prisma + SQLite
- Frontend: Vue 3 + Vite

## Project structure

- `server/`: API, YouTube ingestion, SQLite schema
- `web/`: Vue dashboard

## Prerequisites

- Node.js 20+
- A YouTube Data API key

## Setup

1. Install dependencies:
   - `cd server && npm install`
   - `cd ../web && npm install`
2. Configure env:
   - `cd ../server`
   - `cp .env.example .env`
   - set `YOUTUBE_API_KEY` in `.env`
3. Create DB + Prisma client:
   - `npm run prisma:generate`
   - `npm run prisma:migrate`

## Run

- Backend: `cd server && npm run dev` (http://localhost:3001)
- Frontend: `cd web && npm run dev` (http://localhost:5173)

## API endpoints

- `GET /api/channels`
- `POST /api/channels/scan`
- `GET /api/channels/:channelId/videos`
- `PATCH /api/videos/:videoId/favorite`
- `POST /api/videos/:videoId/click`

## Suggested improvement over naive approach

The YouTube Search API can return imperfect results for handles. For production reliability, resolve the handle to an exact channel ID first (or use playlist uploads feed) and then ingest from that source to avoid edge-case mismatches.
