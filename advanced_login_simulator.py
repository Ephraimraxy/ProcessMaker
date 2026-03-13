import urllib.request
import urllib.parse
import http.cookiejar
import re
import json

URL = "http://127.0.0.1:8000"
LOGIN_URL = f"{URL}/login"
DEBUG_URL = f"{URL}/debug-session"

def debug_login():
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    urllib.request.install_opener(opener)

    print(f"--- Step 1: GET {LOGIN_URL} ---")
    try:
        with urllib.request.urlopen(LOGIN_URL) as response:
            html = response.read().decode('utf-8')
            print(f"Status: {response.getcode()}")
            print("Cookies:")
            for c in cj:
                print(f"  {c.name} = {c.value[:15]}... (Domain: {c.domain}, Secure: {c.secure})")
                
            token = ""
            token_match = re.search(r'name="_token" value="([^"]+)"', html)
            if token_match:
                token = token_match.group(1)

        print(f"\n--- Step 2: POST {LOGIN_URL} ---")
        post_data = {"username": "admin", "password": "admin"}
        if token:
            post_data["_token"] = token
            
        data = urllib.parse.urlencode(post_data).encode('utf-8')
        request = urllib.request.Request(LOGIN_URL, data=data, method='POST')
        
        class RedirectLogger(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, req, fp, code, msg, hdrs, newurl):
                print(f"\nREDIRECT: {code} -> {newurl}")
                print("Headers sent by Server:")
                for k, v in hdrs.items():
                    if 'set-cookie' in k.lower() or 'x-session-id' in k.lower():
                        print(f"  {k}: {v}")
                print("Cookies in jar:")
                for c in cj:
                    print(f"  {c.name} = {c.value[:15]}... (Domain: {c.domain}, Secure: {c.secure})")
                return super().redirect_request(req, fp, code, msg, hdrs, newurl)

        opener_with_log = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), RedirectLogger)
        
        with opener_with_log.open(request) as response:
            print(f"\n--- Step 3: Final Response ---")
            print(f"Final Status: {response.getcode()} at URL: {response.geturl()}")
            print("Final Cookies:")
            for c in cj:
                print(f"  {c.name} = {c.value[:15]}... (Domain: {c.domain}, Secure: {c.secure})")

        print(f"\n--- Step 4: GET {DEBUG_URL} ---")
        try:
            with opener_with_log.open(DEBUG_URL) as response:
                payload = response.read().decode('utf-8')
                try:
                    debug_json = json.loads(payload)
                    print("\nDebug JSON Output:")
                    print(json.dumps(debug_json, indent=2))
                except:
                    pass
        except Exception as e:
            print(f"Failed: {e}")

    except Exception as e:
        print(f"Unexpected Error: {e}")

if __name__ == "__main__":
    debug_login()
