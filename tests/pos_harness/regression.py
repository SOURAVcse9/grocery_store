#!/usr/bin/env python3
"""
GroCo POS regression suite  —  FOR A THROW-AWAY TEST DATABASE ONLY. NEVER RUN AGAINST PRODUCTION.
See README.md in this folder. Exit code 0 = all checks passed.
"""
import json, re, sys, threading, urllib.parse
from client import C, sql

PASS = FAIL = 0
def check(name, cond, detail=''):
    global PASS, FAIL
    if cond: PASS += 1; print(f'  PASS  {name}')
    else:    FAIL += 1; print(f'  FAIL  {name}  {detail}')

def sale(c, items, **kw):
    d = {'items': json.dumps(items), 'discount': '0', 'cash': '0', 'card': '0', 'bkash': '0', 'wallet': '0', 'bank_transfer': '0', 'customer_id': '0', 'note': 'reg'}
    d.update(kw); return c.jpost('/admin/pos/checkout.php', d)
def page_msg(c, path, data):
    code, t, l = c.post(path, data)
    m = re.search(r'role="(?:alert|status)"[^>]*>\s*<i[^>]*></i>\s*(.*?)\s*</div>', t, re.S)
    return code, (re.sub(r'<[^>]+>', '', m.group(1)).strip() if m else '')

print('== 1. CRITICAL REGRESSION: login -> POS -> scan -> cart -> checkout -> payment -> order -> inventory -> receipt')
cash = C(); code, loc = cash.login('cashier'); check('admin login', code in (200, 302))
code, t, l = cash.req('/admin/pos/index.php'); check('POS page loads (no shift yet)', code == 200)
check('open shift', cash.post('/admin/pos/register.php', {'pos_action': 'open_shift', 'opening_cash': '1000'})[0] == 302)
code, t, l = cash.req('/admin/pos/index.php'); check('POS terminal loads with shift', code == 200 and 'posFilterSearch' in t)
s = cash.jget('/admin/pos/ajax/search_products.php?q=8901000000011'); check('barcode scan finds product, discounted price', s['products'][0]['price'] == 430)
stock_before = float(sql("select stock from products where id=1"))
r = sale(cash, [{'id': 1, 'qty': 2, 'price': 430}], cash='1000'); check('checkout succeeds', r.get('success'), r)
check('total = 2 x 430 = 860', r.get('total') == 860.0, r); check('change = 140', r.get('change') == 140.0, r)
check('order row created', sql(f"select count(*) from orders where id={r['order_id']}") == '1')
check('inventory decreased by 2', float(sql("select stock from products where id=1")) == stock_before - 2)
check('inventory movement logged', sql(f"select count(*) from inventory_logs where note like '%{r['order_number']}%'") == '1')
check('ledger income = 860 (cash net of change)', sql(f"select sum(amount) from transactions where reference like '%{r['order_number']}%'") in ('860.00', '860'))
code, t, l = cash.req(f"/admin/pos/receipts.php?id={r['order_id']}&w=80"); check('receipt renders', code == 200 and r['order_number'] in t and '860.00' in t)
oid_main = r['order_id']; on_main = r['order_number']

print('== 2. Server-side integrity (previously exploitable)')
check('negative qty rejected', not sale(cash, [{'id': 3, 'qty': -5}], cash='500')['success'])
check('negative discount rejected', not sale(cash, [{'id': 3, 'qty': 1}], discount='-1000', cash='5000')['success'])
check('zero payment rejected', not sale(cash, [{'id': 3, 'qty': 1}])['success'])
check('underpayment rejected', not sale(cash, [{'id': 3, 'qty': 1}], cash='10')['success'])
check('discount > subtotal rejected', not sale(cash, [{'id': 3, 'qty': 1}], discount='999', cash='999')['success'])
check('discount over policy % rejected', not sale(cash, [{'id': 1, 'qty': 1}], discount='100', cash='999')['success'])
check('cashier cannot tamper price', not sale(cash, [{'id': 3, 'qty': 1, 'price': 1}], cash='9')['success'])
sql("update products set stock=3 where id=2")
check('duplicate cart lines cannot oversell', not sale(cash, [{'id': 2, 'qty': 2}, {'id': 2, 'qty': 2}], cash='999')['success'])
check('stock not changed by rejected sales', sql("select stock from products where id=2") == '3')
check('fraction rejected on integer schema', not sale(cash, [{'id': 3, 'qty': 2.75}], cash='999')['success'])
check('card > total rejected', not sale(cash, [{'id': 3, 'qty': 1}], card='100')['success'])
check('inactive product rejected', (sql("update products set is_active=0 where id=4") or True) and not sale(cash, [{'id': 4, 'qty': 1}], cash='99')['success'])
sql("update products set is_active=1 where id=4")

