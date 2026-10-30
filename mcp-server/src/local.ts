#!/usr/bin/env node
/**
 * Local / self-hosted entry point: adapts Node's http server to the
 * web-standard app. Vercel does not use this file (see api/*.ts).
 */
import http from "node:http";
import { Readable } from "node:stream";
import { app } from "./app.js";
import { loadDotEnv } from "./env.js";

loadDotEnv();

const port = Number(process.env.PORT ?? 3939);
const host = process.env.HOST ?? "127.0.0.1";

function toRequest(req: http.IncomingMessage): Request {
  const url = new URL(req.url ?? "/", `http://${req.headers.host ?? `${host}:${port}`}`);
  const headers = new Headers();
  for (const [key, value] of Object.entries(req.headers)) {
    if (value === undefined) continue;
    headers.set(key, Array.isArray(value) ? value.join(", ") : value);
  }
  const hasBody = req.method !== "GET" && req.method !== "HEAD";
  const init: RequestInit & { duplex?: "half" } = { method: req.method, headers };
  if (hasBody) {
    init.body = Readable.toWeb(req) as unknown as ReadableStream<Uint8Array>;
    init.duplex = "half";
  }
  return new Request(url, init);
}

const server = http.createServer(async (req, res) => {
  try {
    const response = await app.fetch(toRequest(req));
    const headers: Record<string, string> = {};
    response.headers.forEach((value, key) => {
      headers[key] = value;
    });
    res.writeHead(response.status, headers);
    if (!response.body) {
      res.end();
      return;
    }
    Readable.fromWeb(response.body as unknown as import("node:stream/web").ReadableStream<Uint8Array>).pipe(res);
  } catch (error) {
    console.error("[local] request failed", error);
    if (!res.headersSent) res.writeHead(500, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ error: "internal_error" }));
  }
});

server.listen(port, host, () => {
  console.error(`aabhushan-erp-mcp-server listening on http://${host}:${port}/mcp`);
});
