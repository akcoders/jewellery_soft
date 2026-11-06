"""Dependency-free HTTP checks in an isolated checkout; no production changes."""
import http.client
import json
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import unittest

PHP = sys.argv.pop(1) if len(sys.argv) > 1 else "php"
ROOT = Path(__file__).resolve().parents[1]


class ServiceSuspensionTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory()
        cls.root = Path(cls.temp.name)
        for name in ["public/index.php", "index.php", "spark",
                     "public/service-suspended.php", "public/service-suspended.flag",
                     "app/Support/service_suspension.php", "app/Views/service_suspended.html",
                     "app/Views/service_suspended_worker.js", "scripts/service-access.php"]:
            target = cls.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / name, target)
        # If the gate ever boots CI while suspended this sentinel will run.
        config = cls.root / "app/Config"
        config.mkdir()
        (config / "Paths.php").write_text(
            '<?php http_response_code(418); echo "FRAMEWORK_BOOTED"; exit;'
        )
        # The PHP development server ignores .htaccess. Send all requests to
        # the front controller to exercise the PHP gate, not Apache rewrites.
        cls.router = cls.root / "router.php"
        cls.router.write_text('<?php require __DIR__ . "/public/index.php";')
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            cls.port = sock.getsockname()[1]
        cls.server = subprocess.Popen(
            [PHP, "-S", f"127.0.0.1:{cls.port}", "-t", str(cls.root / "public"), str(cls.router)],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        for _ in range(100):
            try:
                with socket.create_connection(("127.0.0.1", cls.port), timeout=.1):
                    break
            except OSError:
                if cls.server.poll() is not None:
                    raise RuntimeError("PHP HTTP server failed to start")
                time.sleep(.03)
        else:
            raise RuntimeError("PHP HTTP server startup timed out")

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate()
        cls.server.wait(timeout=5)
        cls.temp.cleanup()

    def request(self, path, method="GET", headers=None):
        conn = http.client.HTTPConnection("127.0.0.1", self.port, timeout=3)
        conn.request(method, path, headers=headers or {})
        response = conn.getresponse()
        result = response.status, dict(response.getheaders()), response.read()
        conn.close()
        return result

    def test_web_portals_assets_and_unknown_routes_stop_before_boot(self):
        for path in ["/", "/admin/login", "/customer", "/pwa/", "/pwa/index.html",
                     "/analytics/index.html", "/uploads/order.jpg", "/does-not-exist",
                     "/public/admin", "/index.php/admin"]:
            with self.subTest(path=path):
                status, headers, body = self.request(path)
                self.assertEqual(status, 503)
                self.assertIn(b"Service suspended", body)
                self.assertNotIn(b"FRAMEWORK_BOOTED", body)
                self.assertIn("no-store", headers["Cache-Control"])
                self.assertNotIn("Set-Cookie", headers)

    def test_all_api_methods_and_old_tokens_are_denied(self):
        for method in ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"]:
            for path in ["/api/mobile/auth/login", "/api/mobile/orders",
                         "/public/index.php/api/mobile/orders", "/index.php/api/orders"]:
                with self.subTest(method=method, path=path):
                    status, headers, body = self.request(path, method, {"Authorization": "Bearer old-token"})
                    self.assertEqual(status, 503)
                    data = json.loads(body)
                    self.assertIs(data["success"], False)
                    self.assertEqual(data["code"], "SERVICE_SUSPENDED")
                    self.assertIn("Service suspended", data["message"])
                    self.assertIsNone(data["data"])
                    self.assertIn("application/json", headers["Content-Type"])

    def test_json_ajax_and_head(self):
        for headers in [{"Accept": "application/json"}, {"X-Requested-With": "XMLHttpRequest"}]:
            self.assertEqual(json.loads(self.request("/admin/save", "POST", headers)[2])["code"], "SERVICE_SUSPENDED")
        status, _, body = self.request("/api/orders", "HEAD")
        self.assertEqual((status, body), (503, b""))

    def test_worker_updates_are_javascript_not_error_pages(self):
        for path in ["/pwa/aabhushan_app_sw.js?v=old", "/public/pwa/flutter_service_worker.js",
                     "/analytics/sw.js", "/customer-portal-sw.js"]:
            status, headers, body = self.request(path)
            self.assertEqual(status, 200)
            self.assertIn("application/javascript", headers["Content-Type"])
            self.assertIn(b"self.skipWaiting()", body)
            self.assertIn(b"client.navigate", body)
            self.assertNotIn(b"<!doctype", body)

    def test_cli_jobs_pause_and_resume_takes_effect_without_restart(self):
        result = subprocess.run([PHP, str(self.root / "spark"), "mobile:run-notification-cycle"], capture_output=True)
        self.assertEqual(result.returncode, 1)
        self.assertIn(b"Service suspended", result.stderr)
        control = [PHP, str(self.root / "scripts/service-access.php")]
        try:
            self.assertEqual(subprocess.check_output(control + ["resume"]), b"ACTIVE\n")
            self.assertEqual(self.request("/")[0], 418)
            self.assertEqual(subprocess.check_output(control + ["status"]), b"ACTIVE\n")
        finally:
            subprocess.check_call(control + ["suspend"], stdout=subprocess.DEVNULL)
        self.assertEqual(self.request("/")[0], 503)
        self.assertEqual(subprocess.check_output(control + ["suspend"]), b"SUSPENDED\n")


if __name__ == "__main__":
    unittest.main(verbosity=2)
