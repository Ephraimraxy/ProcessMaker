import urllib.request
import urllib.parse
import http.cookiejar
import re
import json

URL = "https://bbmeet.site"
LOGIN_URL = f"{URL}/login"
DEBUG_URL = f"{URL}/debug-session"

def debug_login():
    # Setup cookie jar
    cj = http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    urllib.request.install_opener(opener)

    print(f"--- Step 1: GET {LOGIN_URL} ---")
    try:
        with urllib.request.urlopen(LOGIN_URL) as response:
            html = response.read().decode('utf-8')
            print(f"Status: {response.getcode()}")
            print(f"Initial Cookies: {[c.name for c in cj]}")
            
            # Extract CSRF token (if present)
            token = ""
            token_match = re.search(r'name="_token" value="([^"]+)"', html)
            if token_match:
                token = token_match.group(1)
                print(f"CSRF Token Found: {token}")
            else:
                print("CSRF Token NOT found (this is expected if VerifyCsrfToken is disabled)")

        print(f"\n--- Step 2: POST {LOGIN_URL} ---")
        post_data = {
            "username": "admin",
            "password": "admin"
        }
        if token:
            post_data["_token"] = token
            
        data = urllib.parse.urlencode(post_data).encode('utf-8')

        # We manually follow redirects to capture cookies at each step
        request = urllib.request.Request(LOGIN_URL, data=data, method='POST')
        
        # Custom opener to print redirect info
        class RedirectLogger(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, req, fp, code, msg, hdrs, newurl):
                print(f"REDIRECT: {code} -> {newurl}")
                print(f"Cookies at redirect: {[c.name for c in cj]}")
                return super().redirect_request(req, fp, code, msg, hdrs, newurl)

        opener_with_log = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), RedirectLogger)
        
        with opener_with_log.open(request) as response:
            print(f"\nFinal Response Status: {response.getcode()}")
            print(f"Final Response URL: {response.geturl()}")
            print(f"Final Cookies: {[c.name for c in cj]}")

        print(f"\n--- Step 4: GET {DEBUG_URL} ---")
        try:
            with opener_with_log.open(DEBUG_URL) as response:
                payload = response.read().decode('utf-8')
                try:
                    debug_json = json.loads(payload)
                    print("Debug JSON Output:")
                    print(json.dumps(debug_json, indent=2))
                except:
                    print(f"Debug output was not JSON: {payload[:200]}...")
        except Exception as e:
            print(f"Failed to fetch debug info: {e}")

    except Exception as e:
        print(f"Unexpected Error: {e}")

if __name__ == "__main__":
    debug_login()
