/* eslint-disable n/no-process-env */

import { loadEnvConfig } from "@next/env";
import type { CodegenConfig } from "@graphql-codegen/cli";
import fs from "fs";
import os from "os";
import path from "path";

const tokenFilePath = path.resolve(os.tmpdir(), ".drupal-access-token.txt");
const token = fs.readFileSync(tokenFilePath, "utf8").trim();

loadEnvConfig(process.cwd());

// Prefer internal Drupal base URL for schema introspection during codegen to
// ensure we hit the same GraphQL Compose schema used by server-side fetches.
const schemaUrl = `${process.env.DRUPAL_BASE_URL_INTERNAL || process.env.NEXT_PUBLIC_DRUPAL_BASE_URL}/graphql`;

const config: CodegenConfig = {
  schema: [
    {
      [schemaUrl]: {
        headers: {
          authorization: `Bearer ${token}`,
        },
      },
    },
  ],
  documents: [
    "src/lib/graphql/**/*.ts",
    "!./node_modules/**/*",
    "!./.next/**/*",
  ],
  ignoreNoDocuments: true, // for better experience with the watcher
  generates: {
    "./src/lib/gql/": {
      preset: "client",
      presetConfig: {
        fragmentMasking: false,
      },
    },
    "./src/lib/graphql/schema.graphql": {
      plugins: ["schema-ast"],
      config: {
        includeDirectives: true,
      },
    },
  },
  verbose: true,
};

export default config;
