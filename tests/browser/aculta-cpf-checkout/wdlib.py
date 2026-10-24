import json, os, socket, subprocess, tempfile, time, urllib.error, urllib.request

FIREFOX = os.environ.get("FIREFOX", "/usr/bin/firefox-esr")
GECKODRIVER = os.environ.get("GECKODRIVER", "geckodriver")
TIMEOUT = int(os.environ.get("TIMEOUT", "30"))


class WDError(Exception):
    def __init__(self, code, msg):
        super().__init__(msg)
        self.code = code


class Driver:
    def __init__(self, prefs=None, bidi=False):
        s = socket.socket(); s.bind(("127.0.0.1", 0)); self.port = s.getsockname()[1]; s.close()
        self.base = f"http://127.0.0.1:{self.port}"
        self.profile = tempfile.mkdtemp(prefix="aculta-ff-", dir="/tmp")
        self.proc = subprocess.Popen([GECKODRIVER, "--port", str(self.port)],
                                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.session = None
        for _ in range(75):
            try:
                self.req("GET", "/status", timeout=2); break
            except Exception:
                time.sleep(0.2)
        opts = {"binary": FIREFOX, "args": ["-profile", self.profile, "-no-remote"]}
        if prefs:
            opts["prefs"] = prefs
        always_match = {
            "browserName": "firefox",
            "acceptInsecureCerts": True,
            "moz:firefoxOptions": opts,
        }
        if bidi:
            always_match["webSocketUrl"] = True
        caps = {"capabilities": {"alwaysMatch": always_match}}
        session_info = self.req("POST", "/session", caps, timeout=120)
        self.session = session_info["sessionId"]
        self.capabilities = session_info.get("capabilities", {})
        self.websocket_url = self.capabilities.get("webSocketUrl")
        if bidi and not self.websocket_url:
            raise RuntimeError("Geckodriver não forneceu o endpoint WebDriver BiDi.")
        self.req("POST", f"/session/{self.session}/timeouts",
                 {"pageLoad": TIMEOUT * 1000, "script": TIMEOUT * 1000})

    def req(self, method, path, body=None, timeout=60):
        data = json.dumps(body).encode() if body is not None else None
        r = urllib.request.Request(self.base + path, data=data, method=method,
                                   headers={"Content-Type": "application/json"})
        try:
            with urllib.request.urlopen(r, timeout=timeout) as resp:
                return json.loads(resp.read())["value"]
        except urllib.error.HTTPError as e:
            v = json.loads(e.read())["value"]
            raise WDError(v.get("error", "erro"), v.get("message", "erro"))

    def get(self, url):
        return self.req("POST", f"/session/{self.session}/url", {"url": url}, timeout=TIMEOUT + 60)

    def js(self, script, args=None):
        return self.req("POST", f"/session/{self.session}/execute/sync",
                        {"script": script, "args": args or []})

    def find(self, css):
        return self.req("POST", f"/session/{self.session}/element",
                        {"using": "css selector", "value": css})

    def exists(self, css):
        try:
            self.find(css); return True
        except WDError:
            return False

    def type(self, css, text):
        el = self.find(css)
        self.req("POST", f"/session/{self.session}/element/{el['element-6066-11e4-a52e-4f735466cecf']}/value",
                 {"text": text})

    def click(self, css):
        el = self.find(css)
        self.req("POST", f"/session/{self.session}/element/{el['element-6066-11e4-a52e-4f735466cecf']}/click", {})

    def submit(self, css):
        el = self.find(css)
        self.req("POST", f"/session/{self.session}/element/{el['element-6066-11e4-a52e-4f735466cecf']}/submit", {})

    def title(self):
        return self.req("GET", f"/session/{self.session}/title") or ""

    def url(self):
        return self.req("GET", f"/session/{self.session}/url") or ""

    def source(self):
        return self.req("GET", f"/session/{self.session}/source") or ""

    def new_tab(self):
        return self.req("POST", f"/session/{self.session}/window/new", {"type": "tab"})

    def switch(self, handle):
        self.req("POST", f"/session/{self.session}/window", {"handle": handle})

    def handles(self):
        return self.req("GET", f"/session/{self.session}/window/handles")

    def close(self):
        try:
            if self.session:
                self.req("DELETE", f"/session/{self.session}")
        except Exception:
            pass
        self.proc.terminate()
        try:
            self.proc.wait(timeout=10)
        except Exception:
            self.proc.kill()
        import shutil; shutil.rmtree(self.profile, ignore_errors=True)