print('== 3. Multi-payment, VAT, coupon-style discount, idempotency')
check('cash 200 + bKash 150 against 450 is SHORT -> rejected', not sale(cash, [{'id': 3, 'qty': 10}], cash='200', bkash='150', idempotency_key='reg-multi-1')['success'])
r = sale(cash, [{'id': 3, 'qty': 10}], cash='300', bkash='150', idempotency_key='reg-multi-2'); check('cash 300 + bKash 150 = 450 exact', r.get('success') and r['change'] == 0, r)
mk = sql(f"select note from orders where id={r['order_id']}"); check('payment split stored', 'bkash=150.00' in mk and 'cash=300.00' in mk, mk)
check('ledger has one row per tender', sql(f"select count(*) from transactions where reference like '%{r['order_number']}%'") == '2')
r2 = sale(cash, [{'id': 3, 'qty': 10}], cash='300', bkash='150', idempotency_key='reg-multi-2'); check('same idempotency key returns SAME sale', r2.get('duplicate') and r2['order_number'] == r['order_number'], r2)
check('only one order for key', sql("select count(*) from orders where note like '%idem=reg-multi-2%'") == '1')
r = sale(cash, [{'id': 3, 'qty': 2}], discount='9', cash='100'); check('discount within 15% limit ok', r.get('success') and r['total'] == 81.0, r)
mgr = C(); mgr.login('manager'); mgr.req('/admin/pos/index.php')
mgr.post('/admin/pos/register.php', {'pos_action': 'open_shift', 'opening_cash': '0'})
r = sale(mgr, [{'id': 1, 'qty': 1, 'price': 400}], cash='500'); check('manager (pos.override) may override price', r.get('success') and r['total'] == 400.0, r)
sql("insert into settings(key_name,value) values('site_tax','5') on duplicate key update value='5'")
r = sale(cash, [{'id': 3, 'qty': 2}], cash='200'); check('5% VAT applied server-side: 90 + 4.50', r.get('success') and r['vat'] == 4.5 and r['total'] == 94.5, r)
sql("update settings set value='0' where key_name='site_tax'")
sql("insert into users(full_name,email,phone,wallet_balance) values('Wal Let','w@t.l','01711111111',100)"); uid = sql("select max(id) from users")
check('wallet > balance rejected', not sale(cash, [{'id': 3, 'qty': 4}], wallet='150', cash='100', customer_id=uid)['success'])
check('wallet needs registered customer', not sale(cash, [{'id': 3, 'qty': 1}], wallet='45')['success'])
r = sale(cash, [{'id': 3, 'qty': 2}], wallet='90', customer_id=uid); check('wallet payment ok', r.get('success'), r); check('wallet debited exactly', sql(f"select wallet_balance from users where id={uid}") == '10.00')

print('== 4. Concurrency')
sql("update products set stock=3 where id=2"); out = []
def w(n):
    c = C(); c.login('cashier'); c.req('/admin/pos/index.php'); out.append(sale(c, [{'id': 2, 'qty': 1}], cash='100', idempotency_key=f'race-{n}'))
ts = [threading.Thread(target=w, args=(i,)) for i in range(8)]; [t.start() for t in ts]; [t.join() for t in ts]
check('8 parallel buyers, stock 3 -> exactly 3 sales', sum(1 for x in out if x.get('success')) == 3, [x.get('error') for x in out])
check('stock never negative', sql("select stock from products where id=2") == '0')

