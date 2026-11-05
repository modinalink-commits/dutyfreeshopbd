{{-- Mother Commerce — Storefront typography. Included early in store/layout.blade.php; `html body` prefix wins over page-level styles. Sizes / weights only — no colour, no layout change. --}}
<style id="store-type">
html body{font-family:var(--font-ui);font-size:var(--fs-15);line-height:var(--lh-body)}
/* section titles on the home page: 22px / 700 on desktop, 17px on phones (spec: 18–22 / 16–18) */
html body .st h2,html body .cts h2,html body .lvh h2,html body .sec-lv .lvh h2{font-size:22px!important;font-weight:700!important;line-height:1.25}
@media(max-width:640px){html body .st h2,html body .cts h2,html body .lvh h2{font-size:17px!important}html body .sec-lv .lvh h2{font-size:17px!important}}
/* product card: title 13 / 600 (2 lines), price 13 / 700, old price 11.5 / 400 */
html body .pc .nm{font-size:var(--fs-13);font-weight:600;line-height:1.3}
html body .pc .pr{font-size:var(--fs-13)}
html body .pc .pr b{font-weight:700;font-variant-numeric:tabular-nums}
html body .pc .pr s{font-size:11.5px;font-weight:400}
@media(max-width:640px){html body .pc .nm{font-size:12.5px}html body .pc .pr{font-size:12px}}
/* product page */
html body .pi h1{font-weight:700}
</style>
