#!/bin/sh
cd "$(dirname "$0")"
cat src/00_head.js src/10_assets.js src/11_brand.js src/20_logic.js src/40_public.js src/50_ui.js src/51_sales.js src/52_stock.js src/53_money.js src/54_ops.js src/55_web.js src/60_invoice.js src/70_router.js > server.js
node --check server.js && echo built $(wc -c < server.js) bytes
