{{-- Mother Commerce — Global Typography (shared by the admin backend and the storefront).
     One place for: the font files, the size / weight / line-height tokens and the reusable text classes.
     Fonts are self-hosted (Inter for UI/English, Bornomala for Bangla); nothing is fetched from a third party. --}}
<style id="mc-type">
@font-face{font-family:'Inter';font-weight:400;font-style:normal;font-display:swap;src:url(/fonts/inter-latin-400-normal.woff2) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Inter';font-weight:500;font-style:normal;font-display:swap;src:url(/fonts/inter-latin-500-normal.woff2) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Inter';font-weight:600;font-style:normal;font-display:swap;src:url(/fonts/inter-latin-600-normal.woff2) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@font-face{font-family:'Inter';font-weight:700;font-style:normal;font-display:swap;src:url(/fonts/inter-latin-700-normal.woff2) format('woff2');unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
@include('bn_font')
:root{
  --font-ui:Inter,"Bornomala",system-ui,-apple-system,"Segoe UI",Arial,sans-serif;
  --fs-10:10px;--fs-11:11px;--fs-12:12px;--fs-13:13px;--fs-14:14px;--fs-15:15px;--fs-16:16px;--fs-18:18px;--fs-20:20px;--fs-24:24px;
  --fw-normal:400;--fw-medium:500;--fw-semibold:600;--fw-bold:700;
  --lh-heading:1.25;--lh-body:1.45;--lh-table:1.35;--lh-label:1.25;
}
body{font-family:var(--font-ui)}
/* reusable text classes (spec §47-51): use these instead of inline font-size */
.text-micro{font-size:var(--fs-10);line-height:var(--lh-label)}
.text-caption{font-size:var(--fs-11);line-height:var(--lh-label)}
.text-label,.form-label{font-size:var(--fs-12);font-weight:var(--fw-medium);line-height:var(--lh-label)}
.text-body,.form-control-text{font-size:var(--fs-13);line-height:var(--lh-body)}
.text-body-strong{font-size:var(--fs-13);font-weight:var(--fw-semibold);line-height:var(--lh-body)}
.text-section-title{font-size:var(--fs-18);font-weight:var(--fw-bold);line-height:var(--lh-heading)}
.text-page-title{font-size:clamp(18px,1.5vw,22px);font-weight:var(--fw-bold);line-height:var(--lh-heading)}
.text-number,.table-number,.text-price{font-variant-numeric:tabular-nums}
.text-muted{color:var(--text-muted,#6B7280)}
.table-text{font-size:var(--fs-12);line-height:var(--lh-table)}
.table-header-text{font-size:var(--fs-12);font-weight:var(--fw-semibold)}
.table-total{font-size:var(--fs-13);font-weight:var(--fw-bold);font-variant-numeric:tabular-nums}
.btn-text{font-size:var(--fs-12);font-weight:var(--fw-semibold)}
.btn-text-sm{font-size:var(--fs-11);font-weight:var(--fw-semibold)}
.btn-text-primary{font-size:var(--fs-13);font-weight:var(--fw-bold)}
.form-helper{font-size:11px;line-height:var(--lh-label)}
.form-error{font-size:var(--fs-12);font-weight:var(--fw-medium)}
.customer-name{font-size:var(--fs-13);font-weight:var(--fw-bold)}
.customer-meta{font-size:var(--fs-12);font-weight:var(--fw-medium)}
.customer-address{font-size:12px;line-height:1.35}
.customer-chip,.customer-result{font-size:12px;font-weight:var(--fw-bold)}
</style>
