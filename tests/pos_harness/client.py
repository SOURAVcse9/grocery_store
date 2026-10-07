import re, json, urllib.request, urllib.parse, http.cookiejar, subprocess
BASE='http://127.0.0.1:8099'
class C:
    def __init__(s):
        s.cj=http.cookiejar.CookieJar()
        s.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(s.cj), NoRedirect())
        s.csrf=None
    def req(s,path,data=None,headers=None):
        url=BASE+path if path.startswith('/') else path
        body=urllib.parse.urlencode(data).encode() if data is not None else None
        r=urllib.request.Request(url,data=body,headers=headers or {})
        try: resp=s.op.open(r); code=resp.status; txt=resp.read().decode('utf8','replace'); loc=None
        except urllib.error.HTTPError as e: code=e.code; txt=e.read().decode('utf8','replace'); loc=e.headers.get('Location')
        m=re.search(r'name="csrf_token" value="([a-f0-9]+)"',txt)
        if m: s.csrf=m.group(1)
        return code,txt,loc
    def login(s,user,pw='Pass#123'):
        s.req('/admin/login.php')
        c,t,l=s.req('/admin/login.php',{'csrf_token':s.csrf,'identity':user,'password':pw})
        return c,l
    def post(s,path,data):
        d=dict(data); d['csrf_token']=s.csrf
        return s.req(path,d)
    def jpost(s,path,data):
        c,t,l=s.post(path,data)
        try: return json.loads(t)
        except Exception: return {'_raw':t[:300],'_code':c}
    def jget(s,path):
        c,t,l=s.req(path)
        try: return json.loads(t)
        except Exception: return {'_raw':t[:300],'_code':c}
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(s,*a,**k): return None
def sql(q):
    return subprocess.run(['mariadb','-uroot','groco_test','-N','-e',q],capture_output=True,text=True).stdout.strip()
