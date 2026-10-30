/* ---------- website module (separate from inventory) ---------- */
GETS['/admin/website'] = ({ role, q }) => {
  if (role !== 'owner') return null;
  const id = +q.get('id') || 0, p = (id && get('SELECT * FROM web_products WHERE id=?', id)) || { name: '', category: '', price: '', image: '', description: '', active: 1 };
  const ps = all('SELECT * FROM web_products ORDER BY id DESC'), cats = [...new Set(all('SELECT DISTINCT category c FROM web_products').map(x => x.c).concat(allCats()))].filter(Boolean).sort();
  const form = `<form method="post" action="/admin/website" class="pn" id="wf">${hid('id', id)}${hid('image', p.image && p.image.startsWith('data:') ? '__keep__' : p.image)}<h2>${id ? 'Edit website product' : 'Add website product'}</h2>
<p class="note">These products are shown on greencyclectg.com only. They are not linked to inventory, stock or sales.</p>
<div class="fr"><label>Product name<input name="name" value="${esc(p.name)}" style="width:300px" required></label><label>Category<input name="category" list="wc" value="${esc(p.category)}" style="width:200px" required><datalist id="wc">${cats.map(c => `<option value="${esc(c)}">`).join('')}</datalist></label><label>Price (৳)<input name="price" type="number" step="any" value="${esc(p.price)}" style="width:140px" required></label></div>
<div class="fr"><label>Description<textarea name="description" rows="3" style="width:520px">${esc(p.description)}</textarea></label></div>
<div class="fr"><label>Photo (choose a file)<input type="file" id="wfile" accept="image/*"></label><label>or image link<input name="image_url" value="${esc(p.image && !p.image.startsWith('data:') && !p.image.startsWith('/img/') ? p.image : '')}" style="width:300px" placeholder="https://..."></label>${p.image ? `<img id="wprev" src="${esc(imgSrc(p.id ? p : { ...p, id: 0 }))}" style="height:70px;border-radius:10px;border:1px solid #DDE5DF">` : '<img id="wprev" style="height:70px;border-radius:10px;display:none">'}</div>
<label class="f" style="flex-direction:row;gap:8px;font-size:15px;align-items:center"><input type="checkbox" name="active" value="1" ${p.active ? 'checked' : ''}> Show on website</label>
<p><button class="btn">Save</button> ${id ? lnk('/admin/website', 'Cancel') : ''}</p><input type="hidden" name="image_data" id="wdata"></form>
<script>document.getElementById('wfile').onchange=e=>{const f=e.target.files[0];if(!f)return;const r=new FileReader();r.onload=()=>{const im=new Image();im.onload=()=>{const s=Math.min(1,800/Math.max(im.width,im.height)),c=document.createElement('canvas');c.width=im.width*s;c.height=im.height*s;c.getContext('2d').drawImage(im,0,0,c.width,c.height);const d=c.toDataURL('image/jpeg',.82);document.getElementById('wdata').value=d;const pv=document.getElementById('wprev');pv.src=d;pv.style.display='block'};im.src=r.result};r.readAsDataURL(f)}</script>`;
  const list = `<div class="pn"><h2>Website products (${ps.length})</h2>${tbl(['', 'Product', 'Category', '>Price', 'Status', ''], ps.map(x => tr([`<img src="${esc(imgSrc(x))}" style="width:48px;height:48px;object-fit:cover;border-radius:8px">`, `<b class="nm">${esc(x.name)}</b>`, esc(x.category), R(money(x.price)), x.active ? pill('Visible', 'ok') : pill('Hidden', 'gr'), `<span style="white-space:nowrap">${lnk('/admin/website?id=' + x.id, 'Edit', 'btn o s')} ${confirmForm('/admin/website/delete', hid('id', x.id), 'Delete this website product?')}</span>`])), 'No website products yet.')}</div>`;
  return adminPage(role, '/admin/website', 'Website products', 'The product list of greencyclectg.com. Separate from inventory', '', form + list, q);
};
POSTS['/admin/website'] = (f, { role }) => {
  if (role !== 'owner') throw new Error('Owner only');
  const id = +f.get('id'), name = (f.get('name') || '').trim(), category = (f.get('category') || '').trim(); if (!name || !category) throw new Error('Name and category are required');
  const old = id && get('SELECT image FROM web_products WHERE id=?', id);
  let image = f.get('image') || ''; const data = f.get('image_data') || '', url = (f.get('image_url') || '').trim();
  if (/^data:image\/(jpeg|png|webp);base64,/.test(data) && data.length < 900000) image = data; else if (url && url !== image) image = url; else if (image === '__keep__') image = old ? old.image : '';
  if (!image) image = '/img/' + encodeURIComponent(category) + '.svg';
  const v = [name, category, num(f.get('price')), image, (f.get('description') || '').trim(), f.get('active') ? 1 : 0];
  if (id) run('UPDATE web_products SET name=?,category=?,price=?,image=?,description=?,active=? WHERE id=?', ...v, id); else run("INSERT INTO web_products(name,category,price,image,description,active,created) VALUES(?,?,?,?,?,?,date('now'))", ...v);
  return go('/admin/website', 'Saved');
};
POSTS['/admin/website/delete'] = (f, { role }) => { if (role !== 'owner') throw new Error('Owner only'); run('DELETE FROM web_products WHERE id=?', +f.get('id')); return go('/admin/website', 'Deleted'); };

GETS['/admin/orders'] = ({ role, q }) => {
  const rows = all('SELECT * FROM web_orders ORDER BY id DESC LIMIT 100');
  return adminPage(role, '/admin/orders', 'Website orders', 'Orders placed on greencyclectg.com. They do not touch stock or sales. Call the customer, then record the sale in POS if you wish', '', `<div class="pn">${tbl(['#', 'Date', 'Customer', 'Items', '>Total', 'Payment', 'Status', ''], rows.map(o => tr([o.id, fmtDate(o.date), `<b class="nm">${esc(o.name)}</b><br><span class="mu">${esc(o.phone)}<br>${esc(o.address)}${o.city ? ', ' + esc(o.city) : ''}</span>${o.note ? '<br><span class="mu">Note: ' + esc(o.note) + '</span>' : ''}`, JSON.parse(o.items).map(i => esc(i.name) + ' ×' + i.qty).join(', '), R(money(o.total)), esc(o.payment), pill(o.status, o.status === 'New' ? 'warn' : o.status === 'Delivered' ? 'ok' : 'gr'),
    `<form method="post" action="/admin/orders" style="display:inline">${hid('id', o.id)}${['Contacted', 'Delivered', 'Cancelled'].filter(s => s !== o.status).map(s => `<button class="btn ${s === 'Cancelled' ? 'r' : 'o'} s" name="status" value="${s}">${s}</button>`).join(' ')}</form> <a class="btn o s" style="color:#128C7E" target="_blank" href="https://wa.me/${waPhone(o.phone)}">WhatsApp</a>`])), 'No website orders yet.')}</div>`, q);
};
POSTS['/admin/orders'] = f => { const s = f.get('status'); if (['Contacted', 'Delivered', 'Cancelled'].includes(s)) run('UPDATE web_orders SET status=? WHERE id=?', s, +f.get('id')); return go('/admin/orders', 'Updated'); };
