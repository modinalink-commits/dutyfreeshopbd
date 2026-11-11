{{-- Bangla font for the whole system: Bornomala (self-hosted). Included by typography + every standalone page. --}}
<style id="bn-font">
@font-face{font-family:'Bornomala';src:url('/fonts/Bornomala-Regular.ttf?v=2') format('truetype');font-weight:400 500;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965,U+20B9}
@font-face{font-family:'Bornomala';src:url('/fonts/Bornomala-Bold.ttf?v=2') format('truetype');font-weight:600 800;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965,U+20B9}
/* any text set in Inter (or Inter Chat) shows Bangla letters in Bornomala too, so no stack can fall back to a system Bangla font */
@font-face{font-family:'Inter';src:url('/fonts/Bornomala-Regular.ttf?v=2') format('truetype');font-weight:400 500;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965,U+20B9}
@font-face{font-family:'Inter';src:url('/fonts/Bornomala-Bold.ttf?v=2') format('truetype');font-weight:600 800;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965,U+20B9}
@font-face{font-family:'Inter Chat';src:url('/fonts/Bornomala-Regular.ttf?v=2') format('truetype');font-weight:400 500;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965,U+20B9}
@font-face{font-family:'Inter Chat';src:url('/fonts/Bornomala-Bold.ttf?v=2') format('truetype');font-weight:600 800;font-style:normal;font-display:swap;unicode-range:U+0980-09FF,U+200C-200D,U+0964-0965,U+20B9}
html body button,html body input,html body select,html body textarea{font-family:inherit}html body pre,html body code,html body kbd,html body samp{font-family:Inter,Bornomala,sans-serif}
</style>
