# YTFinder

Laravel + Vue.js dashboard that scans a YouTube channel once, stores video metadata in SQLite, and lets you browse/sort videos by length. Great for choosing yoga sessions that last exactly 6min :-)

<img width="900" alt="image" src="https://github.com/user-attachments/assets/6dab5350-8285-41e1-8263-6d0bd952e48f" />


## What this solves

- Input a URL like `https://www.youtube.com/@YogaWithBird/videos`
- Scan all channel videos through YouTube Data API v3 (paginated)
- Store title, duration, publish date & views
- Highlights videos by keywords (`morning` / `evening` / `stress`)
- Persist `isFavorite` and `clickCount`
- Show previously scanned channels immediately on dashboard load
- Click a channel to view its videos

## Tech stack

- Backend: Laravel + MySQL
- Frontend: Vue 3 + Vite

## Prerequisites

- PHP8+
- A YouTube Data API key

## Setup

1. Install dependencies:
   - `composer install`
   - `npm install`
   - `npm run build`
2. Configure env:
   - `cp .env.example .env`
   - set `YOUTUBE_API_KEY` in `.env`
