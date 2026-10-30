# Hostinger deploy: Aabhushan ERP MCP server

Domain: `https://aabhushan-mcp.thomsontnt.com`
ERP: `https://aabhushan.webignitors.in/public`

---

## 1. Build configuration

| Field | Value |
|---|---|
| Deploy method | Upload files (`mcp-server.zip`) |
| Framework / preset | Other (ya Express, agar "Other" na ho) |
| Node.js version | `22.x` (ya `20.x`, 20.3 se kam nahi) |
| Package manager | `npm` |
| Root directory | `./` (zip ka root hi app ka root hai) |

## 2. Build and output settings

| Field | Value |
|---|---|
| Install command | `npm install` |
| Build command | Khaali chhodo (`dist/` zip mein pehle se hai). Required ho to `npm install` likho. |
| Output directory | Khaali / default (`./`) |
| Entry file | `dist/src/local.js` |
| Start command | `npm start` |

Agar entry file "output directory" ke relative maangi jaaye: output directory `dist` rakho aur entry file `src/local.js`.

## 3. Environment variables

| Key | Value |
|---|---|
| `ERP_BASE_URL` | `https://aabhushan.webignitors.in/public` |
| `MCP_ERP_SHARED_SECRET` | `openssl rand -hex 32` ka pehla output (ERP `.env` mein bhi same) |
| `MCP_TOKEN_SECRET` | `openssl rand -hex 32` ka doosra output |
| `MCP_DATABASE_URL` | `mysql://DB_USER:DB_PASSWORD@localhost:3306/DB_NAME` |
| `MCP_PUBLIC_URL` | `https://aabhushan-mcp.thomsontnt.com/mcp` |

In rows ko **delete** karo (X): `MCP_ALLOWED_HOSTS`, `ERP_DATABASE_URL`, `MCP_BOOTSTRAP_KEY`, `MCP_BOOTSTRAP_ADMIN_USER_ID`, `PORT`.

Notes:
- `localhost` tabhi chalega jab database isi Hostinger account (thomsontnt.com wala) mein bana ho.
- DB password mein `@ : / # ? %` ho to URL toot jaata hai: sirf letters + numbers rakho, ya encode karo (`@` → `%40`, `#` → `%23`).

---

## 4. ERP side (aabhushan.webignitors.in)

1. `McpSessionController.php` + `Routes.php` ka `mcp/session` route ERP server pe deploy karo.
2. ERP ki `.env` mein:
   ```
   mcp.sharedSecret = '<MCP_ERP_SHARED_SECRET wali same value>'
   ```

## 5. Zip banana (Mac)

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/recurpost_old/mcp-server
npm ci && npm run build
zip -r ~/Desktop/mcp-server.zip . -x "node_modules/*" ".env"
```

## 6a. API key: ERP admin panel se (recommended)

ERP → **MCP API Keys** (`/admin/mcp-keys`) → user, naam, scopes, expiry chuno → **Generate key** → key ek hi baar dikhegi, copy karke user ko do. Revoke bhi wahin se; MCP server 1 minute ke andar key band kar deta hai.

## 6. Deploy ke baad: tables + API key (Mac se)

1. hPanel → Databases → **Remote MySQL** → apne Mac ka IP allow karo.
2. `mcp-server/.env` mein (host = hPanel ka remote MySQL host, `localhost` nahi):
   ```
   MCP_DATABASE_URL=mysql://DB_USER:DB_PASSWORD@srvXXX.hstgr.io:3306/DB_NAME
   ```
3. Chalao:
   ```bash
   npm run migrate
   npm run create-key -- --name "Claude" --user 1 --scopes read,write --expires-days 365
   ```
   Key ek hi baar dikhegi, save kar lo. `--user` = ERP `admin_users.id`.

## 7. Test

```bash
curl https://aabhushan-mcp.thomsontnt.com/health

curl -s https://aabhushan-mcp.thomsontnt.com/mcp \
  -H "Authorization: Bearer mcp_..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

## 8. Claude mein connect

- URL: `https://aabhushan-mcp.thomsontnt.com/mcp`
- Header: `Authorization: Bearer <key>`

## Update kaise karein

Step 5 se naya zip banao → hPanel mein upload → app restart.
