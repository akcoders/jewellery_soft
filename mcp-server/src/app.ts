/**
 * Web-standard fetch application: routes /mcp (authenticated), /health and the
 * protected-resource metadata document. Runs unchanged on Vercel functions and
 * on the local Node adapter (src/local.ts).
 */
import { getOAuthProtectedResourceMetadataUrl, hostHeaderValidationResponse, requireBearerAuth } from "@modelcontextprotocol/server";
import { createVerifier } from "./auth.js";
import { loadConfig, SERVER_NAME, SERVER_VERSION } from "./config.js";
import { stateDb } from "./db.js";
import { authorizationServerMetadata, availableScopes, handleAuthorize, handleCallback, handleRegister, handleRevoke, handleToken, issuerUrl, oauthEnabled } from "./oauth.js";
import { mcpHandler } from "./server.js";
import { sqlEnabled } from "./sql.js";

const CORS_HEADERS: Record<string, string> = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "GET, POST, DELETE, OPTIONS",
  "Access-Control-Allow-Headers": "Authorization, Content-Type, Accept, Mcp-Session-Id, Mcp-Protocol-Version, Last-Event-ID",
  "Access-Control-Expose-Headers": "Mcp-Session-Id, Mcp-Protocol-Version, WWW-Authenticate",
  "Access-Control-Max-Age": "86400"
};

function withCors(response: Response): Response {
  const headers = new Headers(response.headers);
  for (const [key, value] of Object.entries(CORS_HEADERS)) headers.set(key, value);
  return new Response(response.body, { status: response.status, statusText: response.statusText, headers });
}

function json(body: unknown, status = 200, extraHeaders: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body, null, 2), {
    status,
    headers: { "Content-Type": "application/json; charset=utf-8", "Cache-Control": "no-store", ...extraHeaders }
  });
}

/** Strips the Vercel "/api" prefix so the same router serves /mcp and /api/mcp. */
function routePath(url: URL): string {
  const path = url.pathname.replace(/\/+$/, "") || "/";
  return path.startsWith("/api/") ? path.slice(4) : path;
}

const verifier = createVerifier();

function buildGate(requestUrl: URL) {
  const { publicUrl } = loadConfig();
  const serverUrl = publicUrl !== "" ? new URL(publicUrl) : new URL("/mcp", requestUrl.origin);
  return requireBearerAuth({
    verifier,
    resourceMetadataUrl: getOAuthProtectedResourceMetadataUrl(serverUrl),
    ...(publicUrl !== "" ? { expectedResource: serverUrl } : {})
  });
}

async function health(): Promise<Response> {
  const config = loadConfig();
  let stateDbStatus: "ok" | "error" | "not-configured" = "not-configured";
  const pool = stateDb();
  if (pool) {
    try {
      await pool.query("SELECT 1");
      stateDbStatus = "ok";
    } catch {
      stateDbStatus = "error";
    }
  }
  return json({
    ok: stateDbStatus !== "error",
    server: SERVER_NAME,
    version: SERVER_VERSION,
    time: new Date().toISOString(),
    erp_configured: config.erpBaseUrl !== "",
    state_db: stateDbStatus,
    sql_tools: sqlEnabled(),
    bootstrap_key_enabled: config.bootstrapKey !== ""
  });
}

function resourceMetadata(requestUrl: URL): Response {
  const { publicUrl } = loadConfig();
  const resource = publicUrl !== "" ? publicUrl : new URL("/mcp", requestUrl.origin).href;
  return json({
    resource,
    ...(oauthEnabled() ? { authorization_servers: [issuerUrl()] } : {}),
    bearer_methods_supported: ["header"],
    scopes_supported: availableScopes(),
    resource_name: "Aabhushan ERP MCP",
    resource_documentation: new URL("/", requestUrl.origin).href
  });
}

const AS_METADATA_PATHS = [
  "/.well-known/oauth-authorization-server",
  "/.well-known/oauth-authorization-server/mcp",
  "/.well-known/openid-configuration",
  "/.well-known/openid-configuration/mcp"
];

/** OAuth 2.1 endpoints; null when the path is not an OAuth route or OAuth is not configured. */
async function oauthRoute(path: string, request: Request): Promise<Response | null> {
  const isOAuthPath = AS_METADATA_PATHS.includes(path) || ["/register", "/authorize", "/token", "/revoke", "/oauth/callback"].includes(path);
  if (!isOAuthPath) return null;
  if (!oauthEnabled()) {
    return json({ error: "not_found", message: "OAuth is not enabled on this server (needs MCP_PUBLIC_URL, MCP_DATABASE_URL, ERP_BASE_URL and MCP_ERP_SHARED_SECRET)." }, 404);
  }
  try {
    if (AS_METADATA_PATHS.includes(path)) return authorizationServerMetadata();
    if (path === "/register") return await handleRegister(request);
    if (path === "/authorize") return await handleAuthorize(request);
    if (path === "/oauth/callback") return await handleCallback(request);
    if (path === "/token") return await handleToken(request);
    return await handleRevoke(request);
  } catch (error) {
    console.error(`[oauth] ${path} failed`, error);
    return json({ error: "server_error", error_description: "The authorization server hit an internal error. Try again shortly." }, 500);
  }
}

export const app = {
  async fetch(request: Request): Promise<Response> {
    const url = new URL(request.url);
    const path = routePath(url);

    if (request.method === "OPTIONS") return withCors(new Response(null, { status: 204 }));

    const { allowedHosts } = loadConfig();
    if (allowedHosts.length > 0) {
      const rejected = hostHeaderValidationResponse(request, allowedHosts);
      if (rejected) return withCors(rejected);
    }

    if (path === "/health") return withCors(await health());
    if (path === "/.well-known/oauth-protected-resource" || path === "/.well-known/oauth-protected-resource/mcp" || path === "/resource-metadata") {
      return withCors(resourceMetadata(url));
    }

    const oauthResponse = await oauthRoute(path, request);
    if (oauthResponse) return withCors(oauthResponse);

    if (path === "/mcp" || path.startsWith("/mcp/")) {
      const auth = await buildGate(url)(request);
      if (auth instanceof Response) return withCors(auth);
      try {
        return withCors(await mcpHandler.fetch(request, { authInfo: auth }));
      } catch (error) {
        console.error("[mcp] unhandled", error);
        return withCors(json({ jsonrpc: "2.0", error: { code: -32603, message: "Internal server error" }, id: null }, 500));
      }
    }

    if (path === "/") {
      return withCors(json({ server: SERVER_NAME, version: SERVER_VERSION, mcp_endpoint: new URL("/mcp", url.origin).href, auth: "Authorization: Bearer <api key>" }));
    }
    return withCors(json({ error: "not_found", message: `No route for ${request.method} ${url.pathname}` }, 404));
  }
};