print('== 5. Shift: expected cash, cash in/out, close with variance')
S = '/admin/pos/ajax/shifts.php'
sm = cash.jpost(S, {'action': 'summary'})['summary']; before = sm['expected_cash']
check('cash-in', cash.jpost(S, {'action': 'cash_in', 'amount': '100', 'notes': 'float'}).get('success'))
check('cash-out needs reason', not cash.jpost(S, {'action': 'cash_out', 'amount': '50', 'notes': ''}).get('success'))
check('cash-out cannot exceed drawer', not cash.jpost(S, {'action': 'cash_out', 'amount': '9999999', 'notes': 'x'}).get('success'))
check('cash-out ok', cash.jpost(S, {'action': 'cash_out', 'amount': '40', 'notes': 'safe drop'}).get('success'))
sm = cash.jpost(S, {'action': 'summary'})['summary']; check('expected = before +100 -40', abs(sm['expected_cash'] - (before + 60)) < 0.01, sm)
check('bKash/wallet NOT counted in drawer cash', sm['bkash'] > 0 and sm['cash_sales'] < sm['gross_sales'])
check('cannot open 2nd shift', not cash.jpost(S, {'action': 'open_shift', 'opening_cash': '1'}).get('success'))
exp = sm['expected_cash']; cl = cash.jpost(S, {'action': 'close_shift', 'actual_cash': str(exp - 5)})
check('close shift, variance -5 stored not hidden', cl.get('success') and cl['difference'] == -5.0, cl)
check('cannot close twice', not cash.jpost(S, {'action': 'close_shift', 'actual_cash': '1'}).get('success'))
check('no sale without open shift', not sale(cash, [{'id': 3, 'qty': 1}], cash='99')['success'])
cash.post('/admin/pos/register.php', {'pos_action': 'open_shift', 'opening_cash': '500'})

print('== 6. Hold / Resume')
H = '/admin/pos/hold-orders.php'; sql("delete from pos_hold_orders")
cart = [{'id': 3, 'name': 'Potato', 'price': 45, 'qty': 2, 'stock': 90}]
check('hold cart', cash.jpost(H, {'pos_action': 'hold', 'customer_id': '0', 'cart_data': json.dumps(cart), 'hold_notes': 'forgot wallet'}).get('success'))
check('garbage cart refused', not cash.jpost(H, {'pos_action': 'hold', 'cart_data': 'zzz'}).get('success'))
hid = sql("select max(id) from pos_hold_orders")
check('GET delete no longer works', (cash.req(f'{H}?action=delete&id={hid}')[0], sql(f"select count(*) from pos_hold_orders where id={hid}")) == (200, '1'))
sql("insert into pos_hold_orders(admin_id,cart_data,hold_notes,created_at) values(3,'[]','mgr',NOW())"); mid = sql("select max(id) from pos_hold_orders")
check("cannot resume another cashier's hold", not cash.jpost(H, {'pos_action': 'resume', 'id': mid}).get('success'))
r = cash.jpost(H, {'pos_action': 'resume', 'id': hid}); check('resume returns cart', r.get('success') and r['cart'][0]['qty'] == 2, r)
check('resume twice impossible', not cash.jpost(H, {'pos_action': 'resume', 'id': hid}).get('success'))

print('== 7. Returns, refunds, void')
pot_before = sql("select stock from products where id=3")
r = sale(cash, [{'id': 3, 'qty': 4}], discount='10', cash='200'); oid, on = r['order_id'], r['order_number']
check('sale took 4 from stock', float(sql("select stock from products where id=3")) == float(pot_before) - 4)
check('cashier w/o pos.return blocked from returns page', cash.req('/admin/pos/returns.php')[0] in (302, 403))
RP = '/admin/pos/returns.php'
check('over-return rejected', 'left to return' in page_msg(mgr, RP, {'pos_action': 'return', 'order_id': oid, 'returns[3]': '5', 'refund_method': 'cash', 'reason': 'x'})[1])
check('reason mandatory', 'reason' in page_msg(mgr, RP, {'pos_action': 'return', 'order_id': oid, 'returns[3]': '1', 'refund_method': 'cash', 'reason': ''})[1].lower())
page_msg(mgr, RP, {'pos_action': 'return', 'order_id': oid, 'returns[3]': '1', 'refund_method': 'cash', 'reason': 'damaged'})
page_msg(mgr, RP, {'pos_action': 'return', 'order_id': oid, 'returns[3]': '3', 'refund_method': 'cash', 'reason': 'rest'})
check('refund pro-rates discount: 42.50 + 127.50 = 170.00', sql(f"select sum(refund_amount) from pos_returns where order_id={oid}") == '170.00')
check('stock restored by returns (back to pre-sale level)', float(sql("select stock from products where id=3")) == float(pot_before))
check('duplicate return blocked', 'left to return' in page_msg(mgr, RP, {'pos_action': 'return', 'order_id': oid, 'returns[3]': '1', 'refund_method': 'cash', 'reason': 'again'})[1])
check('original sale kept & marked refunded', sql(f"select payment_status from orders where id={oid}") == 'refunded')
code, t, l = mgr.req(RP + '?order_number=ORD-1'); check('online order refused in POS returns', 'online order' in t.lower())
r = sale(cash, [{'id': 1, 'qty': 1}], cash='500'); oidv = r['order_id']; stk = sql("select stock from products where id=1")
check('cashier cannot void', 'authorization' in page_msg(cash, RP, {'pos_action': 'void', 'order_id': oidv, 'reason': 'x'})[1].lower() or cash.post(RP, {'pos_action': 'void', 'order_id': oidv, 'reason': 'x'})[0] in (302, 403))
check('manager void ok', 'voided' in page_msg(mgr, RP, {'pos_action': 'void', 'order_id': oidv, 'reason': 'wrong basket'})[1].lower())
check('void keeps row, restores stock', sql(f"select status from orders where id={oidv}") == 'cancelled' and float(sql("select stock from products where id=1")) == float(stk) + 1)
check('void twice blocked', 'already' in page_msg(mgr, RP, {'pos_action': 'void', 'order_id': oidv, 'reason': 'x'})[1].lower())

