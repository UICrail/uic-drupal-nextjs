// @ts-check
/**
 * Custom Next.js 15 Cache Handler with Redis support
 * Based on Next.js native cache handler API
 * Documentation: https://nextjs.org/docs/app/api-reference/config/next-config-js/incrementalCacheHandlerPath
 */

import { createClient } from "redis";

// This should match the REVALIDATE_LONG value in src/lib/constants.ts
const DEFAULT_STALE_AGE = 60 * 10; // 10 minutes
const REDIS_TIMEOUT = 3000; // 3 seconds

/** @type {import("redis").RedisClientType | undefined} */
let redisClient;

/** @type {Map<string, any>} */
const memoryCache = new Map();

/** @type {Map<string, Set<string>>} */
const tagCache = new Map();

/**
 * Initialize Redis client if available
 */
async function initRedis() {
  if (
    process.env.NEXT_PHASE === "phase-production-build" ||
    !process.env.REDIS_HOST ||
    !process.env.REDIS_PASS
  ) {
    console.info("[Cache] Redis not configured, using in-memory cache");
    return null;
  }

  try {
    const client = createClient({
      socket: {
        port: 6379,
        host: process.env.REDIS_HOST,
      },
      password: process.env.REDIS_PASS,
    });

    client.on("error", (e) => {
      console.error("[Cache] Redis client error:", e);
    });

    console.info("[Cache] Connecting to Redis...");
    await Promise.race([
      client.connect(),
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error("Redis connection timeout")), 5000),
      ),
    ]);

    console.info("[Cache] Redis connected successfully");
    return client;
  } catch (error) {
    console.warn("[Cache] Failed to connect to Redis:", error.message);
    return null;
  }
}

/**
 * Wrapper for Redis operations with timeout
 * @param {Promise} operation
 * @returns {Promise<any>}
 */
async function withTimeout(operation) {
  try {
    return await Promise.race([
      operation,
      new Promise((_, reject) =>
        setTimeout(() => reject(new Error("Operation timeout")), REDIS_TIMEOUT),
      ),
    ]);
  } catch (error) {
    console.warn("[Cache] Redis operation failed:", error.message);
    return null;
  }
}

/**
 * Next.js Cache Handler Class
 */
export default class CacheHandler {
  constructor(options) {
    this.options = options;
    this.buildId = options?.buildId || "default";
    this.initialized = false;
  }

  /**
   * Initialize the cache handler
   */
  async init() {
    if (!this.initialized) {
      redisClient = await initRedis();
      this.initialized = true;
    }
  }

  /**
   * Get a value from the cache
   * @param {string} key
   * @returns {Promise<any>}
   */
  async get(key) {
    await this.init();

    const cacheKey = `cache:${this.buildId}:${key}`;

    // Try Redis first
    if (redisClient?.isReady) {
      const data = await withTimeout(redisClient.get(cacheKey));
      if (data) {
        try {
          return JSON.parse(data);
        } catch {
          return null;
        }
      }
    }

    // Fallback to memory cache
    return memoryCache.get(cacheKey) || null;
  }

  /**
   * Set a value in the cache
   * @param {string} key
   * @param {any} data
   * @param {{ tags?: string[] }} ctx
   * @returns {Promise<void>}
   */
  async set(key, data, ctx = {}) {
    await this.init();

    const cacheKey = `cache:${this.buildId}:${key}`;
    const serialized = JSON.stringify(data);

    // Calculate TTL
    const ttl = data?.lifespan?.staleTime || DEFAULT_STALE_AGE;

    // Store in Redis
    if (redisClient?.isReady) {
      await withTimeout(redisClient.set(cacheKey, serialized, { EX: ttl }));

      // Store tags associations
      if (ctx.tags && ctx.tags.length > 0) {
        const tagsKey = `tags:${this.buildId}:${key}`;
        await withTimeout(
          redisClient.set(tagsKey, JSON.stringify(ctx.tags), { EX: ttl }),
        );

        // Store reverse mapping (tag -> keys)
        for (const tag of ctx.tags) {
          const tagKeysKey = `tag:${this.buildId}:${tag}`;
          await withTimeout(redisClient.sAdd(tagKeysKey, key));
          await withTimeout(redisClient.expire(tagKeysKey, ttl));
        }
      }
    }

    // Store in memory cache as fallback
    memoryCache.set(cacheKey, data);

    // Store tags in memory
    if (ctx.tags && ctx.tags.length > 0) {
      for (const tag of ctx.tags) {
        if (!tagCache.has(tag)) {
          tagCache.set(tag, new Set());
        }
        tagCache.get(tag).add(cacheKey);
      }
    }
  }

  /**
   * Revalidate cache entries by tag
   * @param {string | string[]} tags
   * @returns {Promise<void>}
   */
  async revalidateTag(tags) {
    await this.init();

    const tagArray = Array.isArray(tags) ? tags : [tags];

    for (const tag of tagArray) {
      // Redis: delete all keys associated with this tag
      if (redisClient?.isReady) {
        const tagKeysKey = `tag:${this.buildId}:${tag}`;
        const keys = await withTimeout(redisClient.sMembers(tagKeysKey));

        if (keys && keys.length > 0) {
          for (const key of keys) {
            const cacheKey = `cache:${this.buildId}:${key}`;
            await withTimeout(redisClient.del(cacheKey));
          }
          await withTimeout(redisClient.del(tagKeysKey));
        }
      }

      // Memory cache: delete all keys associated with this tag
      const keysToDelete = tagCache.get(tag);
      if (keysToDelete) {
        for (const key of keysToDelete) {
          memoryCache.delete(key);
        }
        tagCache.delete(tag);
      }
    }

    console.info(`[Cache] Revalidated tags:`, tagArray);
  }

  /**
   * Reset the request-specific cache
   * This is called at the start of each request
   */
  resetRequestCache() {
    // This is for request-level caching
    // We don't need to clear Redis or the main memory cache here
    // Next.js handles request-level caching separately
  }
}
