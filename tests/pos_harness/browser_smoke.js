const { JSDOM, ResourceLoader, VirtualConsole } = require('jsdom');
const fs = require('fs'), path = require('path'), http = require('http');
const html = fs.readFileSync('pos.html', 'utf8');
const cookie = fs.readFileSync('cookie.txt', 'utf8');
const BASE = 'http://127.0.0.1:8099/admin/pos/';
const WEBROOT = '/home/claude/work';
let results = [], errors = [];
const ok = (n, c, d) => { results.push([n, !!c, d]); console.log((c ? '  PASS  ' : '  FAIL  ') + n + (c ? '' : '  ' + (d || ''))); };

class Loader extends ResourceLoader {
  fetch(url) {
    if (/^https?:\/\/cdn/.test(url) || /theme\.js|custom-select/.test(url)) return Promise.resolve(Buffer.from(''));
    const m = url.match(/assets\/js\/pos\.js/);
    if (m) return Promise.resolve(fs.readFileSync(path.join(WEBROOT, 'admin/assets/js/pos.js')));
    return Promise.resolve(Buffer.from(''));
  }
}
const vc = new VirtualConsole(); vc.on('jsdomError', e => errors.push(String(e.message || e).slice(0, 200)));
const dom = new JSDOM(html, { url: BASE + 'index.php', runScripts: 'dangerously', resources: new Loader(), pretendToBeVisual: true, virtualConsole: vc });
const w = dom.window;
// real fetch -> PHP server, carrying the cashier's session
w.fetch = (u, o = {}) => fetch(new URL(u, BASE).href, { ...o, headers: { ...(o.headers || {}), cookie, 'user-agent': 'Python-urllib/3.12' }, redirect: 'manual' });
w.alert = m => { w.__alerts = (w.__alerts || []).concat(String(m)); };
w.confirm = () => true; w.prompt = () => '5'; w.open = () => null;
w.HTMLElement.prototype.scrollIntoView = () => {};
const sleep = ms => new Promise(r => setTimeout(r, ms));
const type = (el, v) => { el.value = v; el.dispatchEvent(new w.Event('input', { bubbles: true })); };
const key = (el, k, extra = {}) => el.dispatchEvent(new w.KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...extra }));

(async () => {
  await sleep(1200);
  const cart = () => Object.keys(w.touchCart || {}).length;
  ok('page scripts ran (addTouchCartItem, clearTouchCart, posToast)', typeof w.addTouchCartItem === 'function' && typeof w.clearTouchCart === 'function' && typeof w.posToast === 'function');
  const search = w.document.getElementById('posFilterSearch'); ok('search/scan box present', !!search);

  // 1. SCAN: type a barcode quickly then Enter (what a USB scanner does) -> exactly ONE line, qty 1
  type(search, '8901000000011'); key(search, 'Enter');
  await sleep(900);                     // longer than the 150ms debounce that used to cause a double-add
  ok('scan adds exactly one line', cart() === 1, 'cart size ' + cart());
  ok('scan quantity is 1 (no double add from debounce race)', w.touchCart[1] && w.touchCart[1].qty === 1, JSON.stringify(w.touchCart));
  ok('price is the discounted 430', w.touchCart[1] && w.touchCart[1].price === 430);

  // 2. scanning the same barcode again increments, never duplicates the line
  type(search, '8901000000011'); key(search, 'Enter'); await sleep(700);
  ok('second scan -> qty 2, still one line', cart() === 1 && w.touchCart[1].qty === 2);

  // 3. unknown barcode -> toast, nothing added
  type(search, '9999999999999'); key(search, 'Enter'); await sleep(700);
  ok('unknown barcode adds nothing', cart() === 1);
  ok('unknown barcode shows an error toast', /not found/i.test((w.document.getElementById('posToastBox') || {}).textContent || ''));

  // 4. direct quantity edit + stock cap
  w.setTouchQty(1, 5); ok('direct qty edit', w.touchCart[1].qty === 5);
  w.setTouchQty(1, 9999); ok('qty capped at stock (50)', w.touchCart[1].qty === 50, w.touchCart[1].qty);
  w.setTouchQty(1, 2.6); ok('fraction rounded to whole while store is integer-only', w.touchCart[1].qty === 3, w.touchCart[1].qty);
  w.setTouchQty(1, 2);
  const row = w.document.getElementById('posActiveCartList').textContent;
  ok('cart row shows qty input and line total 860.00', /860\.00/.test(row), row.slice(0, 120));

  // 5. XSS: hostile product name must be rendered as text, not HTML
  w.addTouchCartItem(77, '<img src=x onerror="window.__xss=1">', 10, 5, '', 'X');
  await sleep(100);
  ok('product name is HTML-escaped in the cart', w.__xss !== 1 && !w.document.querySelector('#posActiveCartList img[src="x"]'));
  w.setTouchQty(77, 0); ok('qty 0 removes the line', !w.touchCart[77]);

  // 6. F-keys do not throw and F10 clears the cart IN PLACE (the old reassign bug)
  const ref = w.touchCart;
  key(w.document.body, 'F10');
  await sleep(100);
  ok('F10 new sale clears the cart', cart() === 0);
  ok('clear keeps the SAME object (page + pos.js stay in sync)', w.touchCart === ref);
  ok('checkout button disabled on empty cart', w.document.getElementById('btnPOSCheckoutTrigger').disabled === true);
  const prevent = []; for (const k of ['F1', 'F2', 'F5', 'F6']) { const e = new w.KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true }); w.dispatchEvent(e); prevent.push(e.defaultPrevented); }
  ok('F1/F2/F5/F6 are intercepted (no browser help/reload)', prevent.every(Boolean), prevent);

  // 7. hold -> cart cleared, resume payload restores
  type(search, 'RICE5'); key(search, 'Enter'); await sleep(700);
  ok('SKU scan adds product', cart() === 1);
  w.prompt = () => 'test hold';
  w.suspendPOSCart(); await sleep(1200);
  ok('hold clears the on-screen cart', cart() === 0, (w.__alerts || []).join('|'));

  // 8. checkout end-to-end through the real UI function: pay cash, expect success + cart cleared + idempotency key rotated
  type(search, '8901000000028'); key(search, 'Enter'); await sleep(700);   // Milk 90
  ok('milk added', cart() === 1);
  w.submitPOSCheckoutFinalist();
  const cashEl = w.document.getElementById('splitCash'); ok('payment modal field exists', !!cashEl);
  if (cashEl) {
    cashEl.value = '100'; cashEl.dispatchEvent(new w.Event('input', { bubbles: true }));
    const ordersBefore = require('child_process').execSync("mariadb -uroot groco_test -N -e \"select count(*) from orders where order_number like 'POS-%'\"").toString().trim();
    w.confirmPOSSale(); await sleep(1800);
    const ordersAfter = require('child_process').execSync("mariadb -uroot groco_test -N -e \"select count(*) from orders where order_number like 'POS-%'\"").toString().trim();
    ok('exactly one new POS order was created', Number(ordersAfter) === Number(ordersBefore) + 1, ordersBefore + ' -> ' + ordersAfter);
    ok('milk stock decreased by 1', require('child_process').execSync("mariadb -uroot groco_test -N -e \"select stock from products where id=2\"").toString().trim() === '19');
    ok('checkout via UI completes and clears cart', cart() === 0, (w.__alerts || []).join('|'));
    ok('idempotency key reset after success', !w.posIdemKey);
  }
  console.log('\nJS errors seen:', errors.length ? errors : 'none');
  const failed = results.filter(r => !r[1]).length; console.log(`RESULT: ${results.length - failed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})();
