{{flutter_js}}
{{flutter_build_config}}

// The generated Flutter service worker now unregisters itself. Aabhushan uses
// its own service worker (registered from index.html) so the app stays
// installable and its offline shell remains available.
_flutter.loader.load();