HP = '/admin/pos/history.php'; mgr.req(HP)
r = sale(cash, [{'id': 3, 'qty': 1}], cash='100'); hv = r['order_id']
check('history void needs a reason', 'reason is required' in mgr.post(HP, {'action': 'void', 'order_id': hv, 'reason': ''})[1])
check('history void works with reason', 'voided and inventory' in mgr.post(HP, {'action': 'void', 'order_id': hv, 'reason': 'test'})[1])
onl_id = sql("select id from orders where order_number='ORD-1'")
check('history void refuses ONLINE orders (old code allowed it)', 'POS sale not found' in mgr.post(HP, {'action': 'void', 'order_id': onl_id, 'reason': 'x'})[1] and sql(f"select status from orders where id={onl_id}") == 'pending')
check('cashier without pos.void refused in history', 'administrative permission' in cash.post(HP, {'action': 'void', 'order_id': hv, 'reason': 'x'})[1])

print('== 8. Receipts / reprint')
code, t, l = cash.req(f'/admin/pos/receipts.php?id={oid_main}&w=58'); check('reprint 58mm stamped DUPLICATE', code == 200 and 'DUPLICATE COPY' in t)
check('A4 receipt', cash.req(f'/admin/pos/receipts.php?id={oid_main}&w=a4')[0] == 200)
check('reprints logged', int(sql("select count(*) from admin_activity_logs where activity_type='pos.reprint'")) >= 2)
check('reprint created no new order', sql(f"select count(*) from orders where order_number='{on_main}'") == '1')
sql("insert ignore into orders(order_number,user_id,subtotal,total_amount,created_at) values('ORD-9',1,5,5,NOW())")
check('online order not printable via POS', cash.req('/admin/pos/receipts.php?id=' + sql("select id from orders where order_number='ORD-9'"))[0] == 404)

print('== 9. Search / security probes')
check('SQL-ish search is inert', cash.jget('/admin/pos/ajax/search_products.php?q=' + urllib.parse.quote("' OR 1=1 --"))['products'] == [])
anon = C(); check('anonymous checkout 401', anon.req('/admin/pos/checkout.php', {'items': '[]'})[0] in (401, 403))
nocsrf = C(); nocsrf.login('cashier'); nocsrf.req('/admin/pos/index.php')
check('checkout without CSRF -> 419', nocsrf.req('/admin/pos/checkout.php', {'items': json.dumps([{'id': 3, 'qty': 1}]), 'cash': '99'})[0] == 419)
check('legacy process_sale.php has same protection', nocsrf.req('/admin/pos/ajax/process_sale.php', {'items': '[]'})[0] == 419)

print('== 10. Dashboard / key pages load')
for p in ('/admin/index.php', '/admin/index.php?range=7d', '/admin/index.php?range=custom&from=2026-10-01&to=2026-10-07', '/admin/pos/register.php', '/admin/pos/history.php', '/admin/pos/receipts.php', '/admin/products/index.php', '/admin/inventory/index.php', '/admin/orders/index.php', '/admin/customers/index.php', '/admin/finance/index.php'):
    c = C(); c.login('root'); code, t, l = c.req(p); check(f'GET {p}', code == 200 and 'Fatal error' not in t, code)
print(f'\nRESULT: {PASS} passed, {FAIL} failed'); sys.exit(1 if FAIL else 0)
