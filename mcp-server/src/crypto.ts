/**
 * AES-256-GCM encryption for cached ERP session tokens. The key is derived from
 * MCP_TOKEN_SECRET so any sufficiently long random string works as the secret.
 */
import { createCipheriv, createDecipheriv, createHash, randomBytes, timingSafeEqual } from "node:crypto";

function deriveKey(secret: string): Buffer {
  if (secret.length < 16) throw new Error("MCP_TOKEN_SECRET must be at least 16 characters.");
  return createHash("sha256").update(secret, "utf8").digest();
}

export function encryptText(plain: string, secret: string): string {
  const key = deriveKey(secret);
  const iv = randomBytes(12);
  const cipher = createCipheriv("aes-256-gcm", key, iv);
  const encrypted = Buffer.concat([cipher.update(plain, "utf8"), cipher.final()]);
  const tag = cipher.getAuthTag();
  return Buffer.concat([iv, tag, encrypted]).toString("base64");
}

export function decryptText(payload: string, secret: string): string {
  const key = deriveKey(secret);
  const raw = Buffer.from(payload, "base64");
  if (raw.length < 29) throw new Error("Cipher text is too short.");
  const iv = raw.subarray(0, 12);
  const tag = raw.subarray(12, 28);
  const data = raw.subarray(28);
  const decipher = createDecipheriv("aes-256-gcm", key, iv);
  decipher.setAuthTag(tag);
  return Buffer.concat([decipher.update(data), decipher.final()]).toString("utf8");
}

export function sha256Hex(value: string): string {
  return createHash("sha256").update(value, "utf8").digest("hex");
}

export function safeEqual(a: string, b: string): boolean {
  const bufA = Buffer.from(a, "utf8");
  const bufB = Buffer.from(b, "utf8");
  if (bufA.length !== bufB.length) return false;
  return timingSafeEqual(bufA, bufB);
}

export function randomToken(prefix = "mcp_"): string {
  return prefix + randomBytes(32).toString("hex");
}
