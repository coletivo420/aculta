import json, os, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.request
from pathlib import Path

FIREFOX = os.environ.get("FIREFOX", "/usr/bin/firefox-esr")
GECKODRIVER = os.environ.get("GECKODRIVER", "geckodriver")
CHROME = os.environ.get("CHROME", "/usr/bin/google-chrome")
CHROMIUM = os.environ.get("CHROMIUM", "/usr/bin/chromium")
CHROMEDRIVER = os.environ.get("CHROMEDRIVER", "chromedriver")
TIMEOUT = int(os.environ.get("TIMEOUT", "30"))


class WDError(Exception):
    def __init__(self, code, msg):
        super().__init__(msg)
        self.code = code


class Driver:
    def __init__(self, prefs=None, bidi=False, headless=False, browser="firefox"):
        browser = browser.lower()
        if browser not in ("firefox", "chrome", "chromium"):
            raise ValueError("browser deve ser firefox, chrome ou chromium")
        s = socket.socket(); s.bind(("127.0.0.1", 0)); self.port = s.getsockname()[1]; s.close()
        self.base = f"http://127.0.0.1:{self.port}"
        profile_root = os.environ.get("ACULTA_FF_PROFILE_ROOT")
        if not profile_root:
            suite_out = os.environ.get("SUITE_OUT")
            if not suite_out:
                raise RuntimeError("Defina ACULTA_FF_PROFILE_ROOT ou SUITE_OUT para o perfil temporário.")
            profile_root = os.path.join(suite_out, "profiles")
        profile_root = os.path.realpath(profile_root)
        if os.path.commonpath((profile_root, "/tmp")) == "/tmp":
            raise RuntimeError("Perfis WebDriver não podem ficar em /tmp; use armazenamento persistente em $SUITE_OUT/profiles.")
        repository_root = Path(__file__).resolve().parents[3]
        if Path(profile_root).is_relative_to(repository_root):
            raise RuntimeError("Perfis WebDriver devem ficar fora do repositório.")
        os.makedirs(profile_root, mode=0o700, exist_ok=True)
        os.chmod(profile_root, 0o700)
        self.profile = tempfile.mkdtemp(prefix="aculta-ff-", dir=profile_root)
        self.browser = browser
        driver_binary = GECKODRIVER if browser == "firefox" else CHROMEDRIVER
        driver_args = [driver_binary, "--port", str(self.port)]
        if browser != "firefox":
            driver_args = [driver_binary, f"--port={self.port}"]
        self.proc = subprocess.Popen(driver_args,
                                     stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        self.session = None
        server_ready = False
        for _ in range(75):
            try:
                self.req("GET", "/status", timeout=2)
                server_ready = True
                break
            except Exception:
                if self.proc.poll() is not None:
                    break
                time.sleep(0.2)
        if not server_ready:
            exit_code = self.proc.poll()
            self.proc.terminate()
            self.proc.wait(timeout=5)
            shutil.rmtree(self.profile, ignore_errors=True)
            raise RuntimeError(f"WebDriver {browser} não iniciou em {self.base}/status (exit={exit_code}).")
        if browser == "firefox":
            args = ["-profile", self.profile, "-no-remote"]
            if headless:
                args.append("-headless")
            opts = {"binary": FIREFOX, "args": args}
            if prefs:
                opts["prefs"] = prefs
            browser_options = {"moz:firefoxOptions": opts}
            browser_name = "firefox"
        else:
            binary = CHROME if browser == "chrome" else CHROMIUM
            args = [f"--user-data-dir={self.profile}", "--no-first-run", "--no-default-browser-check"]
            if headless:
                args.extend(("--headless=new", "--disable-dev-shm-usage"))
            opts = {"binary": binary, "args": args}
            browser_options = {"goog:chromeOptions": opts}
            browser_name = "chrome"
        always_match = {
            "browserName": browser_name,
            "acceptInsecureCerts": True,
            **browser_options,
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
        shutil.rmtree(self.profile, ignore_errors=True)
