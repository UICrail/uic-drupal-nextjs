/* eslint-disable n/no-process-env */

/**
 * Next.js Instrumentation
 * This file is used to initialize services when the Next.js server starts
 * Documentation: https://nextjs.org/docs/app/api-reference/file-conventions/instrumentation
 */

export async function register() {
  if (process.env.NEXT_RUNTIME === "nodejs") {
    // Custom cache handler is configured in next.config.mjs
    // No additional initialization needed for Next.js 15 native cache handler
    console.info("[Instrumentation] Next.js cache handler initialized");
  }
}
