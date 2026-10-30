import { app } from "../src/app.js";

export const config = { runtime: "nodejs", maxDuration: 60 };

export default {
  fetch(request: Request): Promise<Response> {
    return app.fetch(request);
  }
};
