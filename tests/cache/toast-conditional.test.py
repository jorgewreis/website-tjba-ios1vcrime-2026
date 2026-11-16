"""HTTP regression for toast catalog validators; uses only a disposable local fixture."""
import http.client
import pathlib
import shutil
import socket
import subprocess
import tempfile
import time

root = pathlib.Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix="toast-cache-test-") as tmp:
    base = pathlib.Path(tmp)
    endpoint = base / "public/api/config/toast-messages.php"
    endpoint.parent.mkdir(parents=True)
    shutil.copyfile(root / "public/api/config/toast-messages.php", endpoint)
    (base / "config").mkdir()
    (base / "config/toast_messages.php").write_text(
        "<?php return ['messages' => ['sample' => ['message' => 'synthetic']]];", encoding="utf-8"
    )
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    proc = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", str(base / "public")],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )
    try:
        def request(headers=None, method="GET"):
            conn = http.client.HTTPConnection("127.0.0.1", port, timeout=2)
            try:
                conn.request(method, "/api/config/toast-messages.php", headers=headers or {})
                response = conn.getresponse()
                body = response.read()
                return response.status, dict(response.getheaders()), body
            finally:
                conn.close()

        for _ in range(50):
            if proc.poll() is not None:
                raise RuntimeError("PHP server exited before becoming ready")
            try:
                status, headers, body = request()
                break
            except (OSError, http.client.HTTPException):
                time.sleep(0.1)
        else:
            raise RuntimeError("PHP server did not become ready")
        assert status == 200 and body, "initial representation missing"
        etag = headers["ETag"]
        current = headers["Last-Modified"]
        future = "Wed, 21 Oct 2099 07:28:00 GMT"
        assert request({"If-None-Match": '"different"', "If-Modified-Since": future})[0] == 200
        status304, headers304, body304 = request({"If-None-Match": etag, "If-Modified-Since": "Wed, 21 Oct 2015 07:28:00 GMT"})
        assert status304 == 304 and body304 == b""
        assert headers304["ETag"] == etag and headers304["Last-Modified"] == current
        assert headers304["Cache-Control"] == headers["Cache-Control"]
        assert request({"If-Modified-Since": current})[0] == 304
        assert request({"If-Modified-Since": "Wed, 21 Oct 2015 07:28:00 GMT"})[0] == 200
        assert request({"If-None-Match": '"other", W/' + etag})[0] == 304
        assert request({"If-None-Match": "*"})[0] == 304
        assert request({"If-None-Match": '"other, *, value"', "If-Modified-Since": future})[0] == 200
        assert request({"If-None-Match": "", "If-Modified-Since": future})[0] == 200
        assert request({"If-None-Match": "invalid", "If-Modified-Since": future})[0] == 200
        head_status, head_headers, head_body = request(method="HEAD")
        assert head_status == 200 and head_body == b"" and head_headers["ETag"] == etag
        status405, headers405, _ = request({"If-None-Match": etag}, method="POST")
        assert status405 == 405 and headers405["Allow"] == "GET, HEAD"
        print("PASS toast cache HTTP: ETag precedence, weak/list/wildcard and date fallback")
    finally:
        proc.terminate()
        try:
            proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=5)
