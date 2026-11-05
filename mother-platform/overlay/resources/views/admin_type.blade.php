{{-- Mother Commerce — Admin Typography Scale. Included once by layout.blade.php inside @auth (backend only).
     Sizes / weights / line-heights only — no colour is touched. Scale: page title 20–22 / 700, module header 15 / 700, body 13, table 12, label 12, helper 11, button 12–13 / 600. --}}
<style id="admin-type">
body{font-family:var(--font-ui)}
main{font-size:var(--fs-13);line-height:var(--lh-body)}
main h1{font-size:clamp(18px,1.5vw,22px);font-weight:700;line-height:var(--lh-heading)}
main h2{font-size:var(--fs-15);font-weight:700;line-height:1.3}
main h3{font-size:var(--fs-14);font-weight:600;line-height:1.3}
main h4{font-size:var(--fs-13);font-weight:600}
/* controls inherit the UI font (browsers default buttons/inputs to Arial/system-ui) */
main button,main input,main select,main textarea,main .btn{font-family:inherit}
main input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]),main select,main textarea{font-size:var(--fs-13)}
main input::placeholder,main textarea::placeholder{font-size:12.5px}
main label{font-size:var(--fs-12);line-height:var(--lh-label)}
main small,main .hint,main .helper{font-size:11px;line-height:1.3}
/* numbers line up vertically */
main table td,main table th,.pgx,main .num,main .n{font-variant-numeric:tabular-nums}
/* modal titles */
dialog h2,dialog h3,[role=dialog] h2,[role=dialog] h3,.modal h2,.modal h3,.modal-box h2,.modal-box h3{font-size:16px;font-weight:700}
/* sidebar */
aside a.nv,aside details summary{font-size:var(--fs-13)}
aside a.sub{font-size:12px}
/* weight scale: nothing heavier than bold inside the backend */
main table td,main table th{line-height:var(--lh-table)}
</style>
