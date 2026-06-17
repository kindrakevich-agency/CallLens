# CallLens web image — Next.js 15 on Node 22 LTS.
# Build context is apps/web/ (see docker-compose web.build.context).
# syntax=docker/dockerfile:1

FROM node:22-bookworm-slim AS base
WORKDIR /app
ENV NEXT_TELEMETRY_DISABLED=1

# -----------------------------------------------------------------------------
# Dev — deps installed, source bind-mounted by compose, `npm run dev`.
# -----------------------------------------------------------------------------
FROM base AS dev
COPY package*.json ./
RUN npm install
COPY . .
EXPOSE 3000
CMD ["npm", "run", "dev"]

# -----------------------------------------------------------------------------
# Prod — install, build, run the optimized server.
# -----------------------------------------------------------------------------
FROM base AS build
COPY package*.json ./
RUN npm ci || npm install
COPY . .
RUN npm run build

FROM base AS prod
ENV NODE_ENV=production
COPY --from=build /app/.next ./.next
COPY --from=build /app/public ./public
COPY --from=build /app/package*.json ./
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/next.config.* ./
EXPOSE 3000
CMD ["npm", "run", "start"]
