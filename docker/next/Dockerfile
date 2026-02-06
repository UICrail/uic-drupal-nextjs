# Multi-stage build for Next.js

# Development stage
FROM node:20-alpine AS development

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm ci

# Copy source code
COPY . .

# Expose port
EXPOSE 3000

# Development command
CMD ["npm", "run", "dev"]

# Build stage
FROM node:20-alpine AS build

WORKDIR /app

# Install dependencies
COPY package*.json ./
RUN npm ci

# Copy source code
COPY . .

# Build Next.js application
RUN npm run graphql-codegen
RUN npm run build

# Production stage
FROM node:20-alpine AS production

WORKDIR /app

# Install sharp for image optimization
RUN apk add --no-cache libc6-compat

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV NEXT_SHARP_PATH=/app/node_modules/sharp

# Copy built application (standalone mode must be enabled in next.config.mjs)
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
COPY --from=build /app/public ./public

# Install sharp if not present in standalone
RUN npm install sharp --omit=dev || true

# Set permissions
RUN chown -R node:node /app
USER node

EXPOSE 3000

CMD ["node", "server.js"]

