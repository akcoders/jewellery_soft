/**
 * Environment configuration. Everything is read lazily so the server can boot
 * (and answer /health) even when optional integrations are not configured;
 * each tool reports precisely which variable it is missing.
 */
export const SERVER_NAME = "aabhushan-erp-mcp-server";
export const SERVER_VERSION = "1.0.0";

export const ALL_SCOPES = ["read", "write", "sql"] as const;
export type Scope = (typeof ALL_SCOPES)[number];

export interface AppConfig {
  erpBaseUrl: string;
  erpSharedSecret: string;
  tokenSecret: string;
  stateDbUrl: string;
  erpDbUrl: string;
  publicUrl: string;
  allowedHosts: string[];
  bootstrapKey: string;
  bootstrapAdminUserId: number;
}

function env(name: string, fallback = ""): string {
  const value = process.env[name];
  return value === undefined ? fallback : value.trim();
}

export function loadConfig(): AppConfig {
  return {
    erpBaseUrl: env("ERP_BASE_URL").replace(/\/+$/, ""),
    erpSharedSecret: env("MCP_ERP_SHARED_SECRET"),
    tokenSecret: env("MCP_TOKEN_SECRET"),
    stateDbUrl: env("MCP_DATABASE_URL"),
    erpDbUrl: env("ERP_DATABASE_URL"),
    publicUrl: env("MCP_PUBLIC_URL"),
    allowedHosts: env("MCP_ALLOWED_HOSTS")
      .split(",")
      .map(host => host.trim().toLowerCase())
      .filter(host => host !== ""),
    bootstrapKey: env("MCP_BOOTSTRAP_KEY"),
    bootstrapAdminUserId: Math.max(0, Number.parseInt(env("MCP_BOOTSTRAP_ADMIN_USER_ID", "0"), 10) || 0)
  };
}

export function isScope(value: string): value is Scope {
  return (ALL_SCOPES as readonly string[]).includes(value);
}
