(function () {
  "use strict";

  const channel = "aabhushan_pwa_bridge";
  const installChannel = "aabhushan_pwa_install";
  let oneSignal = null;
  let initialized = false;
  let listenersBound = false;
  let deferredInstallPrompt = null;

  function send(message) {
    window.postMessage(
      JSON.stringify(Object.assign({ channel, sender: "javascript" }, message)),
      window.location.origin
    );
  }

  function sendInstall(message) {
    window.postMessage(
      JSON.stringify(
        Object.assign({ channel: installChannel, sender: "javascript" }, message)
      ),
      window.location.origin
    );
  }

  function installAvailable() {
    return Boolean(deferredInstallPrompt);
  }

  window.addEventListener("beforeinstallprompt", function (event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    sendInstall({ kind: "availability", available: true });
  });

  window.addEventListener("appinstalled", function () {
    deferredInstallPrompt = null;
    sendInstall({ kind: "availability", available: false });
  });

  function clean(value) {
    return value === undefined || value === null ? null : value;
  }

  function status(error) {
    const supported = Boolean(
      oneSignal && oneSignal.Notifications.isPushSupported()
    );
    const push = oneSignal ? oneSignal.User.PushSubscription : null;
    return {
      initialized,
      permissionGranted: Boolean(
        oneSignal && oneSignal.Notifications.permission
      ),
      canRequestPermission:
        supported &&
        typeof Notification !== "undefined" &&
        Notification.permission === "default",
      optedIn: Boolean(push && push.optedIn),
      subscriptionId: clean(push && push.id),
      pushToken: clean(push && push.token),
      externalUserId: clean(oneSignal && oneSignal.User.externalId),
      error: error ? String(error) : null,
    };
  }

  function emitStatus(error) {
    send({ kind: "event", event: "status", data: status(error) });
  }

  function notificationData(event) {
    const notification = (event && event.notification) || {};
    const data = Object.assign(
      {},
      notification.additionalData || notification.data || {}
    );
    if (notification.notificationId) {
      data.notification_id = notification.notificationId;
    }
    return data;
  }

  function bindListeners() {
    if (!oneSignal || listenersBound) return;
    listenersBound = true;

    oneSignal.Notifications.addEventListener("permissionChange", function () {
      emitStatus();
    });
    oneSignal.User.PushSubscription.addEventListener("change", function () {
      emitStatus();
    });
    oneSignal.User.addEventListener("change", function () {
      emitStatus();
    });
    oneSignal.Notifications.addEventListener("foregroundWillDisplay", function () {
      send({ kind: "event", event: "notificationForeground", data: {} });
    });
    oneSignal.Notifications.addEventListener("click", function (event) {
      send({
        kind: "event",
        event: "notificationOpened",
        data: notificationData(event),
      });
    });
  }

  function consumeLaunchQuery() {
    const params = new URLSearchParams(window.location.search);
    const data = {};
    ["order_id", "task_id", "type", "screen"].forEach(function (key) {
      const value = params.get(key);
      if (value) data[key] = value;
    });
    if (!Object.keys(data).length) return;

    send({ kind: "event", event: "notificationOpened", data });
    ["order_id", "task_id", "type", "screen"].forEach(function (key) {
      params.delete(key);
    });
    const query = params.toString();
    window.history.replaceState(
      {},
      document.title,
      window.location.pathname + (query ? "?" + query : "") + window.location.hash
    );
  }

  async function initialize(appId) {
    if (initialized && oneSignal) return status();

    window.OneSignalDeferred = window.OneSignalDeferred || [];
    return new Promise(function (resolve, reject) {
      window.OneSignalDeferred.push(async function (sdk) {
        try {
          oneSignal = sdk;
          const workerUrl = new URL(
            "push/onesignal/OneSignalSDKWorker.js",
            document.baseURI
          );
          const workerScope = new URL("push/onesignal/", document.baseURI);
          await oneSignal.init({
            appId,
            allowLocalhostAsSecureOrigin:
              window.location.hostname === "localhost" ||
              window.location.hostname === "127.0.0.1",
            autoResubscribe: true,
            notificationClickHandlerMatch: "origin",
            notificationClickHandlerAction: "navigate",
            serviceWorkerPath: workerUrl.pathname,
            serviceWorkerParam: { scope: workerScope.pathname },
          });
          initialized = true;
          bindListeners();
          oneSignal.Notifications.setDefaultTitle("Aabhushan ERP");
          oneSignal.Notifications.setDefaultUrl(
            new URL("./", document.baseURI).href
          );
          consumeLaunchQuery();
          resolve(status());
        } catch (error) {
          reject(error);
        }
      });
    });
  }

  async function run(command, payload) {
    if (command === "init") {
      return initialize(String(payload.appId || ""));
    }
    if (!initialized || !oneSignal) {
      throw new Error("OneSignal web push is not initialized.");
    }
    if (command === "login") {
      await oneSignal.login(String(payload.externalId || ""));
      oneSignal.User.addTags({
        email: String(payload.email || ""),
        name: String(payload.name || ""),
        client: "pwa",
      });
      if (oneSignal.Notifications.permission) {
        await oneSignal.User.PushSubscription.optIn();
      }
      return status();
    }
    if (command === "requestPermission") {
      if (!oneSignal.Notifications.isPushSupported()) {
        throw new Error("This browser does not support web push notifications.");
      }
      await oneSignal.Notifications.requestPermission();
      if (oneSignal.Notifications.permission) {
        await oneSignal.User.PushSubscription.optIn();
      }
      return status();
    }
    if (command === "logout") {
      await oneSignal.logout();
      return status();
    }
    if (command === "status") {
      return status();
    }
    throw new Error("Unknown PWA bridge command.");
  }

  window.addEventListener("message", async function (event) {
    if (
      event.source !== window ||
      event.origin !== window.location.origin ||
      typeof event.data !== "string"
    ) {
      return;
    }
    let message;
    try {
      message = JSON.parse(event.data);
    } catch (_) {
      return;
    }
    if (!message || message.sender !== "dart" || message.kind !== "command") {
      return;
    }

    if (message.channel === installChannel) {
      let installed = false;
      if (message.command === "install" && deferredInstallPrompt) {
        const prompt = deferredInstallPrompt;
        deferredInstallPrompt = null;
        await prompt.prompt();
        const choice = await prompt.userChoice;
        installed = Boolean(choice && choice.outcome === "accepted");
      }
      sendInstall({
        kind: "response",
        id: message.id,
        data: { available: installAvailable(), installed },
      });
      return;
    }

    if (message.channel !== channel) return;

    try {
      const result = await run(
        String(message.command || ""),
        message.payload || {}
      );
      send({ kind: "response", id: message.id, ok: true, data: result });
      emitStatus();
    } catch (error) {
      send({
        kind: "response",
        id: message.id,
        ok: false,
        error: error && error.message ? error.message : String(error),
      });
      emitStatus(error && error.message ? error.message : String(error));
    }
  });
})();
