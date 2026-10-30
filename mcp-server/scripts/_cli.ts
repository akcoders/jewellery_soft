import { loadDotEnv } from "../src/env.js";

loadDotEnv();

export function arg(name: string, fallback = ""): string {
  const index = process.argv.indexOf(`--${name}`);
  if (index === -1) return fallback;
  const value = process.argv[index + 1];
  return value === undefined || value.startsWith("--") ? "true" : value;
}

export function fail(message: string): never {
  console.error(message);
  process.exit(1);
}
