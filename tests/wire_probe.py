import argparse
from concurrent.futures import ThreadPoolExecutor
import http.server
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile
import threading
import uuid


def run(binary, php, model, concurrency, provider_error):
    package = Path(__file__).resolve().parent.parent
    runtime = package / ".runtime"
    runtime.mkdir(mode=0o700, exist_ok=True)
    markers = {"stage-request-" + uuid.uuid4().hex: "stage-response-" + uuid.uuid4().hex for _ in range(concurrency)}
    captured = []
    failures = []

    class Handler(http.server.BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def do_POST(self):
            try:
                size = int(self.headers.get("Content-Length", 0))
                if not 0 < size <= 1048576 or self.headers.get("Content-Encoding"):
                    raise ValueError("Unexpected request encoding or size")
                request = json.loads(self.rfile.read(size))
                captured.append(request)
                matching = [value for key, value in markers.items() if key in json.dumps(request)]
                if len(matching) != 1:
                    raise ValueError("Missing or cross-request source context")
                response_marker = matching[0]
                if provider_error:
                    self.send_response(400)
                    self.send_header("Content-Type", "application/json")
                    self.end_headers()
                    self.wfile.write(json.dumps({"error": {"message": response_marker}}).encode())
                    return
                message = {
                    "id": "msg_probe",
                    "type": "message",
                    "role": "assistant",
                    "status": "completed",
                    "content": [{"type": "output_text", "text": json.dumps({"answer": response_marker}), "annotations": []}],
                }
                response = {
                    "id": "resp_probe",
                    "object": "response",
                    "status": "completed",
                    "output": [message],
                    "usage": {"input_tokens": 10, "output_tokens": 10, "total_tokens": 20},
                }
                events = [
                    ("response.created", {"response": {"id": "resp_probe", "status": "in_progress"}}),
                    ("response.output_item.added", {"output_index": 0, "item": dict(message, content=[])}),
                    ("response.output_text.delta", {"item_id": "msg_probe", "output_index": 0, "content_index": 0, "delta": message["content"][0]["text"]}),
                    ("response.output_item.done", {"output_index": 0, "item": message}),
                    ("response.completed", {"response": response}),
                ]
                self.send_response(200)
                self.send_header("Content-Type", "text/event-stream")
                self.end_headers()
                for kind, body in events:
                    self.wfile.write(("event: " + kind + "\ndata: " + json.dumps(dict(type=kind, **body)) + "\n\n").encode())
                self.wfile.flush()
            except Exception as error:
                failures.append(type(error).__name__)
                self.send_error(500)

    with tempfile.TemporaryDirectory(prefix="wire-", dir=runtime) as temporary:
        root = Path(temporary)
        (root / "work").mkdir(mode=0o700)
        (root / "codex").mkdir(mode=0o700)
        (root / "codex" / "config.toml").write_text('developer_instructions = "UNEXPECTED_USER_CONFIG_SENTINEL"\n')
        specifications = []
        for request_marker in markers:
            specification = json.loads(subprocess.check_output([
                php, str(package / "tests" / "wire-command.php"), binary, str(root), model, request_marker,
            ], timeout=10))
            if request_marker in json.dumps(specification["command"]):
                raise RuntimeError("Conversation leaked into arguments")
            specifications.append(specification)

        server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        server_thread = threading.Thread(target=server.serve_forever, daemon=True)
        server_thread.start()
        overrides = {
            "model_provider": "stage_probe",
            "model_providers.stage_probe.name": "Local synthetic probe",
            "model_providers.stage_probe.base_url": "http://127.0.0.1:" + str(server.server_port),
            "model_providers.stage_probe.wire_api": "responses",
            "model_providers.stage_probe.requires_openai_auth": False,
            "model_providers.stage_probe.request_max_retries": 0,
            "model_providers.stage_probe.stream_max_retries": 0,
        }
        command = specifications[0]["command"][:-1]
        for key, value in overrides.items():
            command.extend(["-c", key + "=" + json.dumps(value)])
        command.append("-")

        def complete(specification):
            return subprocess.run(command, input=specification["input"], text=True, capture_output=True,
                                  cwd=root / "work", env=specification["environment"], timeout=25)

        try:
            with ThreadPoolExecutor(max_workers=concurrency) as pool:
                results = list(pool.map(complete, specifications))
        finally:
            server.shutdown()
            server.server_close()
            server_thread.join(timeout=2)

        if failures or len(captured) != concurrency:
            raise RuntimeError("Synthetic requests did not all reach the local provider")
        for result, response_marker in zip(results, markers.values()):
            if (result.returncode == 0) == provider_error:
                raise RuntimeError("Unexpected CLI exit status " + str(result.returncode))
            if response_marker not in result.stdout:
                raise RuntimeError("Expected answer or provider failure missing")
            if any(other in result.stdout for other in markers.values() if other != response_marker):
                raise RuntimeError("Cross-request answer leakage")
        for request in captured:
            if request.get("tools") not in (None, []) or request.get("store") is not False:
                raise RuntimeError("Tools or provider response storage enabled")
            encoded = json.dumps(request)
            if "UNEXPECTED_USER_CONFIG_SENTINEL" in encoded:
                raise RuntimeError("User configuration reached the model")
            if not any(marker in encoded for marker in markers):
                raise RuntimeError("Source context missing")
            for item in request.get("input", []):
                if item.get("role") != "user" and any(marker in json.dumps(item) for marker in markers):
                    raise RuntimeError("Source context promoted to trusted instructions")
        for file in root.rglob("*"):
            if file.is_file() and not file.is_symlink():
                data = file.read_bytes()
                if any(marker.encode() in data for marker in [*markers, *markers.values()]):
                    raise RuntimeError("Conversation marker persisted in " + str(file.relative_to(root)))
        content_tables = {"threads", "logs", "stage1_outputs", "queued_items", "queued_thread_revisions", "thread_goals"}
        for file in (root / "codex").glob("*.sqlite"):
            connection = sqlite3.connect("file:" + str(file) + "?mode=rw", uri=True)
            try:
                connection.execute("PRAGMA query_only=ON")
                tables = {row[0] for row in connection.execute("SELECT name FROM sqlite_master WHERE type='table'")}
                for table in tables & content_tables:
                    if connection.execute('SELECT count(*) FROM "' + table + '"').fetchone()[0] != 0:
                        raise RuntimeError("Unexpected content rows in " + file.name + "/" + table)
            except sqlite3.Error as error:
                raise RuntimeError("Cannot inspect " + file.name + ": " + str(error)) from error
            finally:
                connection.close()
        version = subprocess.check_output([binary, "--version"], text=True, timeout=5).strip()
        print(json.dumps({"cli": version, "model": model, "requests": len(captured), "tools": 0,
                          "response_storage": False, "content_markers_on_disk": 0, "content_rows": 0, "concurrency": concurrency,
                          "scenario": "provider-error" if provider_error else "success", "provider": "local synthetic fixture"}))


parser = argparse.ArgumentParser()
parser.add_argument("--binary", default=shutil.which("codex"), required=shutil.which("codex") is None)
parser.add_argument("--php", default=shutil.which("php"), required=shutil.which("php") is None)
parser.add_argument("--model", required=True)
parser.add_argument("--concurrency", type=int, choices=range(1, 4), default=1)
arguments = parser.parse_args()
for provider_error in [False, True]:
    run(os.path.abspath(arguments.binary), os.path.abspath(arguments.php), arguments.model, arguments.concurrency, provider_error)
